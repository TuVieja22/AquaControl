<?php

namespace App\Models;

use CodeIgniter\Model;

class ConfiguracionPeceraModel extends Model
{
    protected $table            = 'configuracion_pecera';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'usuario_id',
        'temp_min',
        'temp_max',
        'ph_min',
        'ph_max',
        'temp_objetivo',
        'modo_vacaciones',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function porUsuario(int $userId): ?array
    {
        return $this->where('usuario_id', $userId)->first();
    }
}
