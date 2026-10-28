<?php

namespace App\Libraries;

use App\Models\AlertaModel;
use App\Models\ConfiguracionPeceraModel;
use App\Models\DispositivoModel;
use App\Models\SuscripcionPushModel;
use Throwable;

/**
 * Crea las alertas de la pecera y las manda como notificacion al celular.
 *
 *   - Temperatura o pH fuera del rango configurado, o nivel de agua bajo (con cada lectura).
 *   - Una alimentacion que el ESP32 no pudo dar.
 *   - Un AquaControl que dejo de mandar datos (lo revisa `php spark alertas:revisar`).
 *
 * El mismo aviso no se repite mientras siga sin leer (ver MINUTOS_SIN_REPETIR). Las
 * notificaciones salen aparte (enviarPendientes), para no demorar las respuestas al ESP32.
 */
class Alertas
{
    /** Minutos en los que no se repite una alerta igual que siga sin leer. */
    private const MINUTOS_SIN_REPETIR = 30;

    /** Minutos sin datos para avisar que un AquaControl se desconecto. */
    public const MINUTOS_DESCONECTADO = 5;

    // Niveles de la tabla alertas.
    private const AVISO = 2;
    private const CRITICO = 3;

    private AlertaModel $alertas;

    public function __construct()
    {
        $this->alertas = new AlertaModel();
    }

    /** Revisa una lectura recien llegada contra los rangos de la configuracion del usuario. */
    public function revisarLectura(int $userId, array $lectura): void
    {
        $config = (new ConfiguracionPeceraModel())->deUsuario($userId);

        // Temperatura fuera del rango ideal.
        if (isset($lectura['temperatura']) && $lectura['temperatura'] !== '') {
            $nivel = $this->nivelFueraDeRango((float) $lectura['temperatura'], (float) $config['temp_min'], (float) $config['temp_max']);
            if ($nivel !== null) {
                $this->crear($userId, 'temperatura', $nivel, sprintf(
                    'La temperatura del agua está en %s °C (rango ideal %s - %s °C).',
                    self::numero((float) $lectura['temperatura'], 1),
                    self::numero((float) $config['temp_min'], 1),
                    self::numero((float) $config['temp_max'], 1)
                ));
            }
        }

        // pH fuera del rango ideal.
        if (isset($lectura['ph']) && $lectura['ph'] !== '') {
            $nivel = $this->nivelFueraDeRango((float) $lectura['ph'], (float) $config['ph_min'], (float) $config['ph_max']);
            if ($nivel !== null) {
                $this->crear($userId, 'ph', $nivel, sprintf(
                    'El pH del agua está en %s (rango ideal %s - %s).',
                    self::numero((float) $lectura['ph'], 2),
                    self::numero((float) $config['ph_min'], 2),
                    self::numero((float) $config['ph_max'], 2)
                ));
            }
        }

        // Nivel de agua bajo (solo si el ESP32 lo mando).
        if (isset($lectura['nivel_agua']) && (string) $lectura['nivel_agua'] === '0') {
            $this->crear($userId, 'nivel_agua', self::CRITICO, 'El nivel de agua de la pecera está bajo: hay que rellenarla.');
        }
    }

    /** El ESP32 avisa que no pudo dar una comida. */
    public function alimentacionFallida(int $userId, string $nombreDispositivo, ?string $motivo): void
    {
        $texto = $nombreDispositivo . ' no pudo dar la comida';
        $this->crear($userId, 'sistema', self::AVISO, $texto . ($motivo ? ': ' . $motivo : '.'), $texto);
    }

    /**
     * Avisa de cada AquaControl vinculado que dejo de mandar datos (una sola vez por
     * desconexion: si ya hay una alerta posterior a su ultima conexion, no se repite).
     */
    public function revisarDesconectados(): int
    {
        $limite = date('Y-m-d H:i:s', time() - self::MINUTOS_DESCONECTADO * 60);
        $dispositivos = (new DispositivoModel())
            ->where('api_key_hash IS NOT NULL')
            ->where('ultima_conexion <', $limite)
            ->findAll();

        $creadas = 0;
        foreach ($dispositivos as $device) {
            $comienzo = $device['nombre'] . ' dejó de enviar datos';
            $yaAvisado = $this->alertas->where('usuario_id', $device['usuario_id'])
                ->where('tipo', 'sistema')
                ->where('created_at >', $device['ultima_conexion'])
                ->like('mensaje', $comienzo, 'after')
                ->countAllResults() > 0;
            if ($yaAvisado) {
                continue;
            }

            $this->alertas->insert([
                'usuario_id' => (int) $device['usuario_id'],
                'nivel'      => self::AVISO,
                'tipo'       => 'sistema',
                'mensaje'    => $comienzo . ' (última conexión ' . date('d/m H:i', strtotime($device['ultima_conexion'])) . '). ¿Se cortó la luz o el WiFi?',
                'leida'      => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $creadas++;
        }

        return $creadas;
    }

    /**
     * Manda como notificacion las alertas que todavia no salieron, a todos los navegadores
     * suscriptos de cada usuario. Devuelve cuantas notificaciones se entregaron.
     */
    public function enviarPendientes(): int
    {
        $webPush = new WebPush();
        $suscripciones = new SuscripcionPushModel();
        $entregadas = 0;

        foreach ($this->alertas->sinNotificar() as $alerta) {
            // Sin claves VAPID no se puede mandar nada: quedan anotadas igual (se ven en el panel).
            if ($webPush->configurado()) {
                foreach ($suscripciones->deUsuario((int) $alerta['usuario_id']) as $suscripcion) {
                    $entregadas += $this->enviarUna($webPush, $suscripciones, $suscripcion, $alerta);
                }
            }
            $this->alertas->marcarNotificada((int) $alerta['id']);
        }

        return $entregadas;
    }

    /** Manda una notificacion de prueba a todos los navegadores del usuario. */
    public function enviarPrueba(int $userId): int
    {
        $webPush = new WebPush();
        $suscripciones = new SuscripcionPushModel();
        $alerta = ['nivel' => 1, 'mensaje' => 'Las notificaciones de tu pecera funcionan.'];
        $entregadas = 0;
        foreach ($suscripciones->deUsuario($userId) as $suscripcion) {
            $entregadas += $this->enviarUna($webPush, $suscripciones, $suscripcion, $alerta);
        }

        return $entregadas;
    }

    // Manda una alerta a un navegador. Devuelve 1 si se entrego.
    private function enviarUna(WebPush $webPush, SuscripcionPushModel $suscripciones, array $suscripcion, array $alerta): int
    {
        try {
            $codigo = $webPush->enviar($suscripcion, [
                'titulo'  => (int) $alerta['nivel'] >= self::CRITICO ? '⚠️ AquaControl: ¡atención!' : 'AquaControl',
                'mensaje' => $alerta['mensaje'],
                'url'     => base_url('dashboard'),
            ]);
        } catch (Throwable $error) {
            log_message('error', 'No se pudo mandar la notificacion: ' . $error->getMessage());

            return 0;
        }

        // El navegador ya no acepta avisos (desinstalaron la app o borraron los permisos).
        if ($codigo === 404 || $codigo === 410) {
            $suscripciones->borrarPorEndpoint($suscripcion['endpoint']);
        } elseif ($codigo >= 300) {
            log_message('error', "El servicio de push respondio {$codigo} para la suscripcion {$suscripcion['id']}.");
        }

        return $codigo < 300 ? 1 : 0;
    }

    // Guarda una alerta, salvo que ya haya una igual sin leer de hace poco.
    private function crear(int $userId, string $tipo, int $nivel, string $mensaje, string $comienzo = ''): void
    {
        if ($this->alertas->hayParecidaReciente($userId, $tipo, self::MINUTOS_SIN_REPETIR, $comienzo)) {
            return;
        }

        $this->alertas->insert([
            'usuario_id' => $userId,
            'nivel'      => $nivel,
            'tipo'       => $tipo,
            'mensaje'    => $mensaje,
            'leida'      => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** null si el valor esta dentro del rango; aviso si esta cerca del borde; critico si esta lejos. */
    private function nivelFueraDeRango(float $valor, float $min, float $max): ?int
    {
        if ($valor >= $min && $valor <= $max) {
            return null;
        }
        // Mismo margen que usa el panel para pintar en amarillo o en rojo (PanelPecera).
        $margen = max(($max - $min) * 0.25, 0.2);

        return ($valor >= $min - $margen && $valor <= $max + $margen) ? self::AVISO : self::CRITICO;
    }

    // Numero con coma decimal, como se escribe en Argentina.
    private static function numero(float $valor, int $decimales): string
    {
        return number_format($valor, $decimales, ',', '.');
    }
}
