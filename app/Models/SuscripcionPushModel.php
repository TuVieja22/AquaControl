<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Navegadores (celular, PC) donde cada usuario acepto recibir las alertas como notificacion.
 */
class SuscripcionPushModel extends Model
{
    // Tabla, clave y columnas que se pueden escribir.
    protected $table            = 'suscripciones_push';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = ['usuario_id', 'endpoint', 'p256dh', 'auth', 'created_at'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = '';

    // Suscripciones del usuario.
    public function deUsuario(int $userId): array
    {
        return $this->where('usuario_id', $userId)->findAll();
    }

    /**
     * Guarda la suscripcion de un navegador. Si ese navegador ya estaba (aunque sea de otra
     * cuenta, por ejemplo una PC compartida), queda a nombre del usuario actual.
     */
    public function guardar(int $userId, string $endpoint, string $p256dh, string $auth): void
    {
        $existente = $this->where('endpoint', $endpoint)->first();
        $datos = ['usuario_id' => $userId, 'endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth];

        if ($existente !== null) {
            $this->update((int) $existente['id'], $datos);

            return;
        }
        $this->insert($datos);
    }

    // Borra la suscripcion de un navegador (solo si es de ese usuario).
    public function borrar(int $userId, string $endpoint): void
    {
        $this->where('usuario_id', $userId)->where('endpoint', $endpoint)->delete();
    }

    // Borra una suscripcion que el servicio de push dio por terminada.
    public function borrarPorEndpoint(string $endpoint): void
    {
        $this->where('endpoint', $endpoint)->delete();
    }
}
