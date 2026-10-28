<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Registro de cada vez que se alimento a los peces (manual, automatica o en vacaciones).
 */
class AlimentacionModel extends Model
{
    // Nombre que se muestra de cada tipo de alimentacion.
    public const TIPOS = [
        'manual'     => 'Manual',
        'automatica' => 'Automática',
        'vacaciones' => 'Vacaciones',
    ];

    // Tabla, clave y columnas que se pueden escribir.
    protected $table            = 'alimentaciones';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'usuario_id',
        'cantidad_gramos',
        'tipo',
        'created_at',
    ];
    protected $useTimestamps = false;

    // Ultimas alimentaciones del usuario.
    public function ultimasPorUsuario(int $userId, int $limit = 10): array
    {
        return $this->where('usuario_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll($limit);
    }
}
