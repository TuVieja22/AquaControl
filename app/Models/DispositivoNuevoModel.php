<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * AquaControl que ya tienen internet pero todavia no estan en ninguna cuenta (ver la
 * migracion DispositivosNuevos). La pagina los muestra a quien entra desde la misma red.
 */
class DispositivoNuevoModel extends Model
{
    /** Minutos desde la ultima vez que pregunto para seguir mostrandolo (el ESP32 pregunta cada 10 s). */
    public const MINUTOS_VISIBLE = 10;

    // Tabla, clave y columnas que se pueden escribir.
    protected $table            = 'dispositivos_nuevos';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = ['mac', 'token_hash', 'ip', 'dispositivo_id', 'visto_at', 'created_at'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = '';

    /**
     * El ESP32 pregunta si ya lo agregaron. Devuelve su fila (creandola si es la primera vez),
     * o null si el token no coincide con el de un AquaControl que ya agregaron (no es el mismo).
     */
    public function registrar(string $mac, string $token, string $ip): ?array
    {
        $hash = hash('sha256', $token);
        $fila = $this->where('mac', $mac)->first();
        $ahora = date('Y-m-d H:i:s');

        // Primera vez: se anota.
        if ($fila === null) {
            $id = (int) $this->insert(['mac' => $mac, 'token_hash' => $hash, 'ip' => $ip, 'visto_at' => $ahora]);

            return $this->find($id);
        }

        // Otro token: si todavia no lo agregaron, gana el ultimo (por ejemplo, se borro su memoria);
        // si ya lo agregaron, no se le da la clave a otro aparato que diga tener esa MAC.
        if (! hash_equals($fila['token_hash'], $hash)) {
            if ($fila['dispositivo_id'] !== null) {
                return null;
            }
            $fila['token_hash'] = $hash;
        }

        $this->update((int) $fila['id'], ['token_hash' => $fila['token_hash'], 'ip' => $ip, 'visto_at' => $ahora]);

        return ['ip' => $ip, 'visto_at' => $ahora] + $fila;
    }

    // Los que todavia nadie agrego, que preguntaron hace poco desde la misma red que $ip.
    public function visiblesDesde(string $ip): array
    {
        $recientes = $this->where('dispositivo_id', null)
            ->where('visto_at >=', date('Y-m-d H:i:s', time() - self::MINUTOS_VISIBLE * 60))
            ->orderBy('visto_at', 'DESC')
            ->findAll();

        return array_values(array_filter($recientes, static fn (array $fila): bool => self::mismaRed($ip, $fila['ip'])));
    }

    // Anota que lo agregaron (al dispositivo $deviceId); falta entregarle la API key.
    public function marcarAgregado(int $id, int $deviceId): void
    {
        $this->update($id, ['dispositivo_id' => $deviceId]);
    }

    /**
     * Si dos direcciones son de la misma red: la misma IP publica (con la pagina en internet,
     * todos los aparatos de una casa salen con la misma), o las dos de una red local
     * (192.168..., 10..., 172.16-31..., como cuando la pagina corre en una PC de la casa).
     */
    public static function mismaRed(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        $local = static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

        return $local($a) && $local($b);
    }
}
