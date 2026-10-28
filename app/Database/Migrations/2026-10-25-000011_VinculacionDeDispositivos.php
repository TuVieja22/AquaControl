<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vinculacion de un AquaControl con su direccion MAC:
 *
 *   1) En Dispositivos el usuario escribe la MAC de su ESP32 (sale en la etiqueta y en el
 *      monitor serie). Queda guardada en dispositivos.mac, todavia sin API key.
 *   2) El ESP32, ya conectado al WiFi de la casa, manda su MAC y recibe su API key.
 *
 * La MAC es unica: un mismo ESP32 no puede estar en dos cuentas. Revisa si ya esta hecho.
 */
class VinculacionDeDispositivos extends Migration
{
    // Agrega la MAC del ESP32 a los dispositivos.
    public function up(): void
    {
        if (! $this->existeColumna('dispositivos', 'mac')) {
            $this->db->query(
                "ALTER TABLE dispositivos
                 ADD mac CHAR(12) NULL DEFAULT NULL COMMENT 'MAC del ESP32, sin dos puntos (NULL = cargado a mano)' AFTER usuario_id,
                 ADD UNIQUE KEY uq_mac (mac)"
            );
        }
    }

    // Borra la MAC.
    public function down(): void
    {
        if ($this->existeColumna('dispositivos', 'mac')) {
            $this->db->query('ALTER TABLE dispositivos DROP INDEX uq_mac, DROP mac');
        }
    }

    /** Si la columna existe en la tabla (se pregunta cada vez: CodeIgniter guarda la lista en memoria). */
    private function existeColumna(string $tabla, string $columna): bool
    {
        return $this->db->query(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tabla, $columna]
        )->getRow() !== null;
    }
}
