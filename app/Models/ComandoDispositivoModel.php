<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Feeding;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Cola de comandos para los dispositivos IoT.
 *
 * Ciclo de vida: pendiente -> enviado (el ESP32 lo tomo) -> ejecutado | fallido.
 * Un pendiente que nadie toma antes de `expira_at` pasa a expirado. La entrega es
 * "a lo sumo una vez": un comando enviado no se reenvia (mejor saltear una racion
 * que alimentar dos veces).
 */
class ComandoDispositivoModel extends Model
{
    public const ACCION_ALIMENTAR = 'alimentar';

    protected $table            = 'comandos_dispositivo';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'usuario_id',
        'dispositivo_id',
        'accion',
        'parametros',
        'origen',
        'estado',
        'programado_para',
        'expira_at',
        'enviado_at',
        'finalizado_at',
        'mensaje',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    private Feeding $feeding;

    public function __construct(...$params)
    {
        parent::__construct(...$params);
        $this->feeding = config(Feeding::class);
    }

    public function alimentacionPendiente(int $userId): ?array
    {
        $this->expirarVencidos($userId);

        return $this->where('usuario_id', $userId)
            ->where('accion', self::ACCION_ALIMENTAR)
            ->whereIn('estado', ['pendiente', 'enviado'])
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function ultimaAlimentacion(int $userId): ?array
    {
        return $this->where('usuario_id', $userId)
            ->where('accion', self::ACCION_ALIMENTAR)
            ->orderBy('id', 'DESC')
            ->first();
    }

    public function crearAlimentacionManual(int $userId, float $grams): int
    {
        $now = time();

        return (int) $this->insert([
            'usuario_id' => $userId,
            'accion'     => self::ACCION_ALIMENTAR,
            'parametros' => json_encode(['gramos' => $grams]),
            'origen'     => 'manual',
            'estado'     => 'pendiente',
            'expira_at'  => date('Y-m-d H:i:s', $now + $this->feeding->manualCommandTtlMinutes * 60),
        ]);
    }

    /**
     * Crea los comandos de los horarios de alimentacion que ya vencieron y siguen dentro
     * del margen. Se llama cada vez que el dispositivo consulta la cola, asi que no hace
     * falta un cron. El indice unico (usuario, accion, programado_para) evita duplicados.
     */
    public function programarAlimentaciones(int $userId, array $config): void
    {
        $grams = (float) ($config['cantidad_alim_gramos'] ?? 1);
        $window = $this->feeding->scheduleWindowMinutes * 60;
        $now = time();

        foreach ($this->horariosEnUtc($config) as $scheduledAt) {
            if ($scheduledAt > $now || $now >= $scheduledAt + $window) {
                continue;
            }

            $this->db->table($this->table)->ignore(true)->insert([
                'usuario_id'      => $userId,
                'accion'          => self::ACCION_ALIMENTAR,
                'parametros'      => json_encode(['gramos' => $grams]),
                'origen'          => 'programado',
                'estado'          => 'pendiente',
                'programado_para' => date('Y-m-d H:i:s', $scheduledAt),
                'expira_at'       => date('Y-m-d H:i:s', $scheduledAt + $window),
                'created_at'      => date('Y-m-d H:i:s', $now),
                'updated_at'      => date('Y-m-d H:i:s', $now),
            ]);
        }
    }

    /**
     * Timestamps (UTC) de los horarios configurados para ayer y hoy en la zona local,
     * para cubrir horarios cercanos a la medianoche.
     */
    public function horariosEnUtc(array $config): array
    {
        $zone = new DateTimeZone($this->feeding->timezone);
        $today = new DateTimeImmutable('today', $zone);
        $timestamps = [];

        foreach (self::horasConfiguradas($config) as $time) {
            [$hour, $minute] = array_map('intval', explode(':', $time));

            foreach ([$today->modify('-1 day'), $today] as $day) {
                $timestamps[] = $day->setTime($hour, $minute)->getTimestamp();
            }
        }

        sort($timestamps);

        return $timestamps;
    }

    /**
     * Proximo horario programado, en hora local (H:i), o null si no hay ninguno.
     */
    public function proximoHorario(array $config): ?string
    {
        $zone = new DateTimeZone($this->feeding->timezone);
        $now = time();

        foreach ($this->horariosEnUtc($config) as $timestamp) {
            if ($timestamp > $now) {
                return (new DateTimeImmutable('@' . $timestamp))->setTimezone($zone)->format('H:i');
            }
        }

        $hours = self::horasConfiguradas($config);

        return $hours === [] ? null : $hours[0]; // el primero de maniana
    }

    /**
     * Entrega al dispositivo los comandos pendientes que le corresponden y los marca como
     * enviados. El UPDATE condicionado a estado='pendiente' evita que dos dispositivos
     * tomen el mismo comando.
     */
    public function reclamarPendientes(array $device): array
    {
        $userId = (int) $device['usuario_id'];
        $deviceId = (int) $device['id'];
        $this->expirarVencidos($userId);

        $candidates = $this->where('usuario_id', $userId)
            ->where('estado', 'pendiente')
            ->groupStart()
                ->where('dispositivo_id', null)
                ->orWhere('dispositivo_id', $deviceId)
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->findAll(10);

        $claimed = [];
        foreach ($candidates as $command) {
            $this->db->table($this->table)
                ->where('id', $command['id'])
                ->where('estado', 'pendiente')
                ->update([
                    'estado'         => 'enviado',
                    'dispositivo_id' => $deviceId,
                    'enviado_at'     => date('Y-m-d H:i:s'),
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);

            if ($this->db->affectedRows() === 1) {
                $claimed[] = $command;
            }
        }

        return $claimed;
    }

    /**
     * Registra el resultado informado por el dispositivo. Devuelve el comando actualizado,
     * o null si no existe, no es de ese dispositivo o ya estaba finalizado.
     */
    public function finalizar(int $commandId, int $deviceId, string $status, ?string $message = null): ?array
    {
        $this->db->table($this->table)
            ->where('id', $commandId)
            ->where('dispositivo_id', $deviceId)
            ->where('estado', 'enviado')
            ->update([
                'estado'        => $status,
                'mensaje'       => $message !== null ? mb_substr($message, 0, 255) : null,
                'finalizado_at' => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

        return $this->db->affectedRows() === 1 ? $this->find($commandId) : null;
    }

    public static function parametros(array $command): array
    {
        $params = json_decode((string) ($command['parametros'] ?? ''), true);

        return is_array($params) ? $params : [];
    }

    public static function horasConfiguradas(array $config): array
    {
        $hours = [];
        foreach (['hora_alim_1', 'hora_alim_2'] as $field) {
            $value = (string) ($config[$field] ?? '');
            if (preg_match('/^([01]\d|2[0-3]):[0-5]\d/', $value)) {
                $hours[] = substr($value, 0, 5);
            }
        }

        $hours = array_values(array_unique($hours));
        sort($hours);

        return $hours;
    }

    private function expirarVencidos(int $userId): void
    {
        $this->db->table($this->table)
            ->where('usuario_id', $userId)
            ->where('estado', 'pendiente')
            ->where('expira_at <', date('Y-m-d H:i:s'))
            ->update([
                'estado'        => 'expirado',
                'finalizado_at' => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
    }
}
