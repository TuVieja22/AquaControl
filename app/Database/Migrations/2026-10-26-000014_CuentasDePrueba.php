<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Cuentas;

/**
 * Cuentas de prueba: usuarios.prueba_hasta guarda hasta cuando puede usarse una cuenta que
 * todavia no tiene un AquaControl (NULL = cuenta permanente). Pasado ese dia, se borra.
 *
 * Las cuentas que ya existian no se borran de golpe: las que tienen un dispositivo o una
 * compra aprobada quedan permanentes, y las demas reciben los dias de prueba desde hoy.
 */
class CuentasDePrueba extends Migration
{
    // Agrega la fecha de vencimiento y la completa para las cuentas que ya existen.
    public function up(): void
    {
        if ($this->existeColumna('usuarios', 'prueba_hasta')) {
            return;
        }

        $this->db->query(
            "ALTER TABLE usuarios
             ADD prueba_hasta DATETIME NULL DEFAULT NULL COMMENT 'hasta cuando dura la cuenta de prueba (NULL = permanente)' AFTER activo"
        );

        // Sin dispositivo ni compra aprobada: los dias de prueba empiezan a contar hoy.
        $this->db->query(
            "UPDATE usuarios u
             SET u.prueba_hasta = ?
             WHERE NOT EXISTS (SELECT 1 FROM dispositivos d WHERE d.usuario_id = u.id)
               AND NOT EXISTS (SELECT 1 FROM pedidos p WHERE p.usuario_id = u.id AND p.estado = 'aprobado')",
            [date('Y-m-d H:i:s', time() + config(Cuentas::class)->diasDePrueba * 86400)]
        );
    }

    // Borra la fecha de vencimiento.
    public function down(): void
    {
        if ($this->existeColumna('usuarios', 'prueba_hasta')) {
            $this->db->query('ALTER TABLE usuarios DROP prueba_hasta');
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
