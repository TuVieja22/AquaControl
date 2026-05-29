<?php

namespace App\Models;

use CodeIgniter\Model;

class DispositivoModel extends Model
{
    protected $table            = 'dispositivos';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'usuario_id',
        'nombre',
        'tipo',
        'ubicacion',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'usuario_id' => 'required|is_natural_no_zero',
        'nombre'     => 'required|min_length[2]|max_length[100]',
        'tipo'       => 'required|in_list[sensor,actuador,controlador,kit_iot,otro]',
        'ubicacion'  => 'required|min_length[2]|max_length[120]',
    ];

    protected $validationMessages = [
        'nombre' => [
            'required'   => 'El nombre del dispositivo es obligatorio.',
            'min_length' => 'El nombre debe tener al menos 2 caracteres.',
        ],
        'tipo' => [
            'required' => 'Selecciona el tipo de dispositivo.',
            'in_list'  => 'Selecciona un tipo de dispositivo valido.',
        ],
        'ubicacion' => [
            'required'   => 'La ubicacion es obligatoria.',
            'min_length' => 'La ubicacion debe tener al menos 2 caracteres.',
        ],
    ];

    public function porUsuario(int $userId): array
    {
        return $this->where('usuario_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function buscarParaUsuario(int $deviceId, int $userId): ?array
    {
        return $this->where('id', $deviceId)
            ->where('usuario_id', $userId)
            ->first();
    }
}
