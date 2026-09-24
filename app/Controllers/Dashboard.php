<?php

namespace App\Controllers;

use App\Models\AlertaModel;
use App\Models\AlimentacionModel;
use App\Models\ComandoDispositivoModel;
use App\Models\ConfiguracionPeceraModel;
use App\Models\DispositivoModel;
use App\Models\SensorModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Feeding;
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
        // Mismos defaults que las columnas de configuracion_pecera.
        'hora_alim_1'          => '08:00:00',
        'hora_alim_2'          => '18:00:00',
        'cantidad_alim_gramos' => 1.00,
    ];

    private const MAX_CHART_POINTS = 500;

    private SensorModel $sensorModel;
    private DispositivoModel $dispositivoModel;
    private ComandoDispositivoModel $comandoModel;
    private AlimentacionModel $alimentacionModel;
    private AlertaModel $alertaModel;
    private ConfiguracionPeceraModel $configuracionModel;
    private UserModel $userModel;
    private BaseConnection $database;

    public function __construct()
    {
        $this->database = db_connect();
        $this->sensorModel = new SensorModel();
        $this->dispositivoModel = new DispositivoModel();
        $this->comandoModel = new ComandoDispositivoModel();
        $this->alimentacionModel = new AlimentacionModel();
        $this->alertaModel = new AlertaModel();
        $this->configuracionModel = new ConfiguracionPeceraModel();
        $this->userModel = new UserModel();
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

    public function profile(): string
    {
        return view('dashboard/index', $this->buildViewData('profile'));
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

    /**
     * "Alimentar ahora": encola un comando para que el ESP32 mueva el servo. La
     * alimentacion se registra en el historial recien cuando el dispositivo confirma.
     */
    public function feedNow(): ResponseInterface
    {
        $userId = $this->userId();
        $feeding = config(Feeding::class);
        $grams = round((float) $this->request->getPost('cantidad_gramos'), 2);

        if ($grams < $feeding->minGrams || $grams > $feeding->maxGrams) {
            return $this->feedError($userId, 422, sprintf(
                'La cantidad debe estar entre %.1f y %.1f g.',
                $feeding->minGrams,
                $feeding->maxGrams
            ));
        }

        if ($this->feederDevices($userId) === []) {
            return $this->feedError($userId, 409, 'No hay un alimentador conectado. Registra el ESP32 en Dispositivos y generale una API key.');
        }

        if ($this->comandoModel->alimentacionPendiente($userId) !== null) {
            return $this->feedError($userId, 409, 'Ya hay una alimentacion en curso. Espera a que el dispositivo la confirme.');
        }

        $this->comandoModel->crearAlimentacionManual($userId, $grams);

        return $this->dashboardResponse($userId, [
            'success' => true,
            'message' => 'Orden enviada. El alimentador la ejecutara en unos segundos.',
        ]);
    }

    public function updateFeedingSchedule(): ResponseInterface
    {
        $userId = $this->userId();
        $feeding = config(Feeding::class);

        $rules = [
            'hora_alim_1'          => 'permit_empty|regex_match[/^([01]\d|2[0-3]):[0-5]\d$/]',
            'hora_alim_2'          => 'permit_empty|regex_match[/^([01]\d|2[0-3]):[0-5]\d$/]',
            'cantidad_alim_gramos' => "required|decimal|greater_than_equal_to[{$feeding->minGrams}]|less_than_equal_to[{$feeding->maxGrams}]",
        ];

        if (! $this->validate($rules, [
            'hora_alim_1'          => ['regex_match' => 'El horario 1 debe tener formato HH:MM.'],
            'hora_alim_2'          => ['regex_match' => 'El horario 2 debe tener formato HH:MM.'],
            'cantidad_alim_gramos' => [
                'required'              => 'Indica la cantidad por racion.',
                'decimal'               => 'La cantidad debe ser un numero.',
                'greater_than_equal_to' => "La cantidad minima es {$feeding->minGrams} g.",
                'less_than_equal_to'    => "La cantidad maxima es {$feeding->maxGrams} g.",
            ],
        ])) {
            return $this->feedError($userId, 422, implode(' ', $this->validator->getErrors()));
        }

        $time = function (string $field): ?string {
            $value = trim((string) $this->request->getPost($field));

            return $value === '' ? null : $value . ':00';
        };

        $this->saveConfig($userId, [
            'hora_alim_1'          => $time('hora_alim_1'),
            'hora_alim_2'          => $time('hora_alim_2'),
            'cantidad_alim_gramos' => round((float) $this->request->getPost('cantidad_alim_gramos'), 2),
        ]);

        return $this->dashboardResponse($userId, [
            'success' => true,
            'message' => 'Horarios de alimentacion guardados.',
        ]);
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

    public function updateProfile(): RedirectResponse
    {
        $userId = $this->userId();
        $user = $this->userModel->find($userId);

        if (! $user) {
            session()->destroy();

            return redirect()
                ->to(base_url('auth/login'))
                ->with('error', 'Tu sesion expiro. Inicia sesion nuevamente.');
        }

        ['rules' => $rules, 'messages' => $messages] = $this->profileValidation($userId);

        if (! $this->validate($rules, $messages)) {
            return $this->profileRedirect(
                $this->validator->getErrors(),
                'Revisa los datos de tu cuenta.'
            );
        }

        $name = trim((string) $this->request->getPost('nombre'));
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $currentPassword = (string) $this->request->getPost('current_password');

        $emailChanged = $email !== strtolower((string) ($user['email'] ?? ''));
        $nameChanged = $name !== (string) ($user['nombre'] ?? '');

        if ($emailChanged && ! $this->userModel->verifyPassword($currentPassword, (string) $user['password'])) {
            return $this->profileRedirect([
                'current_password' => $currentPassword === ''
                    ? 'Ingresa tu contrasena actual para cambiar el email.'
                    : 'La contrasena actual no coincide.',
            ], 'No se pudo confirmar tu identidad.');
        }

        if (! $nameChanged && ! $emailChanged) {
            return redirect()
                ->to(base_url('dashboard/profile') . '#perfil')
                ->with('info', 'No habia cambios para guardar.');
        }

        $payload = [
            'id'     => $userId,
            'nombre' => $name,
            'email'  => $email,
        ];

        if ($emailChanged) {
            $payload['token_recuperacion'] = null;
            $payload['token_expira'] = null;
            $payload['login_intentos'] = 0;
            $payload['bloqueado_hasta'] = null;
        }

        if (! $this->userModel->update($userId, $payload)) {
            return $this->profileRedirect(
                $this->userModel->errors(),
                'No se pudo actualizar tu cuenta.'
            );
        }

        session()->set([
            'user_nombre' => $name,
            'user_email'  => $email,
        ]);
        session()->regenerate(true);

        $message = $emailChanged
            ? 'Cuenta actualizada. Usa el nuevo correo en tu proximo inicio de sesion.'
            : 'Cuenta actualizada correctamente.';

        return redirect()
            ->to(base_url('dashboard/profile') . '#perfil')
            ->with('success', $message);
    }

    /**
     * Ingesta de lecturas del ESP32. La ruta usa el filtro `deviceauth`, asi que el
     * dispositivo (y por ende el usuario duenio) sale de la API key, no de la sesion.
     */
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

        $device = service('deviceAuth')->device();
        if ($device === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['success' => false, 'message' => 'Dispositivo no autenticado.']);
        }

        $userId = (int) $device['usuario_id'];

        try {
            $input = $this->request->is('json') ? $this->request->getJSON(true) : $this->request->getPost();
        } catch (Throwable) {
            $input = null; // JSON mal formado
        }

        if (! is_array($input) || $input === [] || ! $this->validateData($input, [
            'temperatura'     => 'permit_empty|decimal|greater_than[-10]|less_than[60]',
            'ph'              => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[14]',
            'turbidez'        => 'permit_empty|decimal',
            'nivel_agua'      => 'permit_empty|in_list[0,1]',
            'calefactor'      => 'permit_empty|in_list[0,1]',
            'modo_vacaciones' => 'permit_empty|in_list[0,1]',
        ])) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    'success' => false,
                    'errors'  => $this->validator?->getErrors() ?? ['body' => 'Se esperaba un objeto JSON con las lecturas.'],
                ]);
        }

        $readingId = $this->sensorModel->insert([
            'usuario_id'      => $userId,
            'dispositivo_id'  => (int) $device['id'],
            'temperatura'     => $input['temperatura'] ?? null,
            'ph'              => $input['ph'] ?? null,
            'turbidez'        => $input['turbidez'] ?? null,
            'nivel_agua'      => isset($input['nivel_agua']) ? (int) $input['nivel_agua'] : 1,
            'calefactor'      => isset($input['calefactor']) ? (int) $input['calefactor'] : 0,
            'modo_vacaciones' => isset($input['modo_vacaciones']) ? (int) $input['modo_vacaciones'] : 0,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        // Respuesta compacta para el microcontrolador: confirma y devuelve la config vigente.
        $config = $this->ensureConfig($userId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success'    => true,
                'lectura_id' => $readingId,
                'config'     => [
                    'temp_objetivo'   => (float) $config['temp_objetivo'],
                    'modo_vacaciones' => (int) $config['modo_vacaciones'],
                ],
            ]);
    }

    private function buildViewData(string $activeSection): array
    {
        $userId = $this->userId();
        $filters = $this->resolveHistoryFilters($userId);

        return [
            'title'         => 'Dashboard',
            'extraCss'      => ['css/dashboard.css'],
            'extraJs'       => ['js/dashboard.js'],
            'activeSection' => $activeSection,
            'profile'       => $this->fetchUserProfile($userId),
            'profileErrors' => session()->getFlashdata('profile_errors') ?? [],
            'profileForm'   => session()->getFlashdata('profile_form') ?? [],
            'historyFilters' => $filters,
            'devices'       => $this->fromTable('dispositivos', fn (): array => $this->dispositivoModel->porUsuario($userId), []),
            'dashboardData' => array_merge($this->buildDashboardPayload($userId, $filters), [
                'userName'  => (string) session()->get('user_nombre'),
                'endpoints' => $this->buildEndpoints($filters),
            ]),
        ];
    }

    private function buildDashboardPayload(int $userId, ?array $filters = null): array
    {
        $config = $this->ensureConfig($userId);
        $latest = $this->fetchLatestReading($userId);

        return [
            'config'          => $config,
            'cards'           => $this->buildCards($latest, $config, $this->fetchLastFeeding($userId)),
            'alerts'          => $this->fetchAlerts($userId),
            'feedings'        => $this->fetchFeedings($userId),
            'charts'          => $this->fetchSensorHistory($userId, $filters ?? $this->resolveHistoryFilters($userId)),
            'feeder'          => $this->buildFeederState($userId, $config),
            'latestTimestamp' => $latest['created_at'] ?? null,
        ];
    }

    /**
     * Lee los filtros del historial desde la query string (`desde`, `hasta` en Y-m-d
     * y `dispositivo`). Sin filtros validos se mantiene la ventana de las ultimas 24 h.
     */
    private function resolveHistoryFilters(int $userId): array
    {
        $today = date('Y-m-d');
        $filters = [
            'custom'      => false,
            'desde'       => date('Y-m-d', strtotime('-1 day')),
            'hasta'       => $today,
            'dispositivo' => null,
            'label'       => '24 h',
            'error'       => null,
            'query'       => [],
        ];

        $desde = trim((string) $this->request->getGet('desde'));
        $hasta = trim((string) $this->request->getGet('hasta'));
        $deviceId = (int) $this->request->getGet('dispositivo');

        if ($deviceId > 0) {
            if ($this->fromTable('dispositivos', fn (): ?array => $this->dispositivoModel->buscarParaUsuario($deviceId, $userId), null)) {
                $filters['dispositivo'] = $deviceId;
                $filters['query']['dispositivo'] = $deviceId;
            } else {
                $filters['error'] = 'El dispositivo seleccionado no existe.';
            }
        }

        if ($desde === '' && $hasta === '') {
            return $filters;
        }

        $desdeDate = $this->parseDate($desde);
        $hastaDate = $this->parseDate($hasta);

        if (($desde !== '' && $desdeDate === null) || ($hasta !== '' && $hastaDate === null)) {
            $filters['error'] = 'Las fechas deben tener formato AAAA-MM-DD.';

            return $filters;
        }

        $desdeDate ??= $hastaDate;
        $hastaDate ??= $today;

        if ($desdeDate > $hastaDate) {
            $filters['error'] = 'La fecha "desde" no puede ser posterior a "hasta".';

            return $filters;
        }

        $filters['custom'] = true;
        $filters['desde'] = $desdeDate;
        $filters['hasta'] = $hastaDate;
        $filters['label'] = $desdeDate === $hastaDate
            ? date('d/m/Y', strtotime($desdeDate))
            : date('d/m', strtotime($desdeDate)) . ' - ' . date('d/m/Y', strtotime($hastaDate));
        $filters['query']['desde'] = $desdeDate;
        $filters['query']['hasta'] = $hastaDate;

        return $filters;
    }

    private function parseDate(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return ($date && $date->format('Y-m-d') === $value) ? $value : null;
    }

    private function buildEndpoints(array $filters = []): array
    {
        $historyQuery = ($filters['query'] ?? []) === [] ? '' : '?' . http_build_query($filters['query']);

        return [
            'latest'            => base_url('dashboard/api/latest') . $historyQuery,
            'feed'              => base_url('dashboard/control/feed') . $historyQuery,
            'vacationToggle'    => base_url('dashboard/control/vacation-toggle') . $historyQuery,
            'targetTemperature' => base_url('dashboard/control/target-temperature') . $historyQuery,
            'feedingSchedule'   => base_url('dashboard/control/feeding-schedule') . $historyQuery,
            'markAlertTemplate' => base_url('dashboard/alerts/__id__/read') . $historyQuery,
        ];
    }

    private function fetchUserProfile(int $userId): array
    {
        $user = $this->userModel->find($userId) ?? [];
        $role = (string) ($user['rol'] ?? session()->get('user_role') ?? UserModel::DEFAULT_ROLE);

        return [
            'nombre'     => (string) ($user['nombre'] ?? session()->get('user_nombre') ?? ''),
            'email'      => (string) ($user['email'] ?? session()->get('user_email') ?? ''),
            'rol'        => $role,
            'roleLabel'  => $this->userModel->roleLabel($role),
            'created_at' => $user['created_at'] ?? null,
            'updated_at' => $user['updated_at'] ?? null,
        ];
    }

    private function profileValidation(int $userId): array
    {
        return [
            'rules' => [
                'nombre'           => 'required|min_length[2]|max_length[100]',
                'email'            => 'required|valid_email|max_length[150]|is_unique[usuarios.email,id,' . $userId . ']',
                'current_password' => 'permit_empty',
            ],
            'messages' => [
                'nombre' => [
                    'required'   => 'El nombre es obligatorio.',
                    'min_length' => 'El nombre debe tener al menos 2 caracteres.',
                    'max_length' => 'El nombre no puede superar los 100 caracteres.',
                ],
                'email' => [
                    'required'    => 'El correo electronico es obligatorio.',
                    'valid_email' => 'Ingresa un correo electronico valido.',
                    'max_length'  => 'El correo no puede superar los 150 caracteres.',
                    'is_unique'   => 'Este correo ya esta registrado en otra cuenta.',
                ],
            ],
        ];
    }

    private function profileRedirect(array $errors, string $message): RedirectResponse
    {
        return redirect()
            ->to(base_url('dashboard/profile') . '#perfil')
            ->with('profile_errors', $errors)
            ->with('profile_form', [
                'nombre' => trim((string) $this->request->getPost('nombre')),
                'email'  => strtolower(trim((string) $this->request->getPost('email'))),
            ])
            ->with('error', $message);
    }

    private function dashboardResponse(int $userId, array $extra = []): ResponseInterface
    {
        return $this->response->setJSON(array_merge($this->buildDashboardPayload($userId), $extra));
    }

    private function feedError(int $userId, int $status, string $message): ResponseInterface
    {
        return $this->dashboardResponse($userId, ['success' => false, 'message' => $message])
            ->setStatusCode($status);
    }

    /**
     * Dispositivos del usuario que pueden recibir ordenes del alimentador
     * (tienen API key y no son sensores puros).
     */
    private function feederDevices(int $userId): array
    {
        return array_values(array_filter(
            $this->fromTable('dispositivos', fn (): array => $this->dispositivoModel->porUsuario($userId), []),
            static fn (array $device): bool => ! empty($device['api_key_hash']) && $device['tipo'] !== 'sensor'
        ));
    }

    private function buildFeederState(int $userId, array $config): array
    {
        $feeding = config(Feeding::class);
        $devices = $this->feederDevices($userId);
        $online = null;

        foreach ($devices as $device) {
            if (! empty($device['ultima_conexion']) && time() - strtotime($device['ultima_conexion']) <= $feeding->onlineThresholdSeconds) {
                $online = $device;
                break;
            }
        }

        $last = $this->fromTable('comandos_dispositivo', fn (): ?array => $this->comandoModel->ultimaAlimentacion($userId), null);
        $pending = $this->fromTable('comandos_dispositivo', fn (): ?array => $this->comandoModel->alimentacionPendiente($userId), null);
        $deviceName = ($online ?? $devices[0] ?? [])['nombre'] ?? null;
        $schedule = ComandoDispositivoModel::horasConfiguradas($config);
        $nextFeeding = $this->comandoModel->proximoHorario($config);

        if ($devices === []) {
            [$statusLevel, $statusText] = ['danger', 'Sin alimentador vinculado. Registra el ESP32 en Dispositivos y generale una API key.'];
        } elseif ($pending !== null) {
            [$statusLevel, $statusText] = ['warn', $pending['estado'] === 'enviado'
                ? 'El alimentador recibio la orden y esta moviendo el servo...'
                : 'Orden en cola: esperando que el alimentador la tome.'];
        } elseif ($online !== null) {
            [$statusLevel, $statusText] = ['ok', 'Conectado: ' . $deviceName];
        } else {
            [$statusLevel, $statusText] = ['warn', $deviceName . ' sin contacto reciente (offline).'];
        }

        $lastLabels = [
            'ejecutado' => 'ejecutada',
            'fallido'   => 'fallo',
            'expirado'  => 'expiro sin respuesta',
        ];
        $lastText = null;
        if ($last !== null && isset($lastLabels[$last['estado']])) {
            $lastText = sprintf(
                'Ultima orden (%s): %s %s',
                $last['origen'] === 'programado' ? 'programada' : 'manual',
                $lastLabels[$last['estado']],
                $this->localTime($last['finalizado_at'] ?? $last['created_at'])
            );
        }

        return [
            'hasDevice'    => $devices !== [],
            'online'       => $online !== null,
            'pending'      => $pending !== null,
            'statusLevel'  => $statusLevel,
            'statusText'   => $statusText,
            'lastText'     => $lastText,
            'scheduleText' => $schedule === []
                ? 'Sin horarios programados.'
                : sprintf(
                    'Programado: %s (%s g por racion, hora Argentina). Proxima: %s.',
                    implode(' y ', $schedule),
                    rtrim(rtrim(number_format((float) ($config['cantidad_alim_gramos'] ?? 1), 2, '.', ''), '0'), '.'),
                    $nextFeeding
                ),
        ];
    }

    /**
     * Fecha guardada (en la zona de la app) formateada en la zona horaria del alimentador.
     */
    private function localTime(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        return (new \DateTimeImmutable($value))
            ->setTimezone(new \DateTimeZone(config(Feeding::class)->timezone))
            ->format('d/m H:i');
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
                    'Rango ideal %.2f - %.2f',
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

    private function fetchSensorHistory(int $userId, array $filters): array
    {
        return $this->fromTable('lecturas_sensores', function () use ($userId, $filters): array {
            $history = $this->emptyHistory();

            if ($filters['custom']) {
                $rows = $this->sensorModel->historialRango(
                    $userId,
                    $filters['desde'] . ' 00:00:00',
                    $filters['hasta'] . ' 23:59:59',
                    $filters['dispositivo']
                );
            } elseif ($filters['dispositivo'] !== null) {
                $rows = $this->sensorModel->historialRango(
                    $userId,
                    date('Y-m-d H:i:s', strtotime('-24 hours')),
                    date('Y-m-d H:i:s'),
                    $filters['dispositivo']
                );
            } else {
                $rows = $this->sensorModel->historial($userId, 24);
            }

            $labelFormat = ($filters['custom'] && $filters['desde'] !== $filters['hasta']) ? 'd/m H:i' : 'H:i';

            foreach ($this->downsample($rows, self::MAX_CHART_POINTS) as $row) {
                $history['labels'][] = date($labelFormat, strtotime($row['created_at']));
                $history['temperature'][] = $row['temperatura'] !== null ? (float) $row['temperatura'] : null;
                $history['ph'][] = $row['ph'] !== null ? (float) $row['ph'] : null;
            }

            $history['count'] = count($rows);

            return $history;
        }, $this->emptyHistory());
    }

    /**
     * Reduce la serie a como mucho $maxPoints promediando lecturas consecutivas,
     * para que rangos de varios dias no saturen el grafico.
     */
    private function downsample(array $rows, int $maxPoints): array
    {
        $total = count($rows);
        if ($total <= $maxPoints) {
            return $rows;
        }

        $bucketSize = (int) ceil($total / $maxPoints);
        $average = static function (array $bucket, string $field): ?float {
            $values = array_filter(array_column($bucket, $field), static fn ($value): bool => $value !== null);

            return $values === [] ? null : round(array_sum($values) / count($values), 2);
        };

        $sampled = [];
        foreach (array_chunk($rows, $bucketSize) as $bucket) {
            $sampled[] = [
                'created_at'  => $bucket[0]['created_at'],
                'temperatura' => $average($bucket, 'temperatura'),
                'ph'          => $average($bucket, 'ph'),
            ];
        }

        return $sampled;
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
            'count'       => 0,
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
