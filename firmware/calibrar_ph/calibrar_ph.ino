/*
  AquaControl - Calibrar el sensor de pH (programa aparte, solo el pH)

  Sirve para probar y calibrar la sonda sin los demas sensores. La calibracion se guarda en
  la memoria del ESP32 en el mismo lugar que usa el programa completo (aquacontrol_esp32):
  al cargar despues el programa completo desde el Arduino IDE, ya queda calibrado.

  Conexion: plaquita PH-4502C alimentada con 5V; su salida Po pasa por un divisor de dos
  resistencias: Po -> 3k -> GPIO 34 -> 1k -> GND (al pin le llega la cuarta parte). La
  de arriba (3k) tiene que ser la mas grande: al reves le llega de mas y el pin se satura.

  Cada 2 segundos muestra el voltaje y el pH. Comandos por el Monitor Serie (115200, "Nueva linea"):
    cal7     -> con la sonda en liquido de calibracion pH 7: guarda ese voltaje como pH 7
    cal4     -> con la sonda en liquido pH 4: guarda ese voltaje como pH 4
    cal10    -> (en vez de cal4) con la sonda en liquido pH 10
    calagua  -> sin liquidos de calibracion: con la sonda en agua de pH conocido (7 si no se
                escribe otro numero, o por ejemplo "calagua 7.4"). Es aproximado
    calreset -> vuelve a los valores de fabrica
    pausa    -> deja de mostrar lecturas (otra vez "pausa" para seguir)
*/

#include <Preferences.h>

#define PIN_PH 34   // senal del pH, despues del divisor de resistencias

const float PH_DIVISOR = 4.0;            // divisor 3k (arriba) y 1k (abajo): deja pasar la cuarta parte
const int PH_MUESTRAS = 40;              // lecturas que se promedian (la sonda es "ruidosa")
// Voltajes de fabrica (de la plaquita, antes del divisor) para pH 7 y pH 4; se reemplazan al calibrar.
// Son los mismos que en el programa completo.
const float PH_V7_FABRICA = 2.50;
const float PH_V4_FABRICA = 3.03;
const unsigned long INTERVALO_MS = 2000; // cada cuanto muestra una lectura

Preferences memoria;
// Calibracion: voltaje de la plaquita en pH 7 y en el segundo liquido (pH 4 o 10).
float phV7 = PH_V7_FABRICA, phV2 = PH_V4_FABRICA, phPunto2 = 4.0;
String lineaSerie;
bool pausado = false;
unsigned long ultimaLectura = 0;
// Lo que midio el pin en la ultima lectura (en milivoltios), para explicar un "sin lectura".
uint32_t pinPromedio = 0, pinMinimo = 0, pinMaximo = 0;

// Voltaje de la plaquita (pin Po), promediando varias lecturas. Devuelve -1 si no hay
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
  pinPromedio = suma / PH_MUESTRAS;
  pinMinimo = minimo;
  pinMaximo = maximo;
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

// Lee la calibracion guardada (la misma que usa el programa completo).
void leerCalibracion() {
  memoria.begin("aquacontrol", false);
  phV7 = memoria.getFloat("ph_v7", PH_V7_FABRICA);
  phV2 = memoria.getFloat("ph_v2", PH_V4_FABRICA);
  phPunto2 = memoria.getFloat("ph_p2", 4.0);
  memoria.end();
}

// Guarda la calibracion en la memoria.
void guardarCalibracion() {
  memoria.begin("aquacontrol", false);
  memoria.putFloat("ph_v7", phV7);
  memoria.putFloat("ph_v2", phV2);
  memoria.putFloat("ph_p2", phPunto2);
  memoria.end();
}

// Muestra la calibracion actual y cuantos milivoltios cambia por cada punto de pH.
void mostrarCalibracion() {
  float mvPorPh = (phV2 - phV7) / (phPunto2 - 7.0f) * 1000.0f;
  Serial.printf("Calibracion: pH 7 = %.3f V, pH %.0f = %.3f V -> %.0f mV por punto de pH\n",
                phV7, phPunto2, phV2, mvPorPh);
  // Una sonda sana cambia unos 160-180 mV por punto (bajando cuando sube el pH).
  if (fabsf(mvPorPh) < 100 || fabsf(mvPorPh) > 250) {
    Serial.println("  OJO: ese valor es raro. Revisa que la sonda este en el liquido correcto y vuelve a calibrar.");
  }
}

// Muestra una lectura: voltaje de la plaquita, lo que llega al pin y el pH.
void mostrarLectura() {
  float voltios = voltajePh();
  if (voltios >= 0) {
    Serial.printf("plaquita %.3f V (al pin llegan %.3f V) -> pH %.2f\n", voltios, voltios / PH_DIVISOR, calcularPh(voltios));
    return;
  }
  // Sin lectura: muestra lo que llega al pin y la causa mas probable.
  Serial.printf("pH: sin lectura. Al pin llegan %u mV en promedio (minimo %u, maximo %u) -> ",
                pinPromedio, pinMinimo, pinMaximo);
  if ((pinMaximo - pinMinimo) * PH_DIVISOR > 500) {
    Serial.println("la lectura salta mucho: cable de senal flojo o sonda desconectada de la plaquita.");
  } else if (pinPromedio < 100) {
    Serial.println("casi 0 V: cable de senal suelto, la plaquita sin 5V, o su GND no esta unido al del ESP32.");
  } else {
    Serial.println("demasiado voltaje: revisar el divisor (las dos resistencias).");
  }
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

    if (comando == "pausa") {
      pausado = !pausado;
      Serial.println(pausado ? "Lecturas en pausa (escribi \"pausa\" para seguir)." : "Lecturas otra vez.");
      continue;
    }
    if (comando == "calreset") {
      phV7 = PH_V7_FABRICA;
      phV2 = PH_V4_FABRICA;
      phPunto2 = 4.0f;
      guardarCalibracion();
      Serial.println("Calibracion vuelta a los valores de fabrica.");
      mostrarCalibracion();
      continue;
    }

    // Los demas comandos calibran con lo que mide ahora.
    float voltios = voltajePh();
    if (comando == "cal7" || comando == "cal4" || comando == "cal10") {
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
      guardarCalibracion();
      Serial.printf("Guardado: %.3f V = pH %s\n", voltios, comando.substring(3).c_str());
      mostrarCalibracion();
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
      guardarCalibracion();
      Serial.printf("Guardado: %.3f V = pH %.2f (calibracion de un punto, aproximada)\n", voltios, valor);
      mostrarCalibracion();
    } else {
      Serial.println("Comandos: cal7 | cal4 | cal10 | calagua | calreset | pausa");
    }
  }
}

void setup() {
  Serial.begin(115200);
  delay(300);
  Serial.println();
  Serial.println("AquaControl - calibrar el sensor de pH (GPIO 34)");

  // El pin del pH lee de 0 a 3.3V.
  analogSetPinAttenuation(PIN_PH, ADC_11db);

  leerCalibracion();
  mostrarCalibracion();
  Serial.println("Comandos: cal7 | cal4 | cal10 | calagua | calreset | pausa");
}

void loop() {
  atenderSerie();
  if (!pausado && millis() - ultimaLectura >= INTERVALO_MS) {
    ultimaLectura = millis();
    mostrarLectura();
  }
  delay(10);
}
