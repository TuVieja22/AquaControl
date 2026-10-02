<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Registro de cada vez que se alimento a los peces (manual, automatica o en vacaciones).
 */
class AlimentacionModel extends Model
{
    public const TIPOS = [
        'manual'     => 'Manual',
        'automatica' => 'Automatica',
        'vacaciones' => 'Vacaciones',
    ];

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

    public function ultimasPorUsuario(int $userId, int $limit = 10): array
    {
        return $this->where('usuario_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll($limit);
    }
}
