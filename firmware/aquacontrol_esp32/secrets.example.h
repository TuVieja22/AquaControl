// Copia este archivo como "secrets.h" (en esta misma carpeta) y completalo.
// secrets.h NO se sube a GitHub: tiene la clave del WiFi y la API key.
#pragma once

// Red WiFi de 2.4 GHz (el ESP32 no se conecta a redes de 5 GHz).
#define WIFI_SSID     "NOMBRE_DE_TU_RED"
#define WIFI_PASSWORD "CLAVE_DE_TU_RED"

// Direccion de la PC donde corre la pagina (php spark serve --host 0.0.0.0).
// No uses "localhost": para el ESP32, localhost es el propio ESP32.
#define SERVER_URL "http://192.168.1.195:8080"

// API key del dispositivo: en la pagina, Dispositivos > "Generar key".
#define DEVICE_KEY "aqk_..."
