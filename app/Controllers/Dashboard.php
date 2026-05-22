<?php

namespace App\Controllers;

use App\Models\AlertaModel;
use App\Models\AlimentacionModel;
use App\Models\ConfiguracionPeceraModel;
use App\Models\SensorModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;



class Dashboard extends BaseController
{
    private const DEFAULT_CONFIG = [
        'temp_min'        => 24.00,
        'temp_max'        => 27.00,
        'ph_min'          => 6.80,
        'ph_max'          => 7.60,
        'temp_objetivo'   => 25.50,
        'modo_vacaciones' => 0,
    ];

    private SensorModel $sensorModel;
    private AlimentacionModel $alimentacionModel;
    private AlertaModel $alertaModel;
    private ConfiguracionPeceraModel $configuracionModel;
    private BaseConnection $database;

    public function __construct()
    {
        $this->database = db_connect();
        $this->sensorModel = new SensorModel();
        $this->alimentacionModel = new AlimentacionModel();
        $this->alertaModel = new AlertaModel();
        $this->configuracionModel = new ConfiguracionPeceraModel();
    }

   

    public function index(): string
    {
        return view('dashboard/index', $this->buildViewData('overview'));
    }

    public function history(): string
    {
        return view('dashboard/index', $this->buildViewData('history'));
    }

    public function settings(): string
    {
        return view('dashboard/index', $this->buildViewData('settings'));
    }

    public function latest(): ResponseInterface
    {
        return $this->dashboardResponse($this->userId());
    }

    public function markAlertRead(int $alertId): ResponseInterface
    {
        $userId = $this->userId();

        if ($this->tableExists('alertas')) {
            $alert = $this->alertaModel
                ->where('id', $alertId)
                ->where('usuario_id', $userId)
                ->first();

            if ($alert) {
                $this->alertaModel->update($alertId, ['leida' => 1]);
            }
        }

        return $this->dashboardResponse($userId, ['success' => true]);
    }

    public function feedNow(): ResponseInterface
    {
        $userId = $this->userId();

        if ($this->tableExists('alimentaciones')) {
            $this->alimentacionModel->insert([
                'usuario_id'      => $userId,
                'cantidad_gramos' => (float) ($this->request->getPost('cantidad_gramos') ?? 5),
                'tipo'            => 'manual',
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->dashboardResponse($userId, ['success' => true]);
    }

    public function toggleVacation(): ResponseInterface
    {
        $userId = $this->userId();
        $config = $this->ensureConfig($userId);
        $nextState = (int) ! ((int) ($config['modo_vacaciones'] ?? 0));

        $this->saveConfig($userId, ['modo_vacaciones' => $nextState]);

        return $this->dashboardResponse($userId, ['success' => true]);
    }

    public function updateTargetTemperature(): ResponseInterface
    {
        $userId = $this->userId();

        $this->saveConfig($userId, [
            'temp_objetivo' => round((float) $this->request->getPost('temp_objetivo'), 2),
        ]);

        return $this->dashboardResponse($userId, ['success' => true]);
    }

    public function receiveData(): ResponseInterface
    {
        if (! $this->tableExists('lecturas_sensores')) {
            return $this->response
                ->setStatusCode(503)
                ->setJSON([
                    'success' => false,
                    'message' => 'La tabla lecturas_sensores no existe.',
                ]);
        }

        $userId = $this->userId();
        $input = $this->request->getJSON(true) ?: $this->request->getPost();

        $this->sensorModel->insert([
            'usuario_id'      => $userId,
            'temperatura'     => $input['temperatura'] ?? null,
            'ph'              => $input['ph'] ?? null,
            'turbidez'        => $input['turbidez'] ?? null,
            'nivel_agua'      => isset($input['nivel_agua']) ? (int) $input['nivel_agua'] : 1,
            'calefactor'      => isset($input['calefactor']) ? (int) $input['calefactor'] : 0,
            'modo_vacaciones' => isset($input['modo_vacaciones']) ? (int) $input['modo_vacaciones'] : 0,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        return $this->dashboardResponse($userId, ['success' => true]);
    }

    private function buildViewData(string $activeSection): array
    {
        $userId = $this->userId();

        return [
            'title'         => 'Dashboard',
            'extraCss'      => ['css/dashboard.css'],
            'extraJs'       => ['js/dashboard.js'],
            'activeSection' => $activeSection,
            'dashboardData' => array_merge($this->buildDashboardPayload($userId), [
                'userName'  => (string) session()->get('user_nombre'),
                'endpoints' => $this->buildEndpoints(),
            ]),
        ];
    }

    private function buildDashboardPayload(int $userId): array
    {
        $config = $this->ensureConfig($userId);
        $latest = $this->fetchLatestReading($userId);

        return [
            'config'          => $config,
            'cards'           => $this->buildCards($latest, $config, $this->fetchLastFeeding($userId)),
            'alerts'          => $this->fetchAlerts($userId),
            'feedings'        => $this->fetchFeedings($userId),
            'charts'          => $this->fetchSensorHistory($userId),
            'latestTimestamp' => $latest['created_at'] ?? null,
        ];
    }

    private function buildEndpoints(): array
    {
        return [
            'latest'            => base_url('dashboard/api/latest'),
            'feed'              => base_url('dashboard/control/feed'),
            'vacationToggle'    => base_url('dashboard/control/vacation-toggle'),
            'targetTemperature' => base_url('dashboard/control/target-temperature'),
            'markAlertTemplate' => base_url('dashboard/alerts/__id__/read'),
        ];
    }

    private function dashboardResponse(int $userId, array $extra = []): ResponseInterface
    {
        return $this->response->setJSON(array_merge($this->buildDashboardPayload($userId), $extra));
    }

    private function buildCards(?array $latest, array $config, ?array $lastFeeding): array
    {
        $temperature = $latest['temperatura'] ?? null;
        $ph = $latest['ph'] ?? null;
        $vacationMode = (int) ($config['modo_vacaciones'] ?? $latest['modo_vacaciones'] ?? 0) === 1;

        return [
            'temperature' => [
                'value'  => $temperature !== null ? number_format((float) $temperature, 1) . ' °C' : '--',
                'raw'    => $temperature !== null ? (float) $temperature : null,
                'status' => $this->rangeStatus($temperature, (float) $config['temp_min'], (float) $config['temp_max']),
                'meta'   => sprintf(
                    'Optimo: %.1f - %.1f °C',
                    (float) $config['temp_min'],
                    (float) $config['temp_max']
                ),
            ],
            'ph' => [
                'value'  => $ph !== null ? number_format((float) $ph, 2) : '--',
                'raw'    => $ph !== null ? (float) $ph : null,
                'status' => $this->rangeStatus($ph, (float) $config['ph_min'], (float) $config['ph_max']),
                'meta'   => sprintf(
                    'Optimo: %.2f - %.2f',
                    (float) $config['ph_min'],
                    (float) $config['ph_max']
                ),
            ],
            'waterLevel' => [
                'value'  => ((int) ($latest['nivel_agua'] ?? 0) === 1) ? 'OK' : 'Bajo',
                'status' => ((int) ($latest['nivel_agua'] ?? 0) === 1) ? 'ok' : 'danger',
                'meta'   => ((int) ($latest['nivel_agua'] ?? 0) === 1) ? 'Nivel estable' : 'Revisar rellenado',
            ],
            'heater' => [
                'value'  => ((int) ($latest['calefactor'] ?? 0) === 1) ? 'Encendido' : 'Apagado',
                'status' => ((int) ($latest['calefactor'] ?? 0) === 1) ? 'ok' : 'neutral',
                'meta'   => 'Control termico automatico',
            ],
            'lastFeeding' => $this->formatFeeding($lastFeeding),
            'vacationMode' => [
                'value'  => $vacationMode ? 'Activo' : 'Inactivo',
                'status' => $vacationMode ? 'ok' : 'neutral',
                'meta'   => $vacationMode ? 'Rutinas automaticas habilitadas' : 'Modo manual activo',
                'raw'    => $vacationMode,
            ],
        ];
    }

    private function fetchLatestReading(int $userId): ?array
    {
        return $this->fromTable(
            'lecturas_sensores',
            fn (): ?array => $this->sensorModel->ultimaLectura($userId),
            null
        );
    }

    private function fetchSensorHistory(int $userId): array
    {
        return $this->fromTable('lecturas_sensores', function () use ($userId): array {
            $history = $this->emptyHistory();

            foreach ($this->sensorModel->historial($userId, 24) as $row) {
                $history['labels'][] = date('H:i', strtotime($row['created_at']));
                $history['temperature'][] = $row['temperatura'] !== null ? (float) $row['temperatura'] : null;
                $history['ph'][] = $row['ph'] !== null ? (float) $row['ph'] : null;
            }

            return $history;
        }, $this->emptyHistory());
    }

    private function fetchAlerts(int $userId): array
    {
        return $this->fromTable('alertas', function () use ($userId): array {
            return array_map(function (array $alert): array {
                return [
                    'id'         => (int) $alert['id'],
                    'nivel'      => (int) $alert['nivel'],
                    'tipo'       => $alert['tipo'],
                    'mensaje'    => $alert['mensaje'],
                    'created_at' => $alert['created_at'],
                    'time'       => date('d/m H:i', strtotime($alert['created_at'])),
                ];
            }, $this->alertaModel->noLeidasPorUsuario($userId, 5));
        }, []);
    }

    private function fetchFeedings(int $userId): array
    {
        return $this->fromTable('alimentaciones', function () use ($userId): array {
            return array_map([$this, 'formatFeeding'], $this->alimentacionModel->ultimasPorUsuario($userId, 10));
        }, []);
    }

    private function fetchLastFeeding(int $userId): ?array
    {
        return $this->fromTable(
            'alimentaciones',
            fn (): ?array => $this->alimentacionModel->ultimaPorUsuario($userId),
            null
        );
    }

    private function formatFeeding(?array $feeding): array
    {
        if ($feeding === null) {
            return [
                'value'      => '--',
                'status'     => 'neutral',
                'meta'       => 'Sin registros',
                'created_at' => null,
                'grams'      => null,
                'type'       => null,
            ];
        }

        $typeMap = [
            'manual'     => 'Manual',
            'automatica' => 'Automatica',
            'vacaciones' => 'Vacaciones',
        ];

        return [
            'value'      => date('H:i', strtotime($feeding['created_at'])),
            'status'     => 'ok',
            'meta'       => ($typeMap[$feeding['tipo']] ?? ucfirst($feeding['tipo'])) . ' · ' . number_format((float) $feeding['cantidad_gramos'], 2) . ' g',
            'created_at' => $feeding['created_at'],
            'grams'      => isset($feeding['cantidad_gramos']) ? (float) $feeding['cantidad_gramos'] : null,
            'type'       => $feeding['tipo'] ?? null,
        ];
    }

    private function ensureConfig(int $userId, bool $refresh = false): array
    {
        static $cache = [];

        if (! $refresh && isset($cache[$userId])) {
            return $cache[$userId];
        }

        $config = $this->fromTable(
            'configuracion_pecera',
            fn (): ?array => $this->configuracionModel->porUsuario($userId),
            null
        );

        $cache[$userId] = array_merge(self::DEFAULT_CONFIG, $config ?? []);

        return $cache[$userId];
    }

    private function saveConfig(int $userId, array $changes): array
    {
        $config = $this->ensureConfig($userId);

        if (! $this->tableExists('configuracion_pecera')) {
            return array_merge($config, $changes);
        }

        $payload = $changes;

        if (isset($config['id'])) {
            $this->configuracionModel->update($config['id'], $payload);
        } else {
            $payload['usuario_id'] = $userId;
            $this->configuracionModel->insert($payload);
        }

        return $this->ensureConfig($userId, true);
    }

    private function rangeStatus($value, float $min, float $max): string
    {
        if ($value === null || $value === '') {
            return 'neutral';
        }

        $value = (float) $value;
        if ($value >= $min && $value <= $max) {
            return 'ok';
        }

        $margin = max(($max - $min) * 0.25, 0.2);

        return ($value >= $min - $margin && $value <= $max + $margin) ? 'warn' : 'danger';
    }

    private function fromTable(string $table, callable $callback, mixed $fallback): mixed
    {
        if (! $this->tableExists($table)) {
            return $fallback;
        }

        try {
            return $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function emptyHistory(): array
    {
        return [
            'labels'      => [],
            'temperature' => [],
            'ph'          => [],
        ];
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];

        if (! array_key_exists($table, $cache)) {
            $cache[$table] = $this->database->tableExists($table);
        }

        return $cache[$table];
    }

    private function userId(): int
    {
        return (int) session()->get('user_id');
    }
}
