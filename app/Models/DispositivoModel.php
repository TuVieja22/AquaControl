<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Equipos de cada usuario (el ESP32, sensores...). Los que tienen API key pueden
 * mandar lecturas y recibir ordenes del alimentador.
 */
class DispositivoModel extends Model
{
    // Todas las API keys empiezan con este texto.
    public const API_KEY_PREFIX = 'aqk_';

    // Tipos de dispositivo y el nombre que se muestra de cada uno.
    public const TIPOS = [
        'sensor'      => 'Sensor',
        'actuador'    => 'Actuador',
        'controlador' => 'Controlador',
        'kit_iot'     => 'Kit IoT',
        'otro'        => 'Otro',
    ];

    /** Cada cuantos segundos como maximo se actualiza `ultima_conexion` (el ESP32 consulta cada 2 s). */
    private const SEGUNDOS_ENTRE_REGISTROS = 10;

    // Tabla, clave y columnas que se pueden escribir.
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

    // Reglas que se revisan antes de guardar un dispositivo.
    protected $validationRules = [
        'usuario_id' => 'required|is_natural_no_zero',
        'nombre'     => 'required|min_length[2]|max_length[100]',
        'tipo'       => 'required|in_list[sensor,actuador,controlador,kit_iot,otro]',
        'ubicacion'  => 'required|min_length[2]|max_length[120]',
    ];

    // Mensaje de cada error.
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

    // Dispositivos del usuario, del mas nuevo al mas viejo.
    public function porUsuario(int $userId): array
    {
        return $this->where('usuario_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    // Un dispositivo, solo si es de ese usuario.
    public function buscarParaUsuario(int $deviceId, int $userId): ?array
    {
        return $this->where('id', $deviceId)
            ->where('usuario_id', $userId)
            ->first();
    }

    /** Puede recibir ordenes del alimentador: tiene API key y no es un sensor puro. */
    public static function esAlimentador(array $device): bool
    {
        return ! empty($device['api_key_hash']) && $device['tipo'] !== 'sensor';
    }

    /**
     * Genera (o rota) la API key del dispositivo. Solo se guarda el hash SHA-256:
     * la key en texto plano se devuelve una unica vez para mostrarsela al usuario.
     */
    public function generarApiKey(int $deviceId): string
    {
        // Clave al azar: el prefijo mas 48 letras y numeros.
        $apiKey = self::API_KEY_PREFIX . bin2hex(random_bytes(24));

        // Se guarda el hash, el comienzo de la clave (para reconocerla) y la fecha.
        $this->builder()
            ->where('id', $deviceId)
            ->update([
                'api_key_hash'        => self::hashApiKey($apiKey),
                'api_key_prefijo'     => substr($apiKey, 0, 12),
                'api_key_generada_at' => date('Y-m-d H:i:s'),
            ]);

        return $apiKey;
    }

    // Borra la API key del dispositivo.
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

    // Dispositivo al que le pertenece una API key (null si no existe).
    public function buscarPorApiKey(string $apiKey): ?array
    {
        if (! str_starts_with($apiKey, self::API_KEY_PREFIX)) {
            return null;
        }

        return $this->where('api_key_hash', self::hashApiKey($apiKey))->first();
    }

    /**
     * Anota que el dispositivo se comunico. Como consulta cada pocos segundos, se escribe
     * como mucho cada SEGUNDOS_ENTRE_REGISTROS (para "en linea" alcanza de sobra).
     */
    public function registrarConexion(array $device): void
    {
        // Si ya se anoto hace muy poco, no se vuelve a escribir.
        $ultima = strtotime((string) ($device['ultima_conexion'] ?? '')) ?: 0;
        if (time() - $ultima < self::SEGUNDOS_ENTRE_REGISTROS) {
            return;
        }

        $this->builder()
            ->where('id', $device['id'])
            ->update(['ultima_conexion' => date('Y-m-d H:i:s')]);
    }

    // Hash SHA-256 de una API key (es lo que se guarda en la base).
    public static function hashApiKey(string $apiKey): string
    {
        return hash('sha256', $apiKey);
    }
}
