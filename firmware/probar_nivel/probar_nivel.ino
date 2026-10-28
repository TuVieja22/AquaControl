/*
  AquaControl - Probar el sensor de nivel de agua (programa aparte, solo el flotante)

  Sirve para probar el flotante sin los demas sensores. El sentido de la lectura (normal o
  invertida) se guarda en la memoria del ESP32 en el mismo lugar que usa el programa completo
  (aquacontrol_esp32): al cargar despues el programa completo, ya queda configurado.

  Conexion: el flotante es un interruptor con dos cables, sin polaridad (da igual cual va
  a cada lado): un cable al GPIO 17 y el otro a GND. No lleva 5V ni resistencias: el ESP32
  usa su resistencia interna (INPUT_PULLUP). Cerrado = LOW, abierto = HIGH.

  Muestra el estado al cambiar y cada 2 segundos. Comandos por el Monitor Serie (115200, "Nueva linea"):
    invertir -> da vuelta la lectura (si marca "BAJO" con el agua bien)
    pausa    -> deja de mostrar lecturas (otra vez "pausa" para seguir)
*/

#include <Preferences.h>

#define PIN_NIVEL 17   // flotante del nivel de agua (entre el pin y GND)

const unsigned long INTERVALO_MS = 2000;   // cada cuanto repite el estado
const unsigned long MS_ESTABLE = 50;       // el contacto rebota al moverse: se espera que se quede quieto

Preferences memoria;
// Si el flotante lee al reves (se da vuelta con el comando "invertir").
bool nivelInvertido = false;
String lineaSerie;
bool pausado = false;
unsigned long ultimaLectura = 0;
// Ultimo estado estable del pin y desde cuando cambio la lectura cruda.
int estadoEstable = -1;
int ultimaCruda = -1;
unsigned long cambioDesde = 0;

// Si el nivel de agua esta bien segun el pin (cerrado = LOW), teniendo en cuenta si esta invertido.
bool nivelOk(int lectura) {
  bool cerrado = lectura == LOW;
  return nivelInvertido ? !cerrado : cerrado;
}

// Muestra el estado del flotante y lo que significa.
void mostrarEstado(const char* motivo) {
  Serial.printf("%sFlotante %s (pin %s) -> nivel %s%s\n", motivo,
                estadoEstable == LOW ? "CERRADO" : "ABIERTO",
                estadoEstable == LOW ? "LOW" : "HIGH",
                nivelOk(estadoEstable) ? "OK" : "BAJO",
                nivelInvertido ? " (lectura invertida)" : "");
}

// Atiende los comandos del Monitor Serie.
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

    if (comando == "invertir") {
      nivelInvertido = !nivelInvertido;
      memoria.begin("aquacontrol", false);
      memoria.putBool("nivel_inv", nivelInvertido);
      memoria.end();
      Serial.printf("Lectura %s (guardada).\n", nivelInvertido ? "invertida" : "normal");
      mostrarEstado("");
    } else if (comando == "pausa") {
      pausado = !pausado;
      Serial.println(pausado ? "Lecturas en pausa (escribi \"pausa\" para seguir)." : "Lecturas otra vez.");
    } else {
      Serial.println("Comandos: invertir | pausa");
    }
  }
}

void setup() {
  Serial.begin(115200);
  delay(300);
  Serial.println();
  Serial.println("AquaControl - probar el flotante del nivel de agua (GPIO 17 y GND)");

  // Resistencia interna hacia 3.3V: con el flotante abierto el pin lee HIGH, cerrado lee LOW.
  pinMode(PIN_NIVEL, INPUT_PULLUP);

  memoria.begin("aquacontrol", false);
  nivelInvertido = memoria.getBool("nivel_inv", false);
  memoria.end();

  estadoEstable = ultimaCruda = digitalRead(PIN_NIVEL);
  mostrarEstado("Al arrancar: ");
  Serial.println("Comandos: invertir | pausa");
}

void loop() {
  atenderSerie();

  // Cambio de estado: se avisa solo cuando la lectura se quedo quieta (sin rebotes).
  int cruda = digitalRead(PIN_NIVEL);
  if (cruda != ultimaCruda) {
    ultimaCruda = cruda;
    cambioDesde = millis();
  }
  if (cruda != estadoEstable && millis() - cambioDesde >= MS_ESTABLE) {
    estadoEstable = cruda;
    mostrarEstado("CAMBIO: ");
  }

  if (!pausado && millis() - ultimaLectura >= INTERVALO_MS) {
    ultimaLectura = millis();
    mostrarEstado("");
  }
  delay(5);
}
