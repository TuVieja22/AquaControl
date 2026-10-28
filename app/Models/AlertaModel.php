<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Avisos para el usuario (temperatura fuera de rango, nivel de agua bajo...).
 */
class AlertaModel extends Model
{
    // Tabla, clave y columnas que se pueden escribir.
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
        'notificada_at',
        'created_at',
    ];
    protected $useTimestamps = false;

    /**
     * Si el usuario ya tiene una alerta sin leer de ese tipo (y que empiece con ese texto,
     * si se pasa) de hace menos de $minutos. Sirve para no repetir el mismo aviso cada 5 s.
     */
    public function hayParecidaReciente(int $userId, string $tipo, int $minutos, string $comienzo = ''): bool
    {
        $consulta = $this->where('usuario_id', $userId)
            ->where('tipo', $tipo)
            ->where('leida', 0)
            ->where('created_at >=', date('Y-m-d H:i:s', time() - $minutos * 60));
        if ($comienzo !== '') {
            $consulta->like('mensaje', $comienzo, 'after');
        }

        return $consulta->countAllResults() > 0;
    }

    // Alertas que todavia no se mandaron como notificacion, de la mas vieja a la mas nueva.
    public function sinNotificar(int $limit = 50): array
    {
        return $this->where('notificada_at', null)->orderBy('id', 'ASC')->findAll($limit);
    }

    // Anota que la alerta ya se mando (o que no habia a quien mandarla).
    public function marcarNotificada(int $alertId): void
    {
        $this->builder()->where('id', $alertId)->update(['notificada_at' => date('Y-m-d H:i:s')]);
    }

    // Alertas sin leer del usuario, de la mas nueva a la mas vieja.
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
