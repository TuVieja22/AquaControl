<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Avisos para el usuario (temperatura fuera de rango, nivel de agua bajo...).
 */
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

    /** Marca la alerta como leida solo si pertenece al usuario. */
    public function marcarLeida(int $alertId, int $userId): void
    {
        $this->builder()
            ->where('id', $alertId)
            ->where('usuario_id', $userId)
            ->update(['leida' => 1]);
    }
}
