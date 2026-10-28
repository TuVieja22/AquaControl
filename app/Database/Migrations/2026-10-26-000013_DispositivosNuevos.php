<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * AquaControl recien conectados a internet que todavia no estan en ninguna cuenta.
 *
 * Cuando el usuario conecta su AquaControl al WiFi de su casa (desde la red del propio
 * AquaControl), el ESP32 se anota aca con su MAC, una clave secreta propia (token) y la
 * direccion desde la que llama. Cuando el usuario abre Dispositivos desde esa misma red, la
 * pagina se lo muestra para agregarlo con un toque. Despues el ESP32 recibe su API key
 * (solo si manda el mismo token) y la fila se borra.
 */
class DispositivosNuevos extends Migration
{
    // Crea la tabla (si no existe).
    public function up(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS dispositivos_nuevos (
                id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                mac CHAR(12) NOT NULL COMMENT 'MAC del ESP32, sin dos puntos',
                token_hash CHAR(64) NOT NULL COMMENT 'hash de la clave secreta que guarda el ESP32',
                ip VARCHAR(45) NOT NULL COMMENT 'direccion desde la que llamo (para reconocer la red)',
                dispositivo_id INT(11) UNSIGNED NULL DEFAULT NULL COMMENT 'el que se creo al agregarlo (falta entregarle la API key)',
                visto_at DATETIME NOT NULL COMMENT 'ultima vez que pregunto',
                created_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_mac (mac),
                KEY fk_nuevos_dispositivo (dispositivo_id),
                CONSTRAINT fk_nuevos_dispositivo FOREIGN KEY (dispositivo_id)
                    REFERENCES dispositivos (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
    }

    // Borra la tabla.
    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS dispositivos_nuevos');
    }
}
