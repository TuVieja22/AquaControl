/*
  AquaControl - ESP32 conectado por USB a la PC

  El ESP32 no usa WiFi: habla por el cable USB con el puente de la PC
  (firmware/puente_usb.ps1), que es quien se comunica con la pagina.

  Hardware:
    - DS18B20 (temperatura)   -> GPIO 18  (con resistencia pull-up de 4.7k a 3.3V)
    - Servo SG90 (alimentador) -> GPIO 19

  Librerias (ya instaladas en el Arduino IDE): OneWire, DallasTemperature, ESP32Servo.
  Placa: "ESP32 Dev Module". Velocidad del puerto: 115200.

  Protocolo por el puerto serie (una linea por mensaje):
    ESP32 -> PC   READY                      al arrancar
                  T:24.56                    temperatura en °C (cada INTERVALO_TEMP_MS)
                  T:ERR                      sensor desconectado
                  ACK:<id>:OK                alimentacion terminada
                  ACK:<id>:ERR:<motivo>      no se pudo alimentar
                  (cualquier otra linea es un log y el puente solo la muestra)
    PC -> ESP32   FEED:<id>:<gramos>         un giro del servo (ida y vuelta)
                  PING                       responde PONG
*/

#include <OneWire.h>
#include <DallasTemperature.h>
#include <ESP32Servo.h>

// ---------- Pines ----------
#define PIN_DS18B20 18
#define PIN_SERVO   19

// ---------- Alimentador ----------
// Cada orden hace UN solo movimiento: ida (abre) y vuelta (cierra).
// La cantidad de comida depende de cuanto tiempo queda abierto.
const int SERVO_CERRADO = 0;    // grados con la compuerta cerrada
const int SERVO_ABIERTO = 90;   // grados con la compuerta abierta
const int MS_ABIERTO = 1000;    // tiempo abierto antes de volver

// ---------- Temperatura ----------
const unsigned long INTERVALO_TEMP_MS = 2000;
const unsigned long MS_CONVERSION = 800; // DS18B20 a 12 bits tarda ~750 ms

OneWire oneWire(PIN_DS18B20);
DallasTemperature sensorTemperatura(&oneWire);
Servo alimentador;

unsigned long ultimoPedidoTemp = 0;
bool convirtiendo = false;
String bufferSerie;

// Un solo ciclo por orden: abre, espera y cierra. Los gramos que manda la pagina
// quedan en el historial, pero no cambian la cantidad de movimientos.
void alimentar(long id, float gramos) {
  Serial.printf("Alimentando (racion de %.2f g): un giro de ida y vuelta...\n", gramos);

  alimentador.write(SERVO_ABIERTO);
  delay(MS_ABIERTO);
  alimentador.write(SERVO_CERRADO);
  delay(500); // deja que llegue a la posicion cerrada antes de confirmar

  Serial.printf("ACK:%ld:OK\n", id);
}

void procesarLinea(String linea) {
  linea.trim();
  if (linea.length() == 0) return;

  if (linea == "PING") {
    Serial.println("PONG");
    return;
  }

  // FEED:<id>:<gramos>
  if (linea.startsWith("FEED:")) {
    int sep = linea.indexOf(':', 5);
    if (sep < 0) {
      Serial.println("ERROR orden FEED mal formada");
      return;
    }
    long id = linea.substring(5, sep).toInt();
    float gramos = linea.substring(sep + 1).toFloat();
    alimentar(id, gramos);
    return;
  }

  Serial.print("Orden desconocida: ");
  Serial.println(linea);
}

void leerOrdenes() {
  while (Serial.available()) {
    char c = (char) Serial.read();
    if (c == '\n') {
      procesarLinea(bufferSerie);
      bufferSerie = "";
    } else if (c != '\r' && bufferSerie.length() < 64) {
      bufferSerie += c;
    }
  }
}

// Lectura no bloqueante: pide la conversion y la lee ~800 ms despues,
// asi el ESP32 sigue atendiendo ordenes mientras el sensor convierte.
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
      Serial.println("T:ERR");
    } else {
      Serial.printf("T:%.2f\n", t);
    }
  }
}

void setup() {
  Serial.begin(115200);
  delay(300);

  sensorTemperatura.begin();
  sensorTemperatura.setWaitForConversion(false);

  alimentador.setPeriodHertz(50);
  alimentador.attach(PIN_SERVO, 500, 2400);
  alimentador.write(SERVO_CERRADO);

  Serial.println("AquaControl ESP32 (USB) - DS18B20 en GPIO18, servo en GPIO19");
  Serial.println("READY");
}

void loop() {
  leerOrdenes();
  actualizarTemperatura();
  delay(5);
}
