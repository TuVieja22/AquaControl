<?php

namespace App\Models;

use CodeIgniter\Model;

class AlimentacionModel extends Model
{
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

    public function ultimaPorUsuario(int $userId): ?array
    {
        return $this->where('usuario_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->first();
    }
}
