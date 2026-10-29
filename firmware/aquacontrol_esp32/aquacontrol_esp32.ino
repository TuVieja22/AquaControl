/*
  AquaControl - ESP32 por WiFi (sin PC intermedia)

  El ESP32 habla directo con la pagina por el WiFi de la casa:
    - cada 5 s envia temperatura y pH    POST /dashboard/api/data
    - cada 2 s pregunta si alimentar     GET  /dashboard/api/commands
      (boton "Alimentar ahora" y horarios programados)
    - al terminar los giros confirma     POST /dashboard/api/commands/{id}/ack

  Conectarlo sin tocar el codigo:
    1. Su propia red: si no tiene WiFi guardado, el ESP32 prende la red RED_NOMBRE con la
       contrasena RED_CLAVE (las mismas en todos los AquaControl). Asi el QR es siempre el
       mismo: la pagina lo muestra en Dispositivos ("Conectar mi AquaControl") y va en el
       manual. El celular lo escanea y se conecta como a cualquier WiFi.
    2. Al conectarse se abre sola una pagina (portal; si no, http://192.168.4.1): la persona
       elige el WiFi de su casa y escribe la contrasena. El ESP32 se conecta, lo guarda y se
       reinicia.
    3. Vincularse: con internet, pregunta cada 10 s a la pagina    POST /dashboard/api/vincular
       mandando su MAC y una clave secreta propia (token). La pagina lo muestra a quien entra
       a Dispositivos desde la misma red ("Agregar a mi cuenta"); cuando lo agregan, la
       respuesta trae su API key. Nadie tiene que copiar la MAC ni ninguna clave.
  Todo queda en la memoria del ESP32 (Preferences), aunque se desenchufe.

  Vuelve a prender su red para elegir otro WiFi (sin que haga falta tocar la placa):
    - si no encuentra su WiFi durante 1 minuto (se mudo o cambio la clave del router): la
      prende y sigue probando su WiFi; si el WiFi vuelve, se reinicia y la apaga,
    - si en la pagina tocan "Cambiar WiFi" (llega como orden "configurar"): la prende sin
      soltar el WiFi actual; si en 10 minutos nadie la usa, se reinicia y la apaga,
    - para quien lo programa: manteniendo apretado el boton BOOT 5 segundos.
  Si la pagina rechaza su API key 3 veces seguidas (lo desvincularon), la borra y vuelve
  a preguntar si lo agregan (aparece de nuevo en Dispositivos).

  Opcional, para quien lo programa: si en esta carpeta hay un secrets.h (no se sube a GitHub)
  con WIFI_SSID, WIFI_PASSWORD, SERVER_URL y DEVICE_KEY, se usan mientras no haya nada guardado.

  Hardware:
    - DS18B20 (temperatura)    -> GPIO 18 (resistencia pull-up de 4.7k a 3.3V)
    - Servo SG90 (alimentador) -> GPIO 5 (rojo a 5V de una fuente aparte, marron a GND)
    - Sensor de pH (plaquita PH-4502C, alimentada con 5V) -> GPIO 34, con un divisor de dos
      resistencias: Po -> 3k -> GPIO 34 -> 1k -> GND (el ESP32 aguanta 3.3V como maximo; asi
      le llega la cuarta parte). Tiene que ser un pin del 32 al 39: los otros que
      leen voltaje no funcionan con el WiFi prendido.
    - Rele del calefactor      -> GPIO 21 al IN1 del modulo de reles (VCC a 5V, GND a GND); el
      calefactor va a los tornillos COM y NO del rele 1. No usar los pines 0, 2, 12 ni 15:
      son "de arranque" y con el rele conectado el ESP32 puede no arrancar.
    - Nivel de agua (flotante) -> GPIO 17 y GND (el pin solo lee "hay" o "no hay").
    - Boton BOOT de la placa   -> GPIO 0
  Todos los GND juntos (fuente, ESP32 y sensores).
  La red WiFi de la casa tiene que ser de 2.4 GHz (el ESP32 no se conecta a 5 GHz).

  Termostato: el rele prende el calefactor si el agua esta HISTERESIS grados por debajo de
  la temperatura objetivo (la que se elige en la pagina; llega con cada respuesta) y lo
  apaga al pasarla por lo mismo. Sin lectura de temperatura o con el nivel de agua bajo, lo
  apaga por seguridad. Sigue funcionando aunque se corte el WiFi.

  Comandos por el Monitor Serie del Arduino IDE (a 115200, con "Nueva linea"):
    estado -> muestra todos los sensores y el calefactor
    ph     -> muestra el voltaje y el pH que esta midiendo ahora
    cal7   -> con la sonda en liquido de calibracion pH 7: guarda ese voltaje como pH 7
    cal4   -> con la sonda en liquido pH 4: guarda ese voltaje como pH 4
    cal10  -> (en vez de cal4) con la sonda en liquido pH 10
    calagua -> sin liquidos de calibracion: con la sonda en agua de pH conocido (7 si no se
              escribe otro numero, o por ejemplo "calagua 7.4") corrige el pH. Es aproximado
    calreset -> vuelve a los valores de fabrica del pH
    nivel  -> muestra que lee el flotante
    nivelinvertir -> da vuelta la lectura del flotante (si marca "bajo" con el agua bien)
    releprueba -> prende el rele 3 segundos (para ver que funciona)
  La calibracion y el sentido del flotante se guardan en la memoria del ESP32.

  Librerias: OneWire, DallasTemperature, ESP32Servo, ArduinoJson (v7). WebServer, DNSServer
  y Preferences vienen con la placa ESP32.
  Placa: "ESP32 Dev Module", con Partition Scheme "Huge APP" (firmware\cargar_firmware.ps1
  ya lo elige solo).
*/

// Librerias: WiFi, pedidos HTTP (y HTTPS), JSON, sensor de temperatura y servo (el pH se lee directo del pin).
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <ESP32Servo.h>
// Su propia red: pagina web, portal que se abre solo y memoria permanente.
#include <WebServer.h>
#include <DNSServer.h>
#include <Preferences.h>

// Datos de fabrica opcionales (ver arriba).
#if __has_include("secrets.h")
#include "secrets.h"
#endif
#ifndef WIFI_SSID
#define WIFI_SSID ""
#endif
#ifndef WIFI_PASSWORD
#define WIFI_PASSWORD ""
#endif
#ifndef SERVER_URL
// Direccion de la pagina que trae de fabrica (se puede cambiar en "Avanzado" del portal).
// Para vender el producto tiene que ser la direccion publica de la pagina (hosting).
#define SERVER_URL "http://192.168.1.195:8080"
#endif
#ifndef DEVICE_KEY
#define DEVICE_KEY ""
#endif

// ---------- Su propia red (la misma en todos: asi el QR de la pagina y del manual sirve siempre) ----------
// Si se cambian, cambiar tambien app/Config/RedAquaControl.php y la imagen del QR (public/img/qr-red-aquacontrol.png).
const char* RED_NOMBRE = "AquaControl";
const char* RED_CLAVE = "aquacontrol";

// ---------- Pines ----------
#define PIN_DS18B20 18
#define PIN_SERVO   5
#define PIN_PH      34   // senal del pH, despues del divisor de resistencias
#define PIN_RELE    21   // rele del calefactor (IN1 del modulo)
#define PIN_NIVEL   17   // flotante del nivel de agua (entre el pin y GND)
#define PIN_BOTON   0    // boton BOOT de la placa

// ---------- Sensor de pH ----------
const float PH_DIVISOR = 4.0;            // divisor 3k (arriba) y 1k (abajo): deja pasar la cuarta parte
const int PH_MUESTRAS = 40;              // lecturas que se promedian (la sonda es "ruidosa")
// Voltajes de fabrica (de la plaquita, antes del divisor) para pH 7 y pH 4; se reemplazan al calibrar.
const float PH_V7_FABRICA = 2.50;
const float PH_V4_FABRICA = 3.03;

// ---------- Calefactor y nivel de agua ----------
const bool RELE_ACTIVO_EN_BAJO = true;   // la mayoria de los modulos de rele se prenden con LOW
const float HISTERESIS = 0.3;            // grados alrededor del objetivo (evita prender y apagar seguido)
const float TEMP_OBJETIVO_FABRICA = 25.5; // hasta que la pagina mande la suya

// ---------- Alimentador: tantos giros (ida y vuelta) como diga la orden ----------
const int SERVO_CERRADO = 0;    // grados con la compuerta cerrada
const int SERVO_ABIERTO = 180;  // grados con la compuerta abierta (el SG90 llega hasta 180)
const int MS_ABIERTO = 1000;    // tiempo abierto antes de volver
// Cerrada un rato largo antes del giro siguiente: encadenando los giros muy seguidos la
// compuerta se traba. Si se cambia, hay que mover tambien Config\Feeding::$segundosPorGiro.
const int MS_CERRADO = 1500;    // tiempo cerrado antes del giro siguiente
// Giros como maximo en una orden (igual que ComandoDispositivoModel::MAX_GIROS): una
// racion mal cargada no tiene que poder vaciar el tacho dentro de la pecera.
const int MAX_GIROS = 200;

// ---------- Tiempos ----------
const unsigned long INTERVALO_TEMP_MS = 5000;      // envio de temperatura y pH
const unsigned long INTERVALO_ORDENES_MS = 2000;   // consulta de ordenes
const unsigned long INTERVALO_VINCULAR_MS = 10000; // pregunta si ya lo agregaron a una cuenta
const unsigned long MS_CONVERSION = 800;           // DS18B20 a 12 bits tarda ~750 ms
const uint16_t HTTP_TIMEOUT_MS = 4000;
const unsigned long MS_BOTON_CONFIG = 5000;        // BOOT apretado para prender su red
const unsigned long MS_PRUEBA_WIFI = 25000;        // tiempo para conectarse al WiFi elegido en el portal
const unsigned long MS_RED_PEDIDA = 600000;        // 10 min con su red pedida sin que nadie la use: se apaga
const int INTENTOS_ANTES_DE_RED = 3;               // intentos de WiFi (de 20 s) antes de prender su red
const int RECHAZOS_ANTES_DE_DESVINCULAR = 3;       // respuestas 401 seguidas antes de borrar la API key

// Objetos que manejan el sensor de temperatura y el servo.
OneWire oneWire(PIN_DS18B20);
DallasTemperature sensorTemperatura(&oneWire);
Servo alimentador;

// Memoria permanente, conexiones de red (la segura es para paginas https), portal y DNS.
Preferences memoria;
WiFiClient clienteNormal;
WiFiClientSecure clienteSeguro;
WebServer servidorWeb(80);
DNSServer dns;

// Configuracion guardada en la memoria: WiFi de la casa, direccion de la pagina y API key.
String cfgSsid, cfgClave, cfgServidor, cfgApiKey;
// Propios de este ESP32: clave secreta para vincularse y su MAC.
String token, mac;
// Calibracion del pH: voltaje de la plaquita en pH 7 y en el segundo liquido (pH 4 o 10).
float phV7 = PH_V7_FABRICA, phV2 = PH_V4_FABRICA, phPunto2 = 4.0;
// Termostato: temperatura objetivo (la manda la pagina) y si el calefactor esta prendido.
float tempObjetivo = TEMP_OBJETIVO_FABRICA;
bool calefactorPrendido = false;
// Si el flotante lee al reves (se da vuelta con el comando "nivelinvertir").
bool nivelInvertido = false;
// Lo que se va escribiendo en el Monitor Serie (comandos de calibracion).
String lineaSerie;

// Momento en que se hizo por ultima vez cada tarea (en milisegundos).
unsigned long ultimoPedidoTemp = 0;
unsigned long ultimaConsulta = 0;
unsigned long ultimoIntentoWifi = 0;
unsigned long ultimoVincular = 0;
unsigned long botonDesde = 0;
// Estado: sensor midiendo, aviso de conexion pendiente y ultimo motivo de desconexion.
bool convirtiendo = false;
bool avisoWifi = false;
volatile uint8_t motivoDesconexion = 0;
// Intentos de WiFi fallidos y rechazos de la API key seguidos.
int intentosWifi = 0;
int rechazosSeguidos = 0;

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

// ---------- Su propia red (portal para elegir el WiFi de la casa) ----------
// Por que se prendio: sin WiFi guardado, sin WiFi (no encuentra su red) o pedido ("Cambiar
// WiFi" de la pagina o BOOT). Cada uno se apaga distinto.
enum MotivoRed { SIN_DATOS, SIN_WIFI, PEDIDO };
// Pasos del portal: mostrando el formulario, probando la red elegida, listo o con error.
enum PasoPortal { PASO_FORMULARIO, PASO_CONECTANDO, PASO_LISTO, PASO_ERROR };
bool redPrendida = false;
bool pedirRed = false;               // se pide desde adentro de un pedido HTTP y se atiende en loop()
bool servidorIniciado = false;
MotivoRed motivoPedido = PEDIDO;
MotivoRed motivoRed = PEDIDO;
PasoPortal paso = PASO_FORMULARIO;
String mensajePaso;
unsigned long inicioRed = 0;
unsigned long inicioPaso = 0;
// Lo que escribio la persona en el portal (se guarda recien si el WiFi conecta).
String nuevaSsid, nuevaClave, nuevoServidor;

// Redes WiFi encontradas para la lista del portal (sin repetir nombres).
const int MAX_REDES = 20;
String redesSsid[MAX_REDES];
int32_t redesSenal[MAX_REDES];
int32_t redesCanal[MAX_REDES];
uint8_t redesBssid[MAX_REDES][6];
int totalRedes = 0;

// Pide prender su red (se prende en loop(), fuera de los pedidos HTTP).
void pedirSuRed(MotivoRed motivo) {
  pedirRed = true;
  motivoPedido = motivo;
}

// ---------------------------------------------------------------- Memoria

// Lee la configuracion guardada (si no hay nada, usa los datos de fabrica de secrets.h).
// La primera vez crea el token (clave secreta para vincularse) y lo guarda para siempre.
void leerConfiguracion() {
  memoria.begin("aquacontrol", false);
  cfgSsid = memoria.getString("ssid", WIFI_SSID);
  cfgClave = memoria.getString("clave", WIFI_PASSWORD);
  cfgServidor = memoria.getString("servidor", SERVER_URL);
  cfgApiKey = memoria.getString("apikey", DEVICE_KEY);
  phV7 = memoria.getFloat("ph_v7", PH_V7_FABRICA);
  phV2 = memoria.getFloat("ph_v2", PH_V4_FABRICA);
  phPunto2 = memoria.getFloat("ph_p2", 4.0);
  tempObjetivo = memoria.getFloat("temp_obj", TEMP_OBJETIVO_FABRICA);
  nivelInvertido = memoria.getBool("nivel_inv", false);
  token = memoria.getString("token", "");
  if (token.length() != 32) {
    token = "";
    for (int i = 0; i < 4; i++) {
      char parte[9];
      sprintf(parte, "%08x", (unsigned) esp_random());
      token += parte;
    }
    memoria.putString("token", token);
  }
  memoria.end();
}

// Guarda la configuracion actual.
void guardarConfiguracion() {
  memoria.begin("aquacontrol", false);
  memoria.putString("ssid", cfgSsid);
  memoria.putString("clave", cfgClave);
  memoria.putString("servidor", cfgServidor);
  memoria.putString("apikey", cfgApiKey);
  memoria.end();
}

// Arma la MAC (12 caracteres) con la MAC de fabrica.
void armarMac() {
  uint64_t numero = ESP.getEfuseMac();
  char texto[13];
  for (int i = 0; i < 6; i++) {
    sprintf(texto + i * 2, "%02X", (unsigned) ((numero >> (8 * i)) & 0xFF));
  }
  mac = String(texto);
}

// ---------------------------------------------------------------- WiFi

// Guarda el motivo que informa el router cuando rechaza o corta la conexion.
void alEventoWifi(WiFiEvent_t evento, WiFiEventInfo_t info) {
  if (evento == ARDUINO_EVENT_WIFI_STA_DISCONNECTED) {
    motivoDesconexion = info.wifi_sta_disconnected.reason;
  }
}

// Traduce el codigo de desconexion del router a un texto entendible (va al monitor y al celular).
const char* explicarMotivo(uint8_t motivo) {
  switch (motivo) {
    case 0:   return "el router no respondió";
    case 2:   case 14: case 15: case 202: case 204:
              return "la contraseña del WiFi es incorrecta (respeta mayúsculas y minúsculas)";
    case 4:   return "el router no respondió a la conexión (si sigue, revisa si el router "
                     "filtra equipos por MAC o tiene límite de equipos)";
    case 201: case 210: case 211:
              return "no encuentra la red (¿nombre mal escrito, red de 5 GHz o muy lejos?)";
    case 212: return "la señal es demasiado débil (acerca el AquaControl al router)";
    case 200: return "se perdió la señal del router";
    case 203: case 205:
              return "el router rechazó la conexión (¿filtro MAC o demasiados equipos?)";
    default:  return "motivo no identificado";
  }
}

// Busca una red por nombre entre las visibles y guarda su canal y su router (el de mejor senal).
bool buscarRed(const String& nombre) {
  hayBssid = false;
  int total = WiFi.scanNetworks();
  int mejorSenal = -1000;
  for (int i = 0; i < total; i++) {
    if (WiFi.SSID(i) == nombre && WiFi.RSSI(i) > mejorSenal) {
      mejorSenal = WiFi.RSSI(i);
      canalRed = WiFi.channel(i);
      memcpy(bssidRed, WiFi.BSSID(i), 6);
      hayBssid = true;
    }
  }
  WiFi.scanDelete();
  if (hayBssid) {
    Serial.printf("Red \"%s\" encontrada: senal %d dBm, canal %d\n", nombre.c_str(), mejorSenal, canalRed);
  } else {
    Serial.printf("La red \"%s\" NO aparece entre las %d redes de 2.4 GHz visibles.\n", nombre.c_str(), total);
  }
  return hayBssid;
}

// Empieza a conectarse a una red (directo al router si aparecio en la busqueda).
void empezarConexion(const String& ssid, const String& clave) {
  motivoDesconexion = 0;
  WiFi.disconnect();
  WiFi.setSleep(false);   // sin ahorro de energia: algunos routers cortan al ESP32 si "duerme"
  WiFi.setTxPower(POTENCIAS[nivelPotencia]);
  if (hayBssid) {
    WiFi.begin(ssid.c_str(), clave.c_str(), canalRed, bssidRed);
  } else {
    WiFi.begin(ssid.c_str(), clave.c_str());
  }
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
    intentosWifi = 0;
    return true;
  }

  // Con alguien conectado a su red no se prueba: cada intento cambia de canal y corta al celular.
  if (redPrendida && WiFi.softAPgetStationNum() > 0) return false;

  // Cada intento tiene 20 s para completarse antes de reintentar (sin bloquear el programa).
  if (ultimoIntentoWifi == 0 || millis() - ultimoIntentoWifi >= 20000) {
    if (ultimoIntentoWifi != 0) {
      Serial.printf("No se pudo conectar: %s (codigo %u)\n",
                    explicarMotivo(motivoDesconexion), motivoDesconexion);
      // Un minuto sin suerte: se prende su red (por si se mudo o cambio la clave), y se sigue probando.
      if (++intentosWifi == INTENTOS_ANTES_DE_RED && !redPrendida) {
        pedirSuRed(SIN_WIFI);
      }
      // El router no contesta: el siguiente intento va con menos potencia (y despues vuelve a empezar).
      nivelPotencia = (nivelPotencia + 1) % 4;
    }
    ultimoIntentoWifi = millis();
    Serial.printf("Conectando a \"%s\" (clave de %u caracteres, potencia %s)...\n",
                  cfgSsid.c_str(), cfgClave.length(), NOMBRES_POTENCIA[nivelPotencia]);
    empezarConexion(cfgSsid, cfgClave);
    avisoWifi = true;
  }
  return false;
}

// ---------------------------------------------------------------- HTTP

// Empieza un pedido a la pagina (cifrado si la direccion es https; sin revisar el
// certificado, porque no hay donde guardarlo).
bool abrirPedido(HTTPClient& http, const String& ruta) {
  String url = cfgServidor + ruta;
  bool ok = url.startsWith("https://") ? http.begin(clienteSeguro, url) : http.begin(clienteNormal, url);
  if (!ok) return false;
  http.setTimeout(HTTP_TIMEOUT_MS);
  http.setConnectTimeout(HTTP_TIMEOUT_MS);
  http.addHeader("Content-Type", "application/json");
  return true;
}

// Prepara un pedido con la API key (para todo lo que no es vincularse).
bool iniciarPedido(HTTPClient& http, const String& ruta) {
  if (!abrirPedido(http, ruta)) return false;
  http.addHeader("X-Device-Key", cfgApiKey);
  return true;
}

// La pagina contesto bien: se olvidan los rechazos anteriores.
void pedidoAceptado() {
  rechazosSeguidos = 0;
}

// Explica por el monitor serie por que fallo un pedido.
void avisarErrorHttp(const char* que, int codigo) {
  if (codigo == 401) {
    Serial.printf("%s: la pagina rechazo la API key (401).\n", que);
    // Varios rechazos seguidos: lo desvincularon. Se borra la key y vuelve a preguntar si lo agregan.
    if (++rechazosSeguidos >= RECHAZOS_ANTES_DE_DESVINCULAR) {
      Serial.println("Este AquaControl ya no esta vinculado: aparece de nuevo en Dispositivos para agregarlo.");
      cfgApiKey = "";
      rechazosSeguidos = 0;
      guardarConfiguracion();
    }
  } else if (codigo < 0) {
    Serial.printf("%s: no se pudo conectar con %s (%s). La pagina esta prendida?\n",
                  que, cfgServidor.c_str(), HTTPClient::errorToString(codigo).c_str());
  } else {
    Serial.printf("%s: respuesta HTTP %d\n", que, codigo);
  }
}

// Pregunta si ya lo agregaron a una cuenta (manda su MAC y su token). Si si, guarda la API key.
void vincularse() {
  HTTPClient http;
  if (!abrirPedido(http, "/dashboard/api/vincular")) return;

  JsonDocument body;
  body["mac"] = mac;
  body["token"] = token;
  String payload;
  serializeJson(body, payload);
  int codigo = http.POST(payload);

  if (codigo == 201) {
    JsonDocument respuesta;
    if (!deserializeJson(respuesta, http.getString()) && respuesta["api_key"].is<const char*>()) {
      cfgApiKey = respuesta["api_key"].as<String>();
      guardarConfiguracion();
      Serial.printf("Vinculado a una cuenta como \"%s\". Ya manda datos.\n", (const char*) (respuesta["dispositivo"]["nombre"] | "AquaControl"));
    }
  } else if (codigo == 202) {
    Serial.printf("Esperando que lo agreguen: en la pagina, Dispositivos -> \"Agregar a mi cuenta\" (MAC %s).\n", mac.c_str());
  } else if (codigo == 403) {
    Serial.println("La pagina dice que este AquaControl ya se agrego desde otro aparato con su MAC.");
  } else {
    avisarErrorHttp("Vinculando", codigo);
  }
  http.end();
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
    pedidoAceptado();
    Serial.printf("Orden #%ld confirmada a la pagina (%s).\n", id, ok ? "ejecutada" : "fallida");
  } else {
    avisarErrorHttp("Confirmando orden", codigo);
  }
  http.end();
}

// Reparte la racion en varios giros: cada giro deja caer lo que entra en la muesca del
// dispensador, asi que la cantidad de giros es la que decide cuanta comida sale. La cuenta
// la hace la pagina (configuracion_pecera.gramos_por_giro) y aca solo se repite.
void alimentar(long id, float gramos, int giros) {
  if (giros < 1) giros = 1;
  if (giros > MAX_GIROS) giros = MAX_GIROS;
  Serial.printf("Orden #%ld: alimentar %.2f g -> %d giros\n", id, gramos, giros);

  // Abre y cierra la compuerta tantas veces como haga falta.
  for (int i = 0; i < giros; i++) {
    alimentador.write(SERVO_ABIERTO);
    delay(MS_ABIERTO);
    alimentador.write(SERVO_CERRADO);
    delay(MS_CERRADO);
  }

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
  pedidoAceptado();

  // Convierte la respuesta (JSON) en datos.
  JsonDocument doc;
  DeserializationError error = deserializeJson(doc, http.getString());
  http.end();
  if (error) {
    Serial.printf("Respuesta de ordenes invalida: %s\n", error.c_str());
    return;
  }

  // Recorre las ordenes: alimentar, o prender su red (boton "Cambiar WiFi" de la pagina).
  for (JsonObject orden : doc["comandos"].as<JsonArray>()) {
    long id = orden["id"] | 0L;
    const char* accion = orden["accion"] | "";
    if (id <= 0) continue;

    if (strcmp(accion, "alimentar") == 0) {
      // Si la pagina es vieja y no manda los giros, se hace uno solo (como antes).
      alimentar(id, orden["gramos"] | 0.0f, orden["giros"] | 1);
    } else if (strcmp(accion, "configurar") == 0) {
      confirmarOrden(id, true, nullptr);
      if (!redPrendida) pedirSuRed(PEDIDO);
    } else {
      confirmarOrden(id, false, "Accion no soportada");
    }
  }
}

// ---------------------------------------------------------------- Sensores (temperatura, pH, nivel) y calefactor

// Voltaje de la plaquita de pH (pin Po), promediando varias lecturas. Devuelve -1 si no hay
// sonda o la lectura salta demasiado (cable suelto: el pin queda "al aire").
float voltajePh() {
  uint32_t suma = 0, minimo = 100000, maximo = 0;
  for (int i = 0; i < PH_MUESTRAS; i++) {
    uint32_t mv = analogReadMilliVolts(PIN_PH);
    suma += mv;
    if (mv < minimo) minimo = mv;
    if (mv > maximo) maximo = mv;
    delay(2);
  }
  // Lo que llega al pin es la cuarta parte (divisor): se multiplica para saber lo que mando la plaquita.
  float voltios = (suma / (float) PH_MUESTRAS) / 1000.0f * PH_DIVISOR;
  bool salta = (maximo - minimo) / 1000.0f * PH_DIVISOR > 0.5f;
  if (voltios < 0.2f || voltios > 4.8f || salta) return -1;
  return voltios;
}

// Convierte el voltaje en pH con la recta de la calibracion (pasa por pH 7 y por el segundo liquido).
float calcularPh(float voltios) {
  if (fabsf(phV2 - phV7) < 0.01f) return -1;   // calibracion invalida (los dos puntos iguales)
  return 7.0f + (voltios - phV7) * (phPunto2 - 7.0f) / (phV2 - phV7);
}

// Si el nivel de agua esta bien: el flotante cierra contra GND (LOW) con el agua en su lugar.
// Si estuviera montado al reves, se da vuelta con el comando "nivelinvertir".
bool nivelOk() {
  bool cerrado = digitalRead(PIN_NIVEL) == LOW;
  return nivelInvertido ? !cerrado : cerrado;
}

// Prende o apaga el rele del calefactor (los modulos de rele comunes se prenden con LOW).
void ponerCalefactor(bool prender) {
  if (prender != calefactorPrendido) {
    Serial.printf("Calefactor %s\n", prender ? "PRENDIDO" : "apagado");
  }
  calefactorPrendido = prender;
  digitalWrite(PIN_RELE, (prender != RELE_ACTIVO_EN_BAJO) ? HIGH : LOW);
}

// Termostato: prende abajo del objetivo, apaga arriba (con margen). Sin temperatura o con el
// nivel de agua bajo (el calefactor podria quedar fuera del agua), apaga.
void controlarCalefactor(float t, bool hayTemperatura, bool hayAgua) {
  if (!hayTemperatura || !hayAgua) {
    ponerCalefactor(false);
  } else if (t < tempObjetivo - HISTERESIS) {
    ponerCalefactor(true);
  } else if (t > tempObjetivo + HISTERESIS) {
    ponerCalefactor(false);
  }
}

// Manda una lectura a la pagina: temperatura y pH (los que esten bien), nivel de agua y
// calefactor. La respuesta trae la temperatura objetivo, que se guarda para el termostato.
void enviarLectura(float t, bool hayTemperatura, float ph, bool hayPh) {
  HTTPClient http;
  if (!iniciarPedido(http, "/dashboard/api/data")) return;

  // Arma el mensaje con dos decimales.
  JsonDocument body;
  if (hayTemperatura) body["temperatura"] = roundf(t * 100) / 100.0f;
  if (hayPh) body["ph"] = roundf(ph * 100) / 100.0f;
  body["nivel_agua"] = nivelOk() ? 1 : 0;
  body["calefactor"] = calefactorPrendido ? 1 : 0;
  String payload;
  serializeJson(body, payload);

  // Lo envia y revisa la respuesta.
  int codigo = http.POST(payload);
  if (codigo == 201) {
    pedidoAceptado();
    Serial.printf("Lectura enviada a la pagina: %s\n", payload.c_str());
    // Temperatura objetivo elegida en la pagina (si cambio, se guarda para cuando no haya WiFi).
    JsonDocument respuesta;
    if (!deserializeJson(respuesta, http.getString())) {
      float objetivo = respuesta["config"]["temp_objetivo"] | tempObjetivo;
      if (objetivo >= 15 && objetivo <= 35 && fabsf(objetivo - tempObjetivo) > 0.01f) {
        tempObjetivo = objetivo;
        memoria.begin("aquacontrol", false);
        memoria.putFloat("temp_obj", tempObjetivo);
        memoria.end();
        Serial.printf("Temperatura objetivo nueva: %.2f C\n", tempObjetivo);
      }
    }
  } else {
    avisarErrorHttp("Enviando lectura", codigo);
  }
  http.end();
}

// Lectura no bloqueante: cada 5 s le pide al DS18B20 que mida; ~800 ms despues lee todos
// los sensores, maneja el calefactor y (si puede) manda la lectura a la pagina. Se llama
// siempre, aunque no haya WiFi: asi el termostato sigue andando.
void actualizarSensores(bool puedeEnviar) {
  unsigned long ahora = millis();

  // Cada 5 segundos le pide al sensor de temperatura que mida.
  if (!convirtiendo && ahora - ultimoPedidoTemp >= INTERVALO_TEMP_MS) {
    sensorTemperatura.requestTemperatures();
    ultimoPedidoTemp = ahora;
    convirtiendo = true;
  }

  // Cuando la medicion esta lista, lee los sensores, maneja el calefactor y manda todo.
  if (convirtiendo && ahora - ultimoPedidoTemp >= MS_CONVERSION) {
    convirtiendo = false;
    float t = sensorTemperatura.getTempCByIndex(0);
    bool hayTemperatura = !(t == DEVICE_DISCONNECTED_C || t < -20 || t > 80);
    // -127: no contesta (cable o resistencia de 4.7k). 85: se reinicio por falta de corriente.
    if (!hayTemperatura) {
      Serial.printf("Sensor DS18B20 sin lectura (%.0f): %s\n", t,
                    t == DEVICE_DISCONNECTED_C ? "no contesta, revisar cables y resistencia de 4.7k"
                    : t == 85 ? "se reinicio por falta de corriente (fuente debil o el servo)"
                    : "valor imposible, revisar cables");
    }

    float voltios = voltajePh();
    float ph = voltios < 0 ? -1 : calcularPh(voltios);
    bool hayPh = ph >= 0 && ph <= 14;
    if (!hayPh) Serial.println("Sensor de pH sin lectura (revisar sonda, plaquita y divisor en GPIO 34)");

    bool hayAgua = nivelOk();
    if (!hayAgua) Serial.println("Nivel de agua BAJO: calefactor apagado por seguridad");

    controlarCalefactor(t, hayTemperatura, hayAgua);
    if (puedeEnviar) enviarLectura(t, hayTemperatura, ph, hayPh);
  }
}

// Guarda la calibracion del pH en la memoria.
void guardarCalibracionPh() {
  memoria.begin("aquacontrol", false);
  memoria.putFloat("ph_v7", phV7);
  memoria.putFloat("ph_v2", phV2);
  memoria.putFloat("ph_p2", phPunto2);
  memoria.end();
}

// Muestra la calibracion actual y cuantos milivoltios cambia por cada punto de pH.
void mostrarCalibracionPh() {
  float mvPorPh = (phV2 - phV7) / (phPunto2 - 7.0f) * 1000.0f;
  Serial.printf("Calibracion: pH 7 = %.3f V, pH %.0f = %.3f V -> %.0f mV por punto de pH\n",
                phV7, phPunto2, phV2, mvPorPh);
  // Una sonda sana cambia unos 160-180 mV por punto (bajando cuando sube el pH).
  if (fabsf(mvPorPh) < 100 || fabsf(mvPorPh) > 250) {
    Serial.println("  OJO: ese valor es raro. Revisa que la sonda este en el liquido correcto y vuelve a calibrar.");
  }
}

// Atiende los comandos del Monitor Serie (estado, pH, calibracion, nivel y prueba del rele).
void atenderSerie() {
  while (Serial.available()) {
    char c = Serial.read();
    if (c != '\n' && c != '\r') {
      if (lineaSerie.length() < 20) lineaSerie += c;
      continue;
    }
    String comando = lineaSerie;
    lineaSerie = "";
    comando.trim();
    comando.toLowerCase();
    if (comando.length() == 0) continue;

    // Lo que mide ahora (se usa para ver cuando la lectura se quedo quieta antes de calibrar).
    float voltios = voltajePh();
    if (comando == "ph") {
      if (voltios < 0) {
        Serial.println("pH: sin lectura (revisar sonda, plaquita y divisor en GPIO 34)");
      } else {
        Serial.printf("pH: plaquita %.3f V (al pin llegan %.3f V) -> pH %.2f\n", voltios, voltios / PH_DIVISOR, calcularPh(voltios));
      }
    } else if (comando == "cal7" || comando == "cal4" || comando == "cal10") {
      if (voltios < 0) {
        Serial.println("No se puede calibrar: el sensor de pH no tiene lectura.");
        continue;
      }
      if (comando == "cal7") {
        phV7 = voltios;
      } else {
        phV2 = voltios;
        phPunto2 = comando == "cal4" ? 4.0f : 10.0f;
      }
      guardarCalibracionPh();
      Serial.printf("Guardado: %.3f V = pH %s\n", voltios, comando.substring(3).c_str());
      mostrarCalibracionPh();
    } else if (comando == "calreset") {
      phV7 = PH_V7_FABRICA;
      phV2 = PH_V4_FABRICA;
      phPunto2 = 4.0f;
      guardarCalibracionPh();
      Serial.println("Calibracion del pH vuelta a los valores de fabrica.");
      mostrarCalibracionPh();
    } else if (comando.startsWith("calagua")) {
      // Sin liquidos de calibracion: un solo punto con agua de pH conocido (7 si no se escribe otro).
      // Corre la recta para que ahora marque ese valor, sin cambiar su inclinacion.
      if (voltios < 0) {
        Serial.println("No se puede calibrar: el sensor de pH no tiene lectura.");
        continue;
      }
      String numero = comando.substring(7);
      numero.trim();
      float valor = numero.length() ? numero.toFloat() : 7.0f;
      if (valor < 4 || valor > 10) {
        Serial.println("Escribi un pH entre 4 y 10, por ejemplo: calagua 7.4");
        continue;
      }
      float corrimiento = voltios - phV7 - (valor - 7.0f) * (phV2 - phV7) / (phPunto2 - 7.0f);
      phV7 += corrimiento;
      phV2 += corrimiento;
      guardarCalibracionPh();
      Serial.printf("Guardado: %.3f V = pH %.2f (calibracion de un punto, aproximada)\n", voltios, valor);
      mostrarCalibracionPh();
    } else if (comando == "nivel") {
      Serial.printf("Nivel de agua: el pin %d esta en %s -> %s%s\n", PIN_NIVEL,
                    digitalRead(PIN_NIVEL) == LOW ? "LOW (flotante cerrado)" : "HIGH (flotante abierto)",
                    nivelOk() ? "OK" : "BAJO", nivelInvertido ? " (lectura invertida)" : "");
    } else if (comando == "nivelinvertir") {
      nivelInvertido = !nivelInvertido;
      memoria.begin("aquacontrol", false);
      memoria.putBool("nivel_inv", nivelInvertido);
      memoria.end();
      Serial.printf("Lectura del flotante %s. Ahora marca: %s\n", nivelInvertido ? "invertida" : "normal", nivelOk() ? "OK" : "BAJO");
    } else if (comando == "releprueba") {
      // Prende el rele 3 segundos y lo deja como estaba (el termostato sigue mandando despues).
      bool antes = calefactorPrendido;
      Serial.println("Prendiendo el rele 3 segundos (deberia escucharse el click)...");
      ponerCalefactor(true);
      delay(3000);
      ponerCalefactor(antes);
    } else if (comando == "estado") {
      sensorTemperatura.requestTemperatures();
      delay(MS_CONVERSION);
      float t = sensorTemperatura.getTempCByIndex(0);
      bool hayTemperatura = !(t == DEVICE_DISCONNECTED_C || t < -20 || t > 80);
      if (hayTemperatura) Serial.printf("Temperatura: %.2f C (objetivo %.2f C)\n", t, tempObjetivo);
      else Serial.println("Temperatura: sin lectura");
      if (voltios < 0) Serial.println("pH: sin lectura");
      else Serial.printf("pH: %.2f (plaquita %.3f V)\n", calcularPh(voltios), voltios);
      Serial.printf("Nivel de agua: %s\n", nivelOk() ? "OK" : "BAJO");
      Serial.printf("Calefactor: %s\n", calefactorPrendido ? "prendido" : "apagado");
      Serial.printf("WiFi: %s  |  %s\n", WiFi.status() == WL_CONNECTED ? WiFi.SSID().c_str() : "sin conexion",
                    cfgApiKey.length() ? "vinculado" : "sin vincular");
    } else {
      Serial.println("Comandos: estado | ph | cal7 | cal4 | cal10 | calagua | calreset | nivel | nivelinvertir | releprueba");
    }
  }
}

// ---------------------------------------------------------------- Portal (su propia red)

// Convierte un texto en HTML seguro (un nombre de red puede tener < o comillas).
String escaparHtml(const String& texto) {
  String salida;
  for (unsigned i = 0; i < texto.length(); i++) {
    char c = texto[i];
    if (c == '&') salida += "&amp;";
    else if (c == '<') salida += "&lt;";
    else if (c == '>') salida += "&gt;";
    else if (c == '"') salida += "&quot;";
    else if (c == '\'') salida += "&#39;";
    else salida += c;
  }
  return salida;
}

// Busca las redes WiFi cercanas para la lista del portal (sin repetir, con la mejor senal).
void escanearRedes() {
  totalRedes = 0;
  int total = WiFi.scanNetworks();
  for (int i = 0; i < total; i++) {
    String nombre = WiFi.SSID(i);
    if (nombre.length() == 0 || nombre == RED_NOMBRE) continue;   // redes ocultas y la propia
    // Si ya estaba en la lista, se queda con la de mejor senal.
    int lugar = -1;
    for (int j = 0; j < totalRedes; j++) {
      if (redesSsid[j] == nombre) lugar = j;
    }
    if (lugar >= 0 && WiFi.RSSI(i) <= redesSenal[lugar]) continue;
    if (lugar < 0) {
      if (totalRedes >= MAX_REDES) continue;
      lugar = totalRedes++;
    }
    redesSsid[lugar] = nombre;
    redesSenal[lugar] = WiFi.RSSI(i);
    redesCanal[lugar] = WiFi.channel(i);
    memcpy(redesBssid[lugar], WiFi.BSSID(i), 6);
  }
  WiFi.scanDelete();
  Serial.printf("Redes WiFi encontradas: %d\n", totalRedes);
}

// Principio y final de las paginas del portal (con su estilo, sin archivos aparte).
String inicioPagina(const char* titulo) {
  String html = F("<!doctype html><html lang=\"es\"><head><meta charset=\"utf-8\">"
                  "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>");
  html += titulo;
  html += F("</title><style>"
            "body{margin:0;background:#070b14;color:#f8fbff;font-family:system-ui,sans-serif;line-height:1.45}"
            "main{max-width:440px;margin:0 auto;padding:24px 16px}"
            "h1{font-size:1.5rem;margin:0 0 6px}p{color:#bccbe2}"
            "label{display:block;margin:16px 0 6px;font-weight:600}"
            "input,select{box-sizing:border-box;width:100%;padding:12px;border-radius:8px;"
            "border:1px solid #2b5d6e;background:#0f172a;color:#f8fbff;font-size:1rem}"
            "button{width:100%;margin-top:22px;padding:14px;border:0;border-radius:8px;"
            "background:#00d4ff;color:#04121c;font-size:1rem;font-weight:700}"
            "small{display:block;margin-top:4px;color:#8fa3bf}"
            "details{margin-top:18px;color:#bccbe2}a{color:#5feaff}"
            ".error{padding:12px;border-radius:8px;background:#3a1416;color:#f7b2b1}"
            ".ok{padding:12px;border-radius:8px;background:#0e3a2d;color:#c9fbe9}"
            "</style></head><body><main>");
  return html;
}

String finPagina() {
  return F("</main></body></html>");
}

// Formulario: red WiFi de la casa, su contrasena y (en Avanzado) la direccion de la pagina.
void mostrarFormulario(const String& error) {
  String html = inicioPagina("Conecta tu AquaControl");
  html += F("<h1>Conecta tu AquaControl a tu WiFi</h1><p>Equipo <strong>AquaControl ");
  html += mac.substring(8);
  html += F("</strong> · MAC ");
  for (int i = 0; i < 12; i += 2) html += mac.substring(i, i + 2) + (i < 10 ? ":" : "");
  html += F("</p>");
  if (error.length()) {
    html += "<p class=\"error\">" + escaparHtml(error) + "</p>";
  }
  html += F("<form method=\"post\" action=\"/guardar\">");

  // Lista de redes encontradas (la guardada aparece elegida).
  html += F("<label for=\"red\">El WiFi de tu casa</label><select id=\"red\" name=\"red\">");
  for (int i = 0; i < totalRedes; i++) {
    html += "<option value=\"" + escaparHtml(redesSsid[i]) + "\"";
    if (redesSsid[i] == cfgSsid) html += " selected";
    html += ">" + escaparHtml(redesSsid[i]) + "</option>";
  }
  html += F("<option value=\"\">Otra (escribe el nombre abajo)</option></select>"
            "<small><a href=\"/?buscar=1\">Buscar redes de nuevo</a></small>"
            "<label for=\"otra\">Nombre de la red (solo si no aparece en la lista)</label>"
            "<input id=\"otra\" name=\"otra\" maxlength=\"32\" autocomplete=\"off\">"
            "<label for=\"clave\">Contraseña del WiFi</label>"
            "<input id=\"clave\" name=\"clave\" type=\"password\" maxlength=\"63\" autocomplete=\"off\">"
            "<small>Tiene que ser una red de 2.4 GHz.</small>");

  // Direccion de la pagina (normalmente no se toca).
  html += F("<details><summary>Avanzado</summary><label for=\"servidor\">Dirección de la página</label>"
            "<input id=\"servidor\" name=\"servidor\" value=\"");
  html += escaparHtml(cfgServidor);
  html += F("\"></details><button type=\"submit\">Conectar</button></form>");
  html += finPagina();
  servidorWeb.send(200, "text/html; charset=utf-8", html);
}

// GET /: el formulario (con ?buscar=1 vuelve a buscar las redes).
void alPedirInicio() {
  if (servidorWeb.hasArg("buscar")) escanearRedes();
  // Si quedo un error de antes, se muestra arriba del formulario.
  String error = paso == PASO_ERROR ? mensajePaso : "";
  if (paso == PASO_ERROR) paso = PASO_FORMULARIO;
  mostrarFormulario(error);
}

// POST /guardar: revisa lo escrito y empieza a probar la conexion (sigue en loop()).
void alGuardar() {
  if (paso == PASO_CONECTANDO || paso == PASO_LISTO) {
    servidorWeb.sendHeader("Location", "/estado-pagina", true);
    servidorWeb.send(303, "text/plain", "");
    return;
  }

  // Lo escrito, sin espacios de mas; la red escrita a mano tiene prioridad sobre la lista.
  String otra = servidorWeb.arg("otra");
  otra.trim();
  nuevaSsid = otra.length() ? otra : servidorWeb.arg("red");
  nuevaClave = servidorWeb.arg("clave");
  nuevoServidor = servidorWeb.arg("servidor");
  nuevoServidor.trim();
  while (nuevoServidor.endsWith("/")) nuevoServidor.remove(nuevoServidor.length() - 1);
  if (nuevoServidor.length() == 0) nuevoServidor = cfgServidor;
  if (!nuevoServidor.startsWith("http://") && !nuevoServidor.startsWith("https://")) nuevoServidor = "http://" + nuevoServidor;

  // Lo minimo: una red.
  if (nuevaSsid.length() == 0) {
    mostrarFormulario("Elige el WiFi de tu casa o escribe su nombre.");
    return;
  }

  // Empieza a conectarse a la red elegida (directo al router, si aparecio en la busqueda).
  Serial.printf("Probando la red \"%s\"...\n", nuevaSsid.c_str());
  paso = PASO_CONECTANDO;
  inicioPaso = millis();
  hayBssid = false;
  for (int i = 0; i < totalRedes; i++) {
    if (redesSsid[i] == nuevaSsid) {
      canalRed = redesCanal[i];
      memcpy(bssidRed, redesBssid[i], 6);
      hayBssid = true;
    }
  }
  nivelPotencia = 0;
  empezarConexion(nuevaSsid, nuevaClave);

  // Pagina de "conectando", que pregunta como va.
  servidorWeb.sendHeader("Location", "/estado-pagina", true);
  servidorWeb.send(303, "text/plain", "");
}

// GET /estado-pagina: muestra como va la conexion y se actualiza sola.
void alPedirPaginaEstado() {
  String html = inicioPagina("Conectando tu AquaControl");
  html += F("<h1 id=\"t\">Conectando…</h1><p id=\"m\">Tu AquaControl se está conectando a tu WiFi. "
            "Esto puede tardar hasta medio minuto.</p><p id=\"v\" hidden><a href=\"/\">Volver al formulario</a></p>"
            "<p><small>Si tu celular se desconecta de la red AquaControl, no pasa nada: vuelve a tu WiFi y "
            "entra a la página de AquaControl, en Dispositivos.</small></p>"
            "<script>"
            "var t=document.getElementById('t'),m=document.getElementById('m'),v=document.getElementById('v');"
            "function p(){fetch('/estado').then(function(r){return r.json()}).then(function(d){"
            "if(d.paso==='listo'){t.textContent='¡Listo!';m.textContent=d.mensaje;m.className='ok';}"
            "else if(d.paso==='error'){t.textContent='No se pudo conectar';m.textContent=d.mensaje;"
            "m.className='error';v.hidden=false;}"
            "else setTimeout(p,1500);}).catch(function(){setTimeout(p,2000);});}"
            "setTimeout(p,1500);"
            "</script>");
  html += finPagina();
  servidorWeb.send(200, "text/html; charset=utf-8", html);
}

// GET /estado: el paso actual en JSON (lo pregunta la pagina de "conectando").
void alPedirEstado() {
  JsonDocument doc;
  const char* nombres[] = { "formulario", "conectando", "listo", "error" };
  doc["paso"] = nombres[paso];
  doc["mensaje"] = mensajePaso;
  String json;
  serializeJson(doc, json);
  servidorWeb.send(200, "application/json; charset=utf-8", json);
}

// Cualquier otra direccion lleva al formulario: asi el celular abre el portal solo.
void alPedirOtra() {
  servidorWeb.sendHeader("Location", "http://" + WiFi.softAPIP().toString() + "/", true);
  servidorWeb.send(302, "text/plain", "");
}

// Prende su red (RED_NOMBRE, igual en todos) con el portal. Si tenia WiFi, lo sigue usando (y si no
// lo tenia, lo sigue probando cuando nadie esta conectado a su red).
void prenderSuRed(MotivoRed motivo) {
  redPrendida = true;
  pedirRed = false;
  motivoRed = motivo;
  paso = PASO_FORMULARIO;
  mensajePaso = "";
  inicioRed = millis();

  // Busca las redes cercanas para la lista (si estaba intentando conectarse, se corta ese intento).
  WiFi.mode(WIFI_AP_STA);
  if (WiFi.status() != WL_CONNECTED) WiFi.disconnect();
  escanearRedes();
  // Su red en 192.168.4.1. Al celular que se conecta se le dice que use al ESP32 como DNS
  // (sin esto, el celular pregunta a otro DNS, no encuentra internet y solo muestra "!").
  IPAddress ip(192, 168, 4, 1);
  WiFi.softAPConfig(ip, ip, IPAddress(255, 255, 255, 0), IPAddress(192, 168, 4, 2), ip);
  WiFi.softAP(RED_NOMBRE, RED_CLAVE);
  delay(200);
  // Ademas se le avisa al celular que esta red tiene un portal (Android 11+ y iPhone lo abren
  // solos con el aviso "Iniciar sesion en la red").
  WiFi.AP.enableDhcpCaptivePortal();

  // DNS que responde a todo con la IP del ESP32: asi el celular abre el portal solo.
  dns.start(53, "*", WiFi.softAPIP());

  // Paginas del portal.
  if (!servidorIniciado) {
    servidorWeb.on("/", HTTP_GET, alPedirInicio);
    servidorWeb.on("/guardar", HTTP_POST, alGuardar);
    servidorWeb.on("/estado-pagina", HTTP_GET, alPedirPaginaEstado);
    servidorWeb.on("/estado", HTTP_GET, alPedirEstado);
    servidorWeb.onNotFound(alPedirOtra);
    servidorWeb.begin();
    servidorIniciado = true;
  }

  const char* porque[] = { "sin WiFi guardado", "no encuentra su WiFi", "lo pidieron" };
  Serial.printf("RED PRENDIDA (%s): escanea el QR de la pagina o conectate a \"%s\" (clave %s) y entra a http://%s\n",
                porque[motivo], RED_NOMBRE, RED_CLAVE, WiFi.softAPIP().toString().c_str());
}

// Atiende el portal: prueba el WiFi elegido y, si conecta, guarda y reinicia.
void seguirPortal() {
  dns.processNextRequest();
  servidorWeb.handleClient();

  if (paso == PASO_CONECTANDO) {
    // Conecto: guarda todo y avisa.
    if (WiFi.status() == WL_CONNECTED) {
      cfgSsid = nuevaSsid;
      cfgClave = nuevaClave;
      cfgServidor = nuevoServidor;
      guardarConfiguracion();
      mensajePaso = "Tu AquaControl quedó conectado a «" + nuevaSsid + "». ";
      if (cfgApiKey.length()) {
        mensajePaso += "Ya puedes volver a tu WiFi.";
      } else {
        mensajePaso += "Último paso: vuelve a tu WiFi, entra a la página de AquaControl, en Dispositivos, "
                       "y toca «Agregar a mi cuenta» en AquaControl " + mac.substring(8) + ".";
      }
      paso = PASO_LISTO;
      inicioPaso = millis();
      Serial.println("WiFi de la casa guardado.");
    } else if (millis() - inicioPaso >= MS_PRUEBA_WIFI) {
      // No conecto: explica el motivo y vuelve a su WiFi de antes (si tenia), desde el primer intento.
      mensajePaso = "No se pudo conectar a «" + nuevaSsid + "»: " + explicarMotivo(motivoDesconexion) + ".";
      paso = PASO_ERROR;
      WiFi.disconnect();
      if (cfgSsid.length()) buscarRed(cfgSsid);
      ultimoIntentoWifi = 0;
      Serial.println(mensajePaso);
    }
  }

  // Listo: unos segundos para que el celular vea el aviso, y se reinicia con lo guardado.
  if (paso == PASO_LISTO && millis() - inicioPaso >= 8000) {
    ESP.restart();
  }

  // Nadie la esta usando: se apaga (reiniciando) segun por que se prendio.
  bool libre = WiFi.softAPgetStationNum() == 0 && paso == PASO_FORMULARIO;
  // Se prendio porque no encontraba su WiFi y el WiFi volvio.
  if (libre && motivoRed == SIN_WIFI && WiFi.status() == WL_CONNECTED) {
    Serial.println("Volvio el WiFi: se apaga su red.");
    ESP.restart();
  }
  // La pidieron y en 10 minutos nadie la uso.
  if (libre && motivoRed == PEDIDO && millis() - inicioRed >= MS_RED_PEDIDA) {
    Serial.println("Nadie uso su red: se apaga.");
    ESP.restart();
  }
}

// Boton BOOT apretado 5 segundos: pide su red (para quien tiene la placa a mano).
void revisarBoton() {
  if (digitalRead(PIN_BOTON) == LOW) {
    if (botonDesde == 0) botonDesde = millis();
    if (millis() - botonDesde >= MS_BOTON_CONFIG) {
      Serial.println("Boton BOOT apretado 5 s: se prende su red para elegir otro WiFi.");
      if (!redPrendida) pedirSuRed(PEDIDO);
      botonDesde = 0;
    }
  } else {
    botonDesde = 0;
  }
}

// ---------------------------------------------------------------- Programa

// Se ejecuta una vez al encender: prepara el sensor, el servo, la configuracion y el WiFi.
void setup() {
  // Lo primero: el calefactor apagado (que no quede prendido mientras arranca).
  pinMode(PIN_RELE, OUTPUT);
  calefactorPrendido = true;
  ponerCalefactor(false);

  // Monitor serie, para ver los mensajes desde la PC.
  Serial.begin(115200);
  delay(300);
  Serial.println("AquaControl ESP32 (WiFi) - temperatura GPIO18, servo GPIO5, pH GPIO34, rele GPIO21, nivel GPIO17");

  // Flotante del nivel de agua (entre el pin y GND: cerrado = LOW).
  pinMode(PIN_NIVEL, INPUT_PULLUP);

  // Sensor de temperatura (sin frenar el programa mientras mide).
  sensorTemperatura.begin();
  sensorTemperatura.setWaitForConversion(false);

  // Servo: arranca con la compuerta cerrada.
  alimentador.setPeriodHertz(50);
  alimentador.attach(PIN_SERVO, 500, 2400);
  alimentador.write(SERVO_CERRADO);

  // Boton BOOT (apretado = LOW), pin del pH (lee de 0 a 3.3V) y conexion https sin revisar certificados.
  pinMode(PIN_BOTON, INPUT_PULLUP);
  analogSetPinAttenuation(PIN_PH, ADC_11db);
  clienteSeguro.setInsecure();

  // Configuracion guardada, MAC y calibracion del pH.
  leerConfiguracion();
  armarMac();
  Serial.printf("MAC: %s  Pagina: %s  %s\n", mac.c_str(), cfgServidor.c_str(),
                cfgApiKey.length() ? "(vinculado)" : "(sin vincular)");
  mostrarCalibracionPh();
  Serial.println("Comandos por el Monitor Serie: estado | ph | cal7 | cal4 | cal10 | calagua | calreset | nivel | nivelinvertir | releprueba");

  // WiFi: la configuracion la maneja este programa (no la memoria propia del WiFi).
  WiFi.persistent(false);
  WiFi.onEvent(alEventoWifi);
  WiFi.mode(WIFI_STA);

  // Sin WiFi guardado: directo a su red.
  if (cfgSsid.length() == 0) {
    prenderSuRed(SIN_DATOS);
    return;
  }

  // Con WiFi guardado: se conecta y vuelve a conectarse si se corta.
  WiFi.setAutoReconnect(true);
  buscarRed(cfgSsid);
  wifiListo();
}

// Se repite sin parar: atiende su red (si esta prendida), los comandos del pH y, con WiFi,
// se vincula o manda la temperatura y el pH y consulta las ordenes.
void loop() {
  if (redPrendida) seguirPortal();
  revisarBoton();
  atenderSerie();

  // Mientras prueba la red elegida en el portal no se usa la guardada.
  bool probando = redPrendida && paso == PASO_CONECTANDO;
  bool conectado = !probando && cfgSsid.length() && wifiListo();

  // Los sensores y el termostato andan siempre; la lectura se manda solo si hay WiFi y API key.
  actualizarSensores(conectado && cfgApiKey.length() > 0);

  if (conectado) {
    if (cfgApiKey.length() == 0) {
      // Sin API key: pregunta cada 10 s si ya lo agregaron a una cuenta.
      if (ultimoVincular == 0 || millis() - ultimoVincular >= INTERVALO_VINCULAR_MS) {
        ultimoVincular = millis();
        vincularse();
      }
    } else {
      if (millis() - ultimaConsulta >= INTERVALO_ORDENES_MS) {
        ultimaConsulta = millis();
        consultarOrdenes();
      }
    }
  }

  // Si algo pidio su red (sin WiFi, "Cambiar WiFi" o el boton), se prende ahora.
  if (pedirRed && !redPrendida) prenderSuRed(motivoPedido);
  delay(redPrendida ? 2 : 10);
}
