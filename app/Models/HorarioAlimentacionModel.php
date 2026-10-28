<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Horarios de la alimentacion programada: una fila por horario de cada configuracion
 * de pecera (antes eran las columnas hora_alim_1 y hora_alim_2).
 */
class HorarioAlimentacionModel extends Model
{
    // Tabla, clave y columnas que se pueden escribir.
    protected $table            = 'horarios_alimentacion';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'configuracion_id',
        'hora',
        'created_at',
    ];
    protected $useTimestamps = false;

    /** Horarios de una configuracion como "HH:MM", ordenados. */
    public function deConfiguracion(int $configuracionId): array
    {
        $filas = $this->where('configuracion_id', $configuracionId)
            ->orderBy('hora', 'ASC')
            ->findAll();

        return array_map(static fn (array $fila): string => substr((string) $fila['hora'], 0, 5), $filas);
    }

    /** Cambia todos los horarios de una configuracion por los nuevos ("HH:MM"). */
    public function reemplazar(int $configuracionId, array $horas): void
    {
        // Se borran los de antes y se guardan los nuevos, todo junto o nada.
        $this->db->transStart();
        $this->where('configuracion_id', $configuracionId)->delete();

        foreach (array_unique($horas) as $hora) {
            $this->insert([
                'configuracion_id' => $configuracionId,
                'hora'             => $hora . ':00',
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->transComplete();
    }
}
