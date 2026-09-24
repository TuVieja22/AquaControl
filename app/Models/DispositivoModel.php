<?php

namespace App\Models;

use CodeIgniter\Model;

class DispositivoModel extends Model
{
    public const API_KEY_PREFIX = 'aqk_';

    protected $table            = 'dispositivos';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'usuario_id',
        'nombre',
        'tipo',
        'ubicacion',
        'api_key_hash',
        'api_key_prefijo',
        'api_key_generada_at',
        'ultima_conexion',
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

    /**
     * Genera (o rota) la API key del dispositivo. Solo se guarda el hash SHA-256:
     * la key en texto plano se devuelve una unica vez para mostrarsela al usuario.
     */
    public function generarApiKey(int $deviceId): string
    {
        $apiKey = self::API_KEY_PREFIX . bin2hex(random_bytes(24));

        $this->builder()
            ->where('id', $deviceId)
            ->update([
                'api_key_hash'        => self::hashApiKey($apiKey),
                'api_key_prefijo'     => substr($apiKey, 0, 12),
                'api_key_generada_at' => date('Y-m-d H:i:s'),
            ]);

        return $apiKey;
    }

    public function revocarApiKey(int $deviceId): void
    {
        $this->builder()
            ->where('id', $deviceId)
            ->update([
                'api_key_hash'        => null,
                'api_key_prefijo'     => null,
                'api_key_generada_at' => null,
            ]);
    }

    public function buscarPorApiKey(string $apiKey): ?array
    {
        if (! str_starts_with($apiKey, self::API_KEY_PREFIX)) {
            return null;
        }

        return $this->where('api_key_hash', self::hashApiKey($apiKey))->first();
    }

    public function registrarConexion(int $deviceId): void
    {
        $this->builder()
            ->where('id', $deviceId)
            ->update(['ultima_conexion' => date('Y-m-d H:i:s')]);
    }

    public static function hashApiKey(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }
}
