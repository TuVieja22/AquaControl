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
 * Un pendiente que nadie toma antes de `expira_at` pasa a expirado, y un enviado que
 * el ESP32 no confirma en SEGUNDOS_PARA_CONFIRMAR pasa a fallido. La entrega es
 * "a lo sumo una vez": un comando enviado no se reenvia (mejor saltear una racion
 * que alimentar dos veces).
 */
class ComandoDispositivoModel extends Model
{
    // Unica orden que existe por ahora: mover el servo del alimentador.
    public const ACCION_ALIMENTAR = 'alimentar';
    // Orden de prender su red WiFi (para conectar el AquaControl a otra red de la casa).
    public const ACCION_CONFIGURAR = 'configurar';

    /** Minutos que espera la orden de prender su red (el ESP32 pregunta cada 2 s). */
    private const MINUTOS_PEDIDO_CONFIGURAR = 2;

    /** Segundos que tiene el ESP32 para confirmar una orden enviada (el giro tarda unos 3 s). */
    private const SEGUNDOS_PARA_CONFIRMAR = 60;

    // Tabla, clave y columnas que se pueden escribir.
    protected $table            = 'comandos_dispositivo';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'usuario_id',
        'dispositivo_id',
        'accion',
        'cantidad_gramos',
        'origen',
        'estado',
        'programado_para',
        'expira_at',
        'enviado_at',
        'finalizado_at',
        'mensaje',
    ];

    // La base anota sola cuando se creo y cuando cambio cada orden.
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Tiempos y limites del alimentador (app/Config/Feeding.php).
    private Feeding $feeding;

    // Al crear el modelo se lee esa configuracion.
    public function __construct(...$params)
    {
        parent::__construct(...$params);
        $this->feeding = config(Feeding::class);
    }

    // Orden de alimentar que todavia no termino (en cola o ya enviada), si hay alguna.
    public function alimentacionPendiente(int $userId): ?array
    {
        $this->expirarVencidos($userId);

        return $this->where('usuario_id', $userId)
            ->where('accion', self::ACCION_ALIMENTAR)
            ->whereIn('estado', ['pendiente', 'enviado'])
            ->orderBy('id', 'DESC')
            ->first();
    }

    // Ultima orden de alimentar, sin importar como termino.
    public function ultimaAlimentacion(int $userId): ?array
    {
        return $this->where('usuario_id', $userId)
            ->where('accion', self::ACCION_ALIMENTAR)
            ->orderBy('id', 'DESC')
            ->first();
    }

    // Crea la orden de "Alimentar ahora"; vence sola si nadie la toma a tiempo.
    public function crearAlimentacionManual(int $userId, float $grams): int
    {
        $now = time();

        return (int) $this->insert([
            'usuario_id'      => $userId,
            'accion'          => self::ACCION_ALIMENTAR,
            'cantidad_gramos' => round($grams, 2),
            'origen'          => 'manual',
            'estado'          => 'pendiente',
            'expira_at'       => date('Y-m-d H:i:s', $now + $this->feeding->manualCommandTtlMinutes * 60),
        ]);
    }

    // Crea la orden de prender su red WiFi para un dispositivo (boton "Cambiar WiFi").
    public function crearPedidoConfiguracion(int $userId, int $deviceId): int
    {
        return (int) $this->insert([
            'usuario_id'     => $userId,
            'dispositivo_id' => $deviceId,
            'accion'         => self::ACCION_CONFIGURAR,
            'origen'         => 'manual',
            'estado'         => 'pendiente',
            'expira_at'      => date('Y-m-d H:i:s', time() + self::MINUTOS_PEDIDO_CONFIGURAR * 60),
        ]);
    }

    /**
     * Crea los comandos de los horarios de alimentacion que ya vencieron y siguen dentro
     * del margen. Se llama cada vez que el dispositivo consulta la cola, asi que no hace
     * falta un cron. El indice unico (usuario, accion, programado_para) evita duplicados.
     */
    public function programarAlimentaciones(int $userId, array $config): void
    {
        // Gramos por racion, margen permitido y hora actual.
        $grams = (float) ($config['cantidad_alim_gramos'] ?? 1);
        $window = $this->feeding->scheduleWindowMinutes * 60;
        $now = time();

        // Recorre los horarios: solo sirven los que ya pasaron y siguen dentro del margen.
        foreach ($this->horariosEnUtc($config) as $scheduledAt) {
            if ($scheduledAt > $now || $now >= $scheduledAt + $window) {
                continue;
            }

            // Crea la orden (si ya existia, la base la ignora).
            $this->db->table($this->table)->ignore(true)->insert([
                'usuario_id'      => $userId,
                'accion'          => self::ACCION_ALIMENTAR,
                'cantidad_gramos' => round($grams, 2),
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
        // Dia de hoy en la zona horaria del alimentador.
        $zone = new DateTimeZone($this->feeding->timezone);
        $today = new DateTimeImmutable('today', $zone);
        $timestamps = [];

        // Cada horario se convierte a fecha y hora de ayer y de hoy.
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

        // Devuelve el primero que todavia no paso.
        foreach ($this->horariosEnUtc($config) as $timestamp) {
            if ($timestamp > $now) {
                return (new DateTimeImmutable('@' . $timestamp))->setTimezone($zone)->format('H:i');
            }
        }

        // Si hoy ya pasaron todos, el proximo es el primero del dia siguiente.
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
        // Primero se dan de baja las ordenes vencidas.
        $userId = (int) $device['usuario_id'];
        $deviceId = (int) $device['id'];
        $this->expirarVencidos($userId);

        // Ordenes pendientes del usuario, sin dispositivo fijo o para este mismo.
        $candidates = $this->where('usuario_id', $userId)
            ->where('estado', 'pendiente')
            ->groupStart()
                ->where('dispositivo_id', null)
                ->orWhere('dispositivo_id', $deviceId)
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->findAll(10);

        // Se marcan como enviadas una por una; solo cuentan las que este pedido logro marcar.
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
        // Solo se cierra si es de este dispositivo y estaba enviada.
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

    // Datos extra que se le mandan al dispositivo con la orden (hoy, los gramos).
    public static function parametros(array $command): array
    {
        return isset($command['cantidad_gramos']) ? ['gramos' => (float) $command['cantidad_gramos']] : [];
    }

    // Horarios validos de la configuracion (su lista 'horarios'), como "HH:MM", sin repetir y ordenados.
    public static function horasConfiguradas(array $config): array
    {
        $hours = [];
        foreach ($config['horarios'] ?? [] as $value) {
            if (preg_match('/^([01]\d|2[0-3]):[0-5]\d/', (string) $value)) {
                $hours[] = substr((string) $value, 0, 5);
            }
        }

        $hours = array_values(array_unique($hours));
        sort($hours);

        return $hours;
    }

    // Pasa a "expirado" las ordenes pendientes cuyo plazo ya vencio, y a "fallido" las enviadas
    // que el ESP32 nunca confirmo (se reinicio o se desconecto a mitad del giro): si no, quedan
    // "en curso" para siempre y no dejan pedir otra.
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

        $this->db->table($this->table)
            ->where('usuario_id', $userId)
            ->where('estado', 'enviado')
            ->where('enviado_at <', date('Y-m-d H:i:s', time() - self::SEGUNDOS_PARA_CONFIRMAR))
            ->update([
                'estado'        => 'fallido',
                'mensaje'       => 'El AquaControl no confirmó la orden (se reinició o perdió la conexión).',
                'finalizado_at' => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
    }
}
