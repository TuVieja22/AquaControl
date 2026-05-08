<?php

namespace App\Models;

use CodeIgniter\Model;

class AlertaModel extends Model
{
    protected $table            = 'alertas';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'usuario_id',
        'nivel',
        'tipo',
        'mensaje',
        'leida',
        'created_at',
    ];
    protected $useTimestamps = false;

    public function noLeidasPorUsuario(int $userId, int $limit = 5): array
    {
        return $this->where('usuario_id', $userId)
            ->where('leida', 0)
            ->orderBy('created_at', 'DESC')
            ->findAll($limit);
    }
}
