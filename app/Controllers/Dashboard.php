<?php

namespace App\Controllers;

use App\Libraries\PanelPecera;
use App\Models\AlertaModel;
use App\Models\ComandoDispositivoModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Feeding;

/**
 * Panel de la pecera. Las paginas (panel, historial, configuracion y mi cuenta) usan la
 * misma vista; las acciones (alimentar, horarios, etc.) responden JSON con los datos
 * actualizados para que dashboard.js redibuje sin recargar.
 */
class Dashboard extends BaseController
{
    private ?PanelPecera $panel = null;

    public function index(): string
    {
        return $this->pagina('overview');
    }

    public function history(): string
    {
        return $this->pagina('history');
    }

    public function settings(): string
    {
        return $this->pagina('settings');
    }

    public function profile(): string
    {
        return $this->pagina('profile');
    }

    /** Datos actualizados (dashboard.js los pide cada pocos segundos). */
    public function latest(): ResponseInterface
    {
        return $this->respuesta();
    }

    public function markAlertRead(int $alertId): ResponseInterface
    {
        (new AlertaModel())->marcarLeida($alertId, $this->userId());

        return $this->respuesta(['success' => true]);
    }

    /**
     * "Alimentar ahora": deja una orden para que el ESP32 mueva el servo. La alimentacion
     * se anota en el historial recien cuando el dispositivo confirma que la hizo.
     */
    public function feedNow(): ResponseInterface
    {
        $feeding = config(Feeding::class);
        $grams = round((float) $this->request->getPost('cantidad_gramos'), 2);

        if ($grams < $feeding->minGrams || $grams > $feeding->maxGrams) {
            return $this->error(422, sprintf('La cantidad debe estar entre %.1f y %.1f g.', $feeding->minGrams, $feeding->maxGrams));
        }

        if ($this->panel()->alimentadores() === []) {
            return $this->error(409, 'No hay un alimentador conectado. Registra el ESP32 en Dispositivos y generale una API key.');
        }

        // Tiene que ser inmediato: si el ESP32 no esta en linea no se deja la orden,
        // para que no dispense minutos despues cuando nadie lo espera.
        if ($this->panel()->alimentadorEnLinea() === null) {
            return $this->error(409, 'El alimentador esta desconectado. Revisa que el ESP32 este enchufado y conectado (por WiFi o con el puente USB).');
        }

        if ($this->panel()->alimentacionPendiente() !== null) {
            return $this->error(409, 'Ya hay una alimentacion en curso. Espera a que el dispositivo la confirme.');
        }

        (new ComandoDispositivoModel())->crearAlimentacionManual($this->userId(), $grams);

        return $this->respuesta([
            'success' => true,
            'message' => 'Orden enviada. El alimentador la ejecutara en unos segundos.',
        ]);
    }

    public function updateFeedingSchedule(): ResponseInterface
    {
        $feeding = config(Feeding::class);
        $hora = 'permit_empty|regex_match[/^([01]\d|2[0-3]):[0-5]\d$/]';

        $valido = $this->validate([
            'hora_alim_1'          => $hora,
            'hora_alim_2'          => $hora,
            'cantidad_alim_gramos' => "required|decimal|greater_than_equal_to[{$feeding->minGrams}]|less_than_equal_to[{$feeding->maxGrams}]",
        ], [
            'hora_alim_1'          => ['regex_match' => 'El horario 1 debe tener formato HH:MM.'],
            'hora_alim_2'          => ['regex_match' => 'El horario 2 debe tener formato HH:MM.'],
            'cantidad_alim_gramos' => [
                'required'              => 'Indica la cantidad por racion.',
                'decimal'               => 'La cantidad debe ser un numero.',
                'greater_than_equal_to' => "La cantidad minima es {$feeding->minGrams} g.",
                'less_than_equal_to'    => "La cantidad maxima es {$feeding->maxGrams} g.",
            ],
        ]);

        if (! $valido) {
            return $this->error(422, implode(' ', $this->validator->getErrors()));
        }

        // Un horario vacio desactiva esa alimentacion programada.
        $horaGuardada = function (string $campo): ?string {
            $valor = trim((string) $this->request->getPost($campo));

            return $valor === '' ? null : $valor . ':00';
        };

        $this->panel()->guardarConfig([
            'hora_alim_1'          => $horaGuardada('hora_alim_1'),
            'hora_alim_2'          => $horaGuardada('hora_alim_2'),
            'cantidad_alim_gramos' => round((float) $this->request->getPost('cantidad_alim_gramos'), 2),
        ]);

        return $this->respuesta([
            'success' => true,
            'message' => 'Horarios de alimentacion guardados.',
        ]);
    }

    public function toggleVacation(): ResponseInterface
    {
        $activo = (int) $this->panel()->config()['modo_vacaciones'] === 1;
        $this->panel()->guardarConfig(['modo_vacaciones' => $activo ? 0 : 1]);

        return $this->respuesta(['success' => true]);
    }

    public function updateTargetTemperature(): ResponseInterface
    {
        $this->panel()->guardarConfig([
            'temp_objetivo' => round((float) $this->request->getPost('temp_objetivo'), 2),
        ]);

        return $this->respuesta(['success' => true]);
    }

    private function panel(): PanelPecera
    {
        return $this->panel ??= new PanelPecera($this->userId());
    }

    /** Filtros del historial tomados de la URL (?desde=...&hasta=...&dispositivo=...). */
    private function filtros(): array
    {
        return $this->panel()->filtrosHistorial(
            trim((string) $this->request->getGet('desde')),
            trim((string) $this->request->getGet('hasta')),
            (int) $this->request->getGet('dispositivo')
        );
    }

    private function pagina(string $seccion): string
    {
        $filtros = $this->filtros();
        $userModel = new UserModel();
        $usuario = $userModel->find($this->userId()) ?? [];

        return view('dashboard/index', [
            'title'          => 'Dashboard',
            'activeSection'  => $seccion,
            'historyFilters' => $filtros,
            'rangoGrafico'   => $this->panel()->textoRango($filtros),
            'devices'        => $this->panel()->dispositivos(),
            'feeding'        => config(Feeding::class),
            'profile'        => [
                'nombre'     => (string) ($usuario['nombre'] ?? session()->get('user_nombre')),
                'email'      => (string) ($usuario['email'] ?? session()->get('user_email')),
                'roleLabel'  => $userModel->roleLabel((string) ($usuario['rol'] ?? session()->get('user_role'))),
                'created_at' => $usuario['created_at'] ?? null,
                'updated_at' => $usuario['updated_at'] ?? null,
            ],
            'profileErrors'  => session()->getFlashdata('profile_errors') ?? [],
            'profileForm'    => session()->getFlashdata('profile_form') ?? [],
            'dashboardData'  => $this->panel()->datos($filtros) + [
                'userName'  => (string) session()->get('user_nombre'),
                'endpoints' => $this->endpoints($filtros),
            ],
        ]);
    }

    /** Direcciones que usa dashboard.js (mantienen los filtros del historial). */
    private function endpoints(array $filtros): array
    {
        $query = $filtros['query'] === [] ? '' : '?' . http_build_query($filtros['query']);

        return [
            'latest'            => base_url('dashboard/api/latest') . $query,
            'feed'              => base_url('dashboard/control/feed') . $query,
            'vacationToggle'    => base_url('dashboard/control/vacation-toggle') . $query,
            'targetTemperature' => base_url('dashboard/control/target-temperature') . $query,
            'feedingSchedule'   => base_url('dashboard/control/feeding-schedule') . $query,
            'markAlertTemplate' => base_url('dashboard/alerts/__id__/read') . $query,
        ];
    }

    private function respuesta(array $extra = [], int $status = 200): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON(array_merge($this->panel()->datos($this->filtros()), $extra));
    }

    private function error(int $status, string $mensaje): ResponseInterface
    {
        return $this->respuesta(['success' => false, 'message' => $mensaje], $status);
    }
}
