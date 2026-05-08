<?php

namespace App\Models;

use CodeIgniter\Model;

class SensorModel extends Model
{
    protected $table            = 'lecturas_sensores';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'usuario_id',
        'temperatura',
        'ph',
        'turbidez',
        'nivel_agua',
        'calefactor',
        'modo_vacaciones',
        'created_at',
    ];
    protected $useTimestamps = false;

    public function ultimaLectura(int $userId): ?array
    {
        return $this->where('usuario_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    public function historial(int $userId, int $hours = 24): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

        return $this->select('temperatura, ph, nivel_agua, calefactor, modo_vacaciones, created_at')
            ->where('usuario_id', $userId)
            ->where('created_at >=', $since)
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }
}
