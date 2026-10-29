<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cuanta comida deja caer el alimentador en un giro de la compuerta.
 *
 * El servo siempre hace el mismo movimiento, asi que lo que sale por giro depende del
 * tamanio de la muesca del dispensador y del tipo de alimento: es algo de cada equipo.
 * Con este valor la pagina puede traducir una racion en gramos a una cantidad de giros,
 * y asi la racion de verdad importa (antes se abria una sola vez para cualquier racion).
 *
 * Se mide una vez: con el alimentador vacio arriba, se pide "Alimentar ahora" varias
 * veces, se pesa lo que cayo y se divide por la cantidad de veces.
 *
 * El valor de arranque (0.120 g) es el medido en el prototipo: 25 giros dieron 3 g.
 */
class GramosPorGiro extends Migration
{
    // Agrega los gramos por giro.
    public function up(): void
    {
        if ($this->existeColumna('configuracion_pecera', 'gramos_por_giro')) {
            return;
        }

        $this->db->query(
            "ALTER TABLE configuracion_pecera
             ADD gramos_por_giro DECIMAL(4,3) NOT NULL DEFAULT 0.120
                 COMMENT 'gramos que deja caer el alimentador en un giro de la compuerta'
             AFTER gramos_vacaciones"
        );
    }

    // Borra los gramos por giro.
    public function down(): void
    {
        if ($this->existeColumna('configuracion_pecera', 'gramos_por_giro')) {
            $this->db->query('ALTER TABLE configuracion_pecera DROP gramos_por_giro');
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
