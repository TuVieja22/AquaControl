/*
  AquaControl - ESP32 por WiFi (sin PC intermedia)

  El ESP32 habla directo con la pagina por WiFi:
    - cada 5 s envia la temperatura     POST /dashboard/api/data
    - cada 2 s pregunta si alimentar     GET  /dashboard/api/commands
      (boton "Alimentar ahora" y horarios programados)
    - al terminar el giro confirma       POST /dashboard/api/commands/{id}/ack

  Hardware:
    - DS18B20 (temperatura)    -> GPIO 18 (resistencia pull-up de 4.7k a 3.3V)
    - Servo SG90 (alimentador) -> GPIO 19 (rojo a 5V/VIN, marron a GND)

  Configuracion: copiar secrets.example.h como secrets.h y completar WiFi,
  IP del servidor y API key (secrets.h no se sube a GitHub).
  La red WiFi tiene que ser de 2.4 GHz (el ESP32 no se conecta a 5 GHz).

  Librerias: OneWire, DallasTemperature, ESP32Servo, ArduinoJson (v7).
  Placa: "ESP32 Dev Module".
*/

// Librerias: WiFi, pedidos HTTP, JSON, sensor de temperatura y servo.
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <ESP32Servo.h>
// Datos privados: red WiFi, direccion de la pagina y API key.
#include "secrets.h"

// ---------- Pines ----------
#define PIN_DS18B20 18
#define PIN_SERVO   19

// ---------- Alimentador: un solo giro (ida y vuelta) por orden ----------
const int SERVO_CERRADO = 0;    // grados con la compuerta cerrada
const int SERVO_ABIERTO = 90;   // grados con la compuerta abierta
const int MS_ABIERTO = 1000;    // tiempo abierto antes de volver

// ---------- Tiempos ----------
const unsigned long INTERVALO_TEMP_MS = 5000;     // envio de temperatura
const unsigned long INTERVALO_ORDENES_MS = 2000;  // consulta de ordenes
const unsigned long MS_CONVERSION = 800;          // DS18B20 a 12 bits tarda ~750 ms
const uint16_t HTTP_TIMEOUT_MS = 4000;

// Objetos que manejan el sensor de temperatura y el servo.
OneWire oneWire(PIN_DS18B20);
DallasTemperature sensorTemperatura(&oneWire);
Servo alimentador;

// Momento en que se hizo por ultima vez cada tarea (en milisegundos).
unsigned long ultimoPedidoTemp = 0;
unsigned long ultimaConsulta = 0;
unsigned long ultimoIntentoWifi = 0;
// Estado: sensor midiendo, aviso de conexion pendiente y ultimo motivo de desconexion.
bool convirtiendo = false;
bool avisoWifi = false;
volatile uint8_t motivoDesconexion = 0;

// Datos del router encontrados en el escaneo (para conectarse directo a ese canal/equipo).
int32_t canalRed = 0;
uint8_t bssidRed[6];
bool hayBssid = false;

// Si el router no responde (codigo 4), se prueba con otra potencia de transmision:
// en muchas placas genericas eso resuelve la conexion. Con esta placa y este router
// la que funciono fue 11 dBm, por eso se arranca con esa.
const wifi_power_t POTENCIAS[] = { WIFI_POWER_11dBm, WIFI_POWER_8_5dBm, WIFI_POWER_15dBm, WIFI_POWER_19_5dBm };
const char* NOMBRES_POTENCIA[] = { "11 dBm", "8.5 dBm", "15 dBm", "19.5 dBm" };
int nivelPotencia = 0;

// ---------------------------------------------------------------- WiFi

// Guarda el motivo que informa el router cuando rechaza o corta la conexion.
void alEventoWifi(WiFiEvent_t evento, WiFiEventInfo_t info) {
  if (evento == ARDUINO_EVENT_WIFI_STA_DISCONNECTED) {
    motivoDesconexion = info.wifi_sta_disconnected.reason;
  }
}

// Traduce el codigo de desconexion del router a un texto entendible.
const char* explicarMotivo(uint8_t motivo) {
  switch (motivo) {
    case 0:   return "todavia sin respuesta del router";
    case 2:   case 14: case 15: case 202: case 204:
              return "CLAVE INCORRECTA (revisa WIFI_PASSWORD, respeta mayusculas)";
    case 4:   return "el router no respondio a la conexion (se prueba con otra potencia; "
                     "si sigue, revisa filtro MAC o limite de equipos en el router)";
    case 201: case 210: case 211:
              return "NO ENCUENTRA LA RED (nombre mal escrito, red de 5 GHz o muy lejos)";
    case 212: return "senal demasiado debil";
    case 200: return "se perdio la senal del router";
    case 203: case 205:
              return "el router rechazo la conexion (filtro MAC o demasiados equipos?)";
    default:  return "motivo no identificado";
  }
}

// Diagnostico al arrancar: busca la red configurada entre las de 2.4 GHz visibles.
void buscarRed() {
  Serial.println("Buscando la red WiFi configurada...");
  // Escanea las redes y recorre la lista buscando la configurada.
  int total = WiFi.scanNetworks();
  bool encontrada = false;
  int mejorSenal = -1000;
  for (int i = 0; i < total; i++) {
    if (WiFi.SSID(i) == WIFI_SSID) {
      encontrada = true;
      Serial.printf("Red encontrada: senal %d dBm, canal %d, seguridad %d\n",
                    WiFi.RSSI(i), WiFi.channel(i), (int) WiFi.encryptionType(i));
      if (WiFi.RSSI(i) > mejorSenal) {   // si hay varios routers con el mismo nombre, el mas fuerte
        mejorSenal = WiFi.RSSI(i);
        canalRed = WiFi.channel(i);
        memcpy(bssidRed, WiFi.BSSID(i), 6);
        hayBssid = true;
      }
    }
  }
  // Si no aparece, avisa por el monitor serie.
  if (!encontrada) {
    Serial.printf("La red \"%s\" NO aparece entre las %d redes de 2.4 GHz visibles: "
                  "revisa el nombre exacto (mayusculas) o si es de 5 GHz.\n", WIFI_SSID, total);
  }
  WiFi.scanDelete();
}

// Devuelve true si hay WiFi; si no, intenta conectar (sin frenar el programa).
bool wifiListo() {
  // Ya conectado: avisa una sola vez y listo.
  if (WiFi.status() == WL_CONNECTED) {
    if (avisoWifi) {
      Serial.printf("WiFi conectado. IP del ESP32: %s  (senal %d dBm, potencia %s)\n",
                    WiFi.localIP().toString().c_str(), WiFi.RSSI(), NOMBRES_POTENCIA[nivelPotencia]);
      avisoWifi = false;
    }
    return true;
  }

  // Cada intento tiene 20 s para completarse antes de reintentar (sin bloquear el programa).
  if (ultimoIntentoWifi == 0 || millis() - ultimoIntentoWifi >= 20000) {
    if (ultimoIntentoWifi != 0) {
      Serial.printf("No se pudo conectar: %s (codigo %u)\n",
                    explicarMotivo(motivoDesconexion), motivoDesconexion);
      // El router no contesta: el siguiente intento va con menos potencia (y despues vuelve a empezar).
      nivelPotencia = (nivelPotencia + 1) % 4;
    }
    ultimoIntentoWifi = millis();
    motivoDesconexion = 0;

    // Nuevo intento de conexion, con la potencia que toca.
    WiFi.disconnect();
    WiFi.setSleep(false);   // sin ahorro de energia: algunos routers cortan al ESP32 si "duerme"
    WiFi.setTxPower(POTENCIAS[nivelPotencia]);
    Serial.printf("Conectando a \"%s\" (clave de %u caracteres, potencia %s)...\n",
                  WIFI_SSID, (unsigned) strlen(WIFI_PASSWORD), NOMBRES_POTENCIA[nivelPotencia]);
    if (hayBssid) {
      WiFi.begin(WIFI_SSID, WIFI_PASSWORD, canalRed, bssidRed);   // directo al router encontrado
    } else {
      WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    }
    avisoWifi = true;
  }
  return false;
}

// ---------------------------------------------------------------- HTTP

// Prepara un pedido a la pagina: direccion, tiempos de espera y API key.
bool iniciarPedido(HTTPClient& http, const String& ruta) {
  if (!http.begin(String(SERVER_URL) + ruta)) return false;
  http.setTimeout(HTTP_TIMEOUT_MS);
  http.setConnectTimeout(HTTP_TIMEOUT_MS);
  http.addHeader("X-Device-Key", DEVICE_KEY);
  http.addHeader("Content-Type", "application/json");
  return true;
}

// Explica por el monitor serie por que fallo un pedido.
void avisarErrorHttp(const char* que, int codigo) {
  if (codigo == 401) {
    Serial.printf("%s: la pagina rechazo la API key (401). Revisa DEVICE_KEY en secrets.h.\n", que);
  } else if (codigo < 0) {
    Serial.printf("%s: no se pudo conectar con %s (%s). La PC y la pagina estan prendidas?\n",
                  que, SERVER_URL, HTTPClient::errorToString(codigo).c_str());
  } else {
    Serial.printf("%s: respuesta HTTP %d\n", que, codigo);
  }
}

// ---------------------------------------------------------------- Alimentador

// Le avisa a la pagina como termino una orden (ejecutada o fallida).
void confirmarOrden(long id, bool ok, const char* mensaje) {
  HTTPClient http;
  if (!iniciarPedido(http, "/dashboard/api/commands/" + String(id) + "/ack")) return;

  // Arma el mensaje en formato JSON.
  JsonDocument body;
  body["estado"] = ok ? "ejecutado" : "fallido";
  if (mensaje) body["mensaje"] = mensaje;
  String payload;
  serializeJson(body, payload);

  // Lo envia y revisa la respuesta.
  int codigo = http.POST(payload);
  if (codigo == 200) {
    Serial.printf("Orden #%ld confirmada a la pagina (%s).\n", id, ok ? "ejecutada" : "fallida");
  } else {
    avisarErrorHttp("Confirmando orden", codigo);
  }
  http.end();
}

// Un solo giro por orden: abre, espera y cierra.
void alimentar(long id, float gramos) {
  Serial.printf("Orden #%ld: alimentar (racion de %.2f g) -> un giro de ida y vuelta\n", id, gramos);
  // Abre la compuerta, espera, la cierra y confirma.
  alimentador.write(SERVO_ABIERTO);
  delay(MS_ABIERTO);
  alimentador.write(SERVO_CERRADO);
  delay(500);
  confirmarOrden(id, true, nullptr);
}

// Le pregunta a la pagina si hay ordenes pendientes y las ejecuta.
void consultarOrdenes() {
  HTTPClient http;
  if (!iniciarPedido(http, "/dashboard/api/commands")) return;

  // Hace la consulta.
  int codigo = http.GET();
  if (codigo != 200) {
    avisarErrorHttp("Consultando ordenes", codigo);
    http.end();
    return;
  }

  // Convierte la respuesta (JSON) en datos.
  JsonDocument doc;
  DeserializationError error = deserializeJson(doc, http.getString());
  http.end();
  if (error) {
    Serial.printf("Respuesta de ordenes invalida: %s\n", error.c_str());
    return;
  }

  // Recorre las ordenes: la unica que se conoce es alimentar.
  for (JsonObject orden : doc["comandos"].as<JsonArray>()) {
    long id = orden["id"] | 0L;
    const char* accion = orden["accion"] | "";
    if (id <= 0) continue;

    if (strcmp(accion, "alimentar") == 0) {
      alimentar(id, orden["gramos"] | 0.0f);
    } else {
      confirmarOrden(id, false, "Accion no soportada");
    }
  }
}

// ---------------------------------------------------------------- Temperatura

// Envia una temperatura a la pagina.
void enviarTemperatura(float t) {
  HTTPClient http;
  if (!iniciarPedido(http, "/dashboard/api/data")) return;

  // Arma el mensaje con la temperatura redondeada a dos decimales.
  JsonDocument body;
  body["temperatura"] = roundf(t * 100) / 100.0f;
  String payload;
  serializeJson(body, payload);

  // Lo envia y revisa la respuesta.
  int codigo = http.POST(payload);
  if (codigo == 201) {
    Serial.printf("Temperatura %.2f C enviada a la pagina\n", t);
  } else {
    avisarErrorHttp("Enviando temperatura", codigo);
  }
  http.end();
}

// Lectura no bloqueante: pide la conversion y la lee ~800 ms despues.
void actualizarTemperatura() {
  unsigned long ahora = millis();

  // Cada 5 segundos le pide al sensor que mida.
  if (!convirtiendo && ahora - ultimoPedidoTemp >= INTERVALO_TEMP_MS) {
    sensorTemperatura.requestTemperatures();
    ultimoPedidoTemp = ahora;
    convirtiendo = true;
  }

  // Cuando la medicion esta lista, la lee y la envia (si es valida).
  if (convirtiendo && ahora - ultimoPedidoTemp >= MS_CONVERSION) {
    convirtiendo = false;
    float t = sensorTemperatura.getTempCByIndex(0);
    if (t == DEVICE_DISCONNECTED_C || t < -20 || t > 80) {
      Serial.println("Sensor DS18B20 sin lectura (revisar cables y resistencia de 4.7k)");
    } else {
      enviarTemperatura(t);
    }
  }
}

// ---------------------------------------------------------------- Programa

// Se ejecuta una vez al encender: prepara el sensor, el servo y el WiFi.
void setup() {
  // Monitor serie, para ver los mensajes desde la PC.
  Serial.begin(115200);
  delay(300);
  Serial.println("AquaControl ESP32 (WiFi) - DS18B20 en GPIO18, servo en GPIO19");

  // Sensor de temperatura (sin frenar el programa mientras mide).
  sensorTemperatura.begin();
  sensorTemperatura.setWaitForConversion(false);

  // Servo: arranca con la compuerta cerrada.
  alimentador.setPeriodHertz(50);
  alimentador.attach(PIN_SERVO, 500, 2400);
  alimentador.write(SERVO_CERRADO);

  // WiFi: se conecta a un router y vuelve a conectarse si se corta.
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.onEvent(alEventoWifi);
  buscarRed();   // el escaneo enciende la radio; recien ahi la MAC es valida
  Serial.printf("MAC del ESP32: %s (por si el router filtra equipos por MAC)\n", WiFi.macAddress().c_str());
  wifiListo();
}

// Se repite sin parar: con WiFi, manda la temperatura y consulta las ordenes.
void loop() {
  if (wifiListo()) {
    actualizarTemperatura();

    if (millis() - ultimaConsulta >= INTERVALO_ORDENES_MS) {
      ultimaConsulta = millis();
      consultarOrdenes();
    }
  }
  delay(10);
}
