/*
  AquaControl - ESP32: alimentador con servo + envio de lecturas

  Librerias (Arduino IDE > Administrar bibliotecas):
    - ESP32Servo  (Kevin Harrington)
    - ArduinoJson (Benoit Blanchon, v7)

  Flujo del alimentador:
    1. Cada POLL_INTERVAL_MS el ESP32 consulta   GET  /dashboard/api/commands
    2. Si llega {"accion":"alimentar","gramos":X} mueve el servo
    3. Confirma el resultado con                 POST /dashboard/api/commands/{id}/ack
       -> el servidor lo registra en el historial de alimentaciones.
  Los horarios programados en la pagina los genera el servidor: llegan por la misma cola.

  La API key se genera en la pagina: Dispositivos > "Generar key".
  El dispositivo debe ser de tipo Actuador, Controlador, Kit IoT u Otro (los "Sensor" no reciben ordenes).
*/

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <ESP32Servo.h>

// ---------- Configuracion ----------
const char* WIFI_SSID     = "RioTel_MEDINA_5847";
const char* WIFI_PASSWORD = "6672503295";

// IP de la PC donde corre `php spark serve --host 0.0.0.0` (no uses localhost: es el propio ESP32).
const char* SERVER_URL = "http://192.168.0.10:8080";
const char* DEVICE_KEY = "aqk_PEGA_AQUI_LA_API_KEY";

const int SERVO_PIN = 19;           // mismo pin que el modo USB
const int SERVO_REPOSO = 0;          // grados con la compuerta cerrada
const int SERVO_ABIERTO = 90;        // grados con la compuerta abierta
const int MS_ABIERTO = 1000;         // tiempo abierto antes de volver

const unsigned long POLL_INTERVAL_MS = 5000;     // consulta de ordenes
const unsigned long READING_INTERVAL_MS = 60000; // envio de lecturas
// -----------------------------------

Servo feederServo;
unsigned long lastPoll = 0;
unsigned long lastReading = 0;

void connectWifi() {
  if (WiFi.status() == WL_CONNECTED) return;

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Conectando WiFi");
  for (int i = 0; i < 40 && WiFi.status() != WL_CONNECTED; i++) {
    delay(500);
    Serial.print(".");
  }
  Serial.println(WiFi.status() == WL_CONNECTED ? " OK" : " sin conexion");
}

bool beginRequest(HTTPClient& http, const String& path) {
  if (!http.begin(String(SERVER_URL) + path)) return false;
  http.addHeader("X-Device-Key", DEVICE_KEY);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(8000);
  return true;
}

// Un solo giro por orden (ida y vuelta), igual que el firmware USB.
bool dispensar(float gramos) {
  Serial.printf("Alimentando (racion de %.2f g): un giro de ida y vuelta\n", gramos);

  feederServo.write(SERVO_ABIERTO);
  delay(MS_ABIERTO);
  feederServo.write(SERVO_REPOSO);
  delay(500);
  return true; // si tenes un sensor de fin de carrera, devolve false cuando falle
}

void confirmarComando(int id, bool ok, const char* mensaje) {
  HTTPClient http;
  if (!beginRequest(http, "/dashboard/api/commands/" + String(id) + "/ack")) return;

  JsonDocument body;
  body["estado"] = ok ? "ejecutado" : "fallido";
  if (mensaje) body["mensaje"] = mensaje;
  String payload;
  serializeJson(body, payload);

  int code = http.POST(payload);
  Serial.printf("ACK comando %d -> HTTP %d\n", id, code);
  http.end();
}

void consultarComandos() {
  HTTPClient http;
  if (!beginRequest(http, "/dashboard/api/commands")) return;

  int code = http.GET();
  if (code != 200) {
    Serial.printf("Consulta de comandos -> HTTP %d\n", code);
    http.end();
    return;
  }

  JsonDocument doc;
  DeserializationError err = deserializeJson(doc, http.getString());
  http.end();
  if (err) return;

  for (JsonObject cmd : doc["comandos"].as<JsonArray>()) {
    int id = cmd["id"];
    const char* accion = cmd["accion"] | "";

    if (strcmp(accion, "alimentar") == 0) {
      bool ok = dispensar(cmd["gramos"] | 1.0f);
      confirmarComando(id, ok, ok ? nullptr : "Fallo el servo");
    } else {
      confirmarComando(id, false, "Accion no soportada");
    }
  }
}

void enviarLectura() {
  HTTPClient http;
  if (!beginRequest(http, "/dashboard/api/data")) return;

  JsonDocument body;
  body["temperatura"] = 25.0; // TODO: reemplazar por la lectura real (DS18B20)
  body["ph"] = 7.0;           // TODO: sensor de pH
  body["nivel_agua"] = 1;     // TODO: sensor de nivel (1 = OK, 0 = bajo)
  body["calefactor"] = 0;
  String payload;
  serializeJson(body, payload);

  int code = http.POST(payload);
  Serial.printf("Lectura enviada -> HTTP %d\n", code);
  http.end();
}

void setup() {
  Serial.begin(115200);
  feederServo.attach(SERVO_PIN);
  feederServo.write(SERVO_REPOSO);
  connectWifi();
}

void loop() {
  connectWifi();
  if (WiFi.status() != WL_CONNECTED) {
    delay(2000);
    return;
  }

  unsigned long now = millis();
  if (now - lastPoll >= POLL_INTERVAL_MS) {
    lastPoll = now;
    consultarComandos();
  }
  if (now - lastReading >= READING_INTERVAL_MS) {
    lastReading = now;
    enviarLectura();
  }
}
