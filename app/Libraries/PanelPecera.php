<?php

namespace App\Libraries;

use App\Models\AlertaModel;
use App\Models\AlimentacionModel;
use App\Models\ComandoDispositivoModel;
use App\Models\ConfiguracionPeceraModel;
use App\Models\DispositivoModel;
use App\Models\SensorModel;
use Config\Feeding;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Arma los datos del panel de la pecera de un usuario: tarjetas, grafico, alertas,
 * alimentaciones y estado del alimentador.
 *
 * Lo usan la pagina del panel (para dibujarla) y las respuestas JSON con las que
 * funciones/dashboard.js la refresca cada pocos segundos, asi que los textos se calculan en un
 * solo lugar: aca.
 */
class PanelPecera
{
    /** Puntos maximos del grafico: los rangos largos se promedian para no saturarlo. */
    private const MAX_PUNTOS_GRAFICO = 500;

    /** Puntaje de cada estado para el "Estado del ecosistema" (%). */
    private const PUNTAJES = ['ok' => 98, 'neutral' => 96, 'warn' => 78, 'danger' => 44];

    private ConfiguracionPeceraModel $configModel;
    private DispositivoModel $dispositivoModel;
    private ComandoDispositivoModel $comandoModel;
    private Feeding $feeding;
    private ?array $config = null;
    private ?array $dispositivos = null;

    public function __construct(private readonly int $userId)
    {
        $this->configModel = new ConfiguracionPeceraModel();
        $this->dispositivoModel = new DispositivoModel();
        $this->comandoModel = new ComandoDispositivoModel();
        $this->feeding = config(Feeding::class);
    }

    public function config(): array
    {
        return $this->config ??= $this->configModel->deUsuario($this->userId);
    }

    public function guardarConfig(array $cambios): void
    {
        $this->configModel->guardar($this->userId, $cambios);
        $this->config = null;
    }

    public function dispositivos(): array
    {
        return $this->dispositivos ??= $this->dispositivoModel->porUsuario($this->userId);
    }

    /** Dispositivos que pueden mover el servo (tienen API key y no son sensores puros). */
    public function alimentadores(): array
    {
        return array_values(array_filter($this->dispositivos(), [DispositivoModel::class, 'esAlimentador']));
    }

    /** Primer alimentador con contacto reciente, o null si ninguno esta en linea. */
    public function alimentadorEnLinea(): ?array
    {
        foreach ($this->alimentadores() as $device) {
            $ultima = strtotime((string) ($device['ultima_conexion'] ?? '')) ?: 0;
            if (time() - $ultima <= $this->feeding->onlineThresholdSeconds) {
                return $device;
            }
        }

        return null;
    }

    public function alimentacionPendiente(): ?array
    {
        return $this->comandoModel->alimentacionPendiente($this->userId);
    }

    /**
     * Filtros del historial a partir de la URL (`desde`, `hasta` en AAAA-MM-DD y
     * `dispositivo`). Sin fechas validas se muestran las ultimas 24 h.
     */
    public function filtrosHistorial(string $desde, string $hasta, int $deviceId): array
    {
        $hoy = date('Y-m-d');
        $filtros = [
            'custom'      => false,
            'desde'       => date('Y-m-d', strtotime('-1 day')),
            'hasta'       => $hoy,
            'dispositivo' => null,
            'label'       => '24 h',
            'error'       => null,
            'query'       => [],
        ];

        if ($deviceId > 0) {
            if (in_array($deviceId, array_map('intval', array_column($this->dispositivos(), 'id')), true)) {
                $filtros['dispositivo'] = $deviceId;
                $filtros['query']['dispositivo'] = $deviceId;
            } else {
                $filtros['error'] = 'El dispositivo seleccionado no existe.';
            }
        }

        if ($desde === '' && $hasta === '') {
            return $filtros;
        }

        $desdeFecha = $this->fechaValida($desde);
        $hastaFecha = $this->fechaValida($hasta);

        if (($desde !== '' && $desdeFecha === null) || ($hasta !== '' && $hastaFecha === null)) {
            $filtros['error'] = 'Las fechas deben tener formato AAAA-MM-DD.';

            return $filtros;
        }

        $desdeFecha ??= $hastaFecha;
        $hastaFecha ??= $hoy;

        if ($desdeFecha > $hastaFecha) {
            $filtros['error'] = 'La fecha "desde" no puede ser posterior a "hasta".';

            return $filtros;
        }

        $filtros['custom'] = true;
        $filtros['desde'] = $desdeFecha;
        $filtros['hasta'] = $hastaFecha;
        $filtros['label'] = $desdeFecha === $hastaFecha
            ? date('d/m/Y', strtotime($desdeFecha))
            : date('d/m', strtotime($desdeFecha)) . ' - ' . date('d/m/Y', strtotime($hastaFecha));
        $filtros['query']['desde'] = $desdeFecha;
        $filtros['query']['hasta'] = $hastaFecha;

        return $filtros;
    }

    /** Texto del rango del grafico, p. ej. "24 h" o "29/09 - 01/10/2026 · ESP32 Pecera". */
    public function textoRango(array $filtros): string
    {
        foreach ($this->dispositivos() as $device) {
            if ((int) $device['id'] === $filtros['dispositivo']) {
                return $filtros['label'] . ' · ' . $device['nombre'];
            }
        }

        return $filtros['label'];
    }

    /** Todo lo que muestra el panel. */
    public function datos(array $filtros): array
    {
        $config = $this->config();
        $ultima = (new SensorModel())->ultimaLectura($this->userId);
        $alimentaciones = array_map([$this, 'alimentacion'], (new AlimentacionModel())->ultimasPorUsuario($this->userId, 10));
        $alertas = $this->alertas();
        $tarjetas = $this->tarjetas($ultima, $config, $alimentaciones[0] ?? $this->alimentacion(null));

        return [
            'config'          => $config,
            'cards'           => $tarjetas,
            'summary'         => $this->resumen($tarjetas, count($alertas)) + ['syncText' => $this->haceCuanto($ultima['created_at'] ?? null)],
            'alerts'          => $alertas,
            'feedings'        => $alimentaciones,
            'charts'          => $this->grafico($filtros),
            'feeder'          => $this->estadoAlimentador($config),
            'latestTimestamp' => $ultima['created_at'] ?? null,
        ];
    }

    private function tarjetas(?array $ultima, array $config, array $ultimaAlimentacion): array
    {
        $temperatura = $ultima['temperatura'] ?? null;
        $ph = $ultima['ph'] ?? null;
        $estadoPh = $this->estadoSegunRango($ph, (float) $config['ph_min'], (float) $config['ph_max']);
        $aguaOk = (int) ($ultima['nivel_agua'] ?? 0) === 1;
        $calefactor = (int) ($ultima['calefactor'] ?? 0) === 1;
        $vacaciones = (int) ($config['modo_vacaciones'] ?? 0) === 1;

        return [
            'temperature' => [
                'value'  => $temperatura !== null ? number_format((float) $temperatura, 1) . ' °C' : '--',
                'status' => $this->estadoSegunRango($temperatura, (float) $config['temp_min'], (float) $config['temp_max']),
                'meta'   => sprintf('Rango ideal %.1f - %.1f °C', $config['temp_min'], $config['temp_max']),
            ],
            'ph' => [
                'value'  => $ph !== null ? number_format((float) $ph, 2) : '--',
                'status' => $estadoPh,
                'meta'   => $estadoPh === 'ok' ? 'Agua estable' : sprintf('Rango ideal %.2f - %.2f', $config['ph_min'], $config['ph_max']),
            ],
            'waterLevel' => [
                'value'  => $aguaOk ? 'OK' : 'Bajo',
                'status' => $aguaOk ? 'ok' : 'danger',
                'meta'   => $aguaOk ? 'Nivel estable' : 'Revisar rellenado',
            ],
            'heater' => [
                'value'  => $calefactor ? 'Encendido' : 'Apagado',
                'status' => $calefactor ? 'ok' : 'neutral',
                'meta'   => 'Control termico automatico',
            ],
            'lastFeeding'  => $ultimaAlimentacion,
            'vacationMode' => [
                'value'  => $vacaciones ? 'Activo' : 'Inactivo',
                'status' => $vacaciones ? 'ok' : 'neutral',
                'meta'   => $vacaciones ? 'Rutinas automaticas habilitadas' : 'Modo manual activo',
            ],
        ];
    }

    /** Textos de resumen del panel (estado del ecosistema, alertas y tarjetas de estado). */
    private function resumen(array $tarjetas, int $cantidadAlertas): array
    {
        $estados = array_map(
            static fn (string $tarjeta): string => $tarjetas[$tarjeta]['status'],
            ['temperature', 'ph', 'waterLevel', 'heater', 'vacationMode']
        );
        $promedio = array_sum(array_map(static fn (string $estado): int => self::PUNTAJES[$estado] ?? self::PUNTAJES['neutral'], $estados)) / count($estados);
        $alimentacion = $tarjetas['lastFeeding'];
        $sinAlimentar = $alimentacion['value'] === '--';

        return [
            'alertsCount'   => $cantidadAlertas,
            'alertsMeta'    => match ($cantidadAlertas) {
                0       => 'Sin eventos criticos',
                1       => '1 evento pendiente',
                default => "{$cantidadAlertas} eventos pendientes",
            },
            'health'        => max(0, min(100, (int) round($promedio) - $cantidadAlertas * 6)),
            'waterTitle'    => $tarjetas['waterLevel']['status'] === 'ok' ? 'Agua clara' : 'Revisar nivel',
            'feedingTitle'  => $sinAlimentar ? 'Alimentacion pendiente' : 'Ultima alimentacion',
            'feedingMeta'   => $sinAlimentar ? $alimentacion['meta'] : $alimentacion['value'] . ' - ' . $alimentacion['meta'],
            'vacationTitle' => $tarjetas['vacationMode']['value'] === 'Activo' ? 'Modo Ausencia listo' : 'Modo manual activo',
        ];
    }

    private function grafico(array $filtros): array
    {
        if ($filtros['custom']) {
            [$desde, $hasta] = [$filtros['desde'] . ' 00:00:00', $filtros['hasta'] . ' 23:59:59'];
        } else {
            [$desde, $hasta] = [date('Y-m-d H:i:s', strtotime('-24 hours')), date('Y-m-d H:i:s')];
        }

        $serie = (new SensorModel())->serieParaGrafico($this->userId, $desde, $hasta, $filtros['dispositivo'], self::MAX_PUNTOS_GRAFICO);
        $formato = ($filtros['custom'] && $filtros['desde'] !== $filtros['hasta']) ? 'd/m H:i' : 'H:i';
        $grafico = ['labels' => [], 'temperature' => [], 'ph' => [], 'count' => $serie['total']];

        foreach ($serie['puntos'] as $punto) {
            $grafico['labels'][] = date($formato, strtotime($punto['created_at']));
            $grafico['temperature'][] = $punto['temperatura'] !== null ? (float) $punto['temperatura'] : null;
            $grafico['ph'][] = $punto['ph'] !== null ? (float) $punto['ph'] : null;
        }

        return $grafico;
    }

    private function alertas(): array
    {
        return array_map(static fn (array $alerta): array => [
            'id'         => (int) $alerta['id'],
            'nivel'      => (int) $alerta['nivel'],
            'tipo'       => $alerta['tipo'],
            'mensaje'    => $alerta['mensaje'],
            'created_at' => $alerta['created_at'],
            'time'       => date('d/m H:i', strtotime($alerta['created_at'])),
        ], (new AlertaModel())->noLeidasPorUsuario($this->userId, 5));
    }

    /** Una alimentacion lista para mostrar (o el texto "Sin registros" si no hay ninguna). */
    private function alimentacion(?array $fila): array
    {
        if ($fila === null) {
            return [
                'value'      => '--',
                'status'     => 'neutral',
                'meta'       => 'Sin registros',
                'created_at' => null,
                'fecha'      => null,
                'grams'      => null,
                'type'       => null,
                'tipoTexto'  => null,
            ];
        }

        $tipo = AlimentacionModel::TIPOS[$fila['tipo']] ?? ucfirst($fila['tipo']);
        $gramos = (float) $fila['cantidad_gramos'];

        return [
            'value'      => date('H:i', strtotime($fila['created_at'])),
            'status'     => 'ok',
            'meta'       => $tipo . ' · ' . number_format($gramos, 2) . ' g',
            'created_at' => $fila['created_at'],
            'fecha'      => date('d/m/Y H:i', strtotime($fila['created_at'])),
            'grams'      => $gramos,
            'type'       => $fila['tipo'],
            'tipoTexto'  => $tipo,
        ];
    }

    private function estadoAlimentador(array $config): array
    {
        $alimentadores = $this->alimentadores();
        $enLinea = $this->alimentadorEnLinea();
        $pendiente = $this->alimentacionPendiente();
        $ultima = $this->comandoModel->ultimaAlimentacion($this->userId);
        $nombre = ($enLinea ?? $alimentadores[0] ?? [])['nombre'] ?? null;

        if ($alimentadores === []) {
            [$nivel, $texto] = ['danger', 'Sin alimentador vinculado. Registra el ESP32 en Dispositivos y generale una API key.'];
        } elseif ($pendiente !== null) {
            [$nivel, $texto] = ['warn', $pendiente['estado'] === 'enviado'
                ? 'El alimentador recibio la orden y esta moviendo el servo...'
                : 'Orden en cola: esperando que el alimentador la tome.'];
        } elseif ($enLinea !== null) {
            [$nivel, $texto] = ['ok', 'Conectado: ' . $nombre];
        } else {
            [$nivel, $texto] = ['warn', $nombre . ' sin contacto reciente (offline).'];
        }

        $resultados = ['ejecutado' => 'ejecutada', 'fallido' => 'fallo', 'expirado' => 'expiro sin respuesta'];
        $ultimaTexto = null;
        if ($ultima !== null && isset($resultados[$ultima['estado']])) {
            $ultimaTexto = sprintf(
                'Ultima orden (%s): %s %s',
                $ultima['origen'] === 'programado' ? 'programada' : 'manual',
                $resultados[$ultima['estado']],
                $this->horaLocal($ultima['finalizado_at'] ?? $ultima['created_at'])
            );
        }

        $horarios = ComandoDispositivoModel::horasConfiguradas($config);

        return [
            'hasDevice'    => $alimentadores !== [],
            'online'       => $enLinea !== null,
            'pending'      => $pendiente !== null,
            'statusLevel'  => $nivel,
            'statusText'   => $texto,
            'lastText'     => $ultimaTexto,
            'scheduleText' => $horarios === []
                ? 'Sin horarios programados.'
                : sprintf(
                    'Programado: %s (%s g por racion, hora Argentina). Proxima: %s.',
                    implode(' y ', $horarios),
                    rtrim(rtrim(number_format((float) $config['cantidad_alim_gramos'], 2, '.', ''), '0'), '.'),
                    $this->comandoModel->proximoHorario($config)
                ),
        ];
    }

    /** 'ok' dentro del rango, 'warn' cerca del borde, 'danger' lejos, 'neutral' sin dato. */
    private function estadoSegunRango($valor, float $min, float $max): string
    {
        if ($valor === null || $valor === '') {
            return 'neutral';
        }

        $valor = (float) $valor;
        if ($valor >= $min && $valor <= $max) {
            return 'ok';
        }

        $margen = max(($max - $min) * 0.25, 0.2);

        return ($valor >= $min - $margen && $valor <= $max + $margen) ? 'warn' : 'danger';
    }

    /** "hace 12 s", "hace 3 min" o la fecha completa si paso mas de una hora (igual que funciones/dashboard.js). */
    private function haceCuanto(?string $fecha): string
    {
        if (empty($fecha)) {
            return 'Sin lecturas';
        }

        $segundos = max(0, time() - strtotime($fecha));

        return match (true) {
            $segundos < 60   => "hace {$segundos} s",
            $segundos < 3600 => 'hace ' . intdiv($segundos, 60) . ' min',
            default          => date('d/m/Y H:i', strtotime($fecha)),
        };
    }

    /** Fecha guardada en la base, mostrada en la zona horaria del alimentador. */
    private function horaLocal(?string $fecha): string
    {
        if (empty($fecha)) {
            return '';
        }

        return (new DateTimeImmutable($fecha))
            ->setTimezone(new DateTimeZone($this->feeding->timezone))
            ->format('d/m H:i');
    }

    private function fechaValida(string $valor): ?string
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return ($fecha && $fecha->format('Y-m-d') === $valor) ? $valor : null;
    }
}
