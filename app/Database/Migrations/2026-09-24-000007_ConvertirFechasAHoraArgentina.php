<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * La app guardaba las fechas en UTC (App::$appTimezone = 'UTC'). Al pasar a
 * America/Argentina/Buenos_Aires se corrigen los datos existentes para que sigan
 * representando el mismo instante. Argentina es UTC-3 fijo (sin horario de verano),
 * por eso alcanza con restar 3 horas a cada columna DATETIME.
 *
 * Las columnas TIME (horarios de alimentacion) ya estaban en hora local y no se tocan.
 */
class ConvertirFechasAHoraArgentina extends Migration
{
    private const OFFSET_HOURS = 3;

    public function up(): void
    {
        $this->shift('-');
    }

    public function down(): void
    {
        $this->shift('+');
    }

    private function shift(string $operator): void
    {
        $columns = $this->db->query(
            'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS '
            . "WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'datetime' AND TABLE_NAME <> 'migrations'"
        )->getResultArray();

        foreach ($columns as $column) {
            $table = $this->db->escapeIdentifiers($column['TABLE_NAME']);
            $field = $this->db->escapeIdentifiers($column['COLUMN_NAME']);

            $this->db->query(
                "UPDATE {$table} SET {$field} = {$field} {$operator} INTERVAL " . self::OFFSET_HOURS . " HOUR WHERE {$field} IS NOT NULL"
            );
        }
    }
}
