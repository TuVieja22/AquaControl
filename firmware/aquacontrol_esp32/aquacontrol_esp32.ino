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

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <ESP32Servo.h>
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

OneWire oneWire(PIN_DS18B20);
DallasTemperature sensorTemperatura(&oneWire);
Servo alimentador;

unsigned long ultimoPedidoTemp = 0;
unsigned long ultimaConsulta = 0;
unsigned long ultimoIntentoWifi = 0;
bool convirtiendo = false;
bool avisoWifi = false;

// ---------------------------------------------------------------- WiFi

bool wifiListo() {
  if (WiFi.status() == WL_CONNECTED) {
    if (avisoWifi) {
      Serial.printf("WiFi conectado. IP del ESP32: %s\n", WiFi.localIP().toString().c_str());
      avisoWifi = false;
    }
    return true;
  }

  // Reintenta cada 10 s sin bloquear el resto del programa.
  if (millis() - ultimoIntentoWifi >= 10000 || ultimoIntentoWifi == 0) {
    ultimoIntentoWifi = millis();
    Serial.printf("Conectando a la red WiFi \"%s\"...\n", WIFI_SSID);
    WiFi.disconnect();
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    avisoWifi = true;
  }
  return false;
}

// ---------------------------------------------------------------- HTTP

bool iniciarPedido(HTTPClient& http, const String& ruta) {
  if (!http.begin(String(SERVER_URL) + ruta)) return false;
  http.setTimeout(HTTP_TIMEOUT_MS);
  http.setConnectTimeout(HTTP_TIMEOUT_MS);
  http.addHeader("X-Device-Key", DEVICE_KEY);
  http.addHeader("Content-Type", "application/json");
  return true;
}

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

void confirmarOrden(long id, bool ok, const char* mensaje) {
  HTTPClient http;
  if (!iniciarPedido(http, "/dashboard/api/commands/" + String(id) + "/ack")) return;

  JsonDocument body;
  body["estado"] = ok ? "ejecutado" : "fallido";
  if (mensaje) body["mensaje"] = mensaje;
  String payload;
  serializeJson(body, payload);

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
  alimentador.write(SERVO_ABIERTO);
  delay(MS_ABIERTO);
  alimentador.write(SERVO_CERRADO);
  delay(500);
  confirmarOrden(id, true, nullptr);
}

void consultarOrdenes() {
  HTTPClient http;
  if (!iniciarPedido(http, "/dashboard/api/commands")) return;

  int codigo = http.GET();
  if (codigo != 200) {
    avisarErrorHttp("Consultando ordenes", codigo);
    http.end();
    return;
  }

  JsonDocument doc;
  DeserializationError error = deserializeJson(doc, http.getString());
  http.end();
  if (error) {
    Serial.printf("Respuesta de ordenes invalida: %s\n", error.c_str());
    return;
  }

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

void enviarTemperatura(float t) {
  HTTPClient http;
  if (!iniciarPedido(http, "/dashboard/api/data")) return;

  JsonDocument body;
  body["temperatura"] = roundf(t * 100) / 100.0f;
  String payload;
  serializeJson(body, payload);

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

  if (!convirtiendo && ahora - ultimoPedidoTemp >= INTERVALO_TEMP_MS) {
    sensorTemperatura.requestTemperatures();
    ultimoPedidoTemp = ahora;
    convirtiendo = true;
  }

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

void setup() {
  Serial.begin(115200);
  delay(300);
  Serial.println("AquaControl ESP32 (WiFi) - DS18B20 en GPIO18, servo en GPIO19");

  sensorTemperatura.begin();
  sensorTemperatura.setWaitForConversion(false);

  alimentador.setPeriodHertz(50);
  alimentador.attach(PIN_SERVO, 500, 2400);
  alimentador.write(SERVO_CERRADO);

  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  wifiListo();
}

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
