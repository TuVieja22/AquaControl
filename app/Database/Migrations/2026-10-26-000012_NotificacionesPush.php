<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Alertas de la pecera como notificacion en el celular (Web Push):
 *
 * - suscripciones_push: celulares y computadoras que aceptaron recibir las alertas. Cada
 *   navegador tiene su direccion en el servicio de push (endpoint) y dos claves propias
 *   para cifrar los avisos. Un usuario puede tener varios (su celular y su PC).
 * - alertas.notificada_at: cuando se mando la notificacion de esa alerta (NULL = falta).
 *   Las manda `php spark alertas:revisar`, aparte, para no demorar las respuestas al ESP32.
 *
 * Cada paso revisa si ya esta hecho.
 */
class NotificacionesPush extends Migration
{
    // Crea la tabla de suscripciones y agrega la marca de "notificada" a las alertas.
    public function up(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS suscripciones_push (
                id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                usuario_id INT(11) UNSIGNED NOT NULL,
                endpoint VARCHAR(500) NOT NULL COMMENT 'direccion del navegador en el servicio de push',
                p256dh VARCHAR(100) NOT NULL COMMENT 'clave publica del navegador (base64url)',
                auth VARCHAR(50) NOT NULL COMMENT 'secreto del navegador (base64url)',
                created_at DATETIME NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_endpoint (endpoint),
                KEY idx_usuario (usuario_id),
                CONSTRAINT fk_suscripciones_usuario FOREIGN KEY (usuario_id)
                    REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        if (! $this->existeColumna('alertas', 'notificada_at')) {
            $this->db->query(
                "ALTER TABLE alertas
                 ADD notificada_at DATETIME NULL DEFAULT NULL COMMENT 'cuando se mando al celular (NULL = falta)' AFTER leida"
            );
            // Las alertas viejas no se mandan: se marcan como ya notificadas.
            $this->db->query('UPDATE alertas SET notificada_at = created_at');
        }
    }

    // Borra la tabla y la marca.
    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS suscripciones_push');

        if ($this->existeColumna('alertas', 'notificada_at')) {
            $this->db->query('ALTER TABLE alertas DROP notificada_at');
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
