<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Racion que usa el alimentador mientras el modo vacaciones esta prendido.
 *
 * Con nadie en casa que limpie, la comida que sobra ensucia el agua: por eso las
 * alimentaciones programadas pasan a usar esta racion, normalmente mas chica que
 * la de todos los dias (configuracion_pecera.cantidad_alim_gramos).
 *
 * A las peceras que ya existen se les deja el 60% de su racion habitual, que es
 * el punto de partida que propone la pagina.
 */
class RacionDeVacaciones extends Migration
{
    // Agrega la racion de vacaciones y la completa en las peceras que ya existen.
    public function up(): void
    {
        if ($this->existeColumna('configuracion_pecera', 'gramos_vacaciones')) {
            return;
        }

        $this->db->query(
            "ALTER TABLE configuracion_pecera
             ADD gramos_vacaciones DECIMAL(4,2) NOT NULL DEFAULT 0.60
                 COMMENT 'racion de las alimentaciones programadas con el modo vacaciones prendido'
             AFTER cantidad_alim_gramos"
        );

        // El 60% de la racion habitual, sin bajar del minimo que acepta el alimentador.
        $this->db->query(
            'UPDATE configuracion_pecera SET gramos_vacaciones = GREATEST(ROUND(cantidad_alim_gramos * 0.6, 2), 0.10)'
        );
    }

    // Borra la racion de vacaciones.
    public function down(): void
    {
        if ($this->existeColumna('configuracion_pecera', 'gramos_vacaciones')) {
            $this->db->query('ALTER TABLE configuracion_pecera DROP gramos_vacaciones');
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
