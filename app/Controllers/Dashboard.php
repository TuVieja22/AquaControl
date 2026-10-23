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
 * actualizados para que funciones/dashboard.js redibuje sin recargar.
 */
class Dashboard extends BaseController
{
    // Datos del panel del usuario (se crea la primera vez que hace falta).
    private ?PanelPecera $panel = null;

    // Las cuatro paginas del panel usan la misma vista; solo cambia la seccion marcada.
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

    /** Datos actualizados (funciones/dashboard.js los pide cada pocos segundos). */
    public function latest(): ResponseInterface
    {
        return $this->respuesta();
    }

    // Marca una alerta como leida y devuelve el panel actualizado.
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
        // Limites de gramos y cantidad pedida.
        $feeding = config(Feeding::class);
        $grams = round((float) $this->request->getPost('cantidad_gramos'), 2);

        // La cantidad tiene que estar dentro de los limites.
        if ($grams < $feeding->minGrams || $grams > $feeding->maxGrams) {
            return $this->error(422, sprintf('La cantidad debe estar entre %.1f y %.1f g.', $feeding->minGrams, $feeding->maxGrams));
        }

        // Tiene que haber un alimentador registrado con su API key.
        if ($this->panel()->alimentadores() === []) {
            return $this->error(409, 'No hay un alimentador conectado. Registra el ESP32 en Dispositivos y generale una API key.');
        }

        // Tiene que ser inmediato: si el ESP32 no esta en linea no se deja la orden,
        // para que no dispense minutos despues cuando nadie lo espera.
        if ($this->panel()->alimentadorEnLinea() === null) {
            return $this->error(409, 'El alimentador esta desconectado. Revisa que el ESP32 este enchufado y conectado (por WiFi o con el puente USB).');
        }

        // No se aceptan dos ordenes a la vez.
        if ($this->panel()->alimentacionPendiente() !== null) {
            return $this->error(409, 'Ya hay una alimentacion en curso. Espera a que el dispositivo la confirme.');
        }

        // Deja la orden anotada: el ESP32 la toma en su proxima consulta.
        (new ComandoDispositivoModel())->crearAlimentacionManual($this->userId(), $grams);

        return $this->respuesta([
            'success' => true,
            'message' => 'Orden enviada. El alimentador la ejecutara en unos segundos.',
        ]);
    }

    // Guarda los dos horarios y los gramos de la alimentacion programada.
    public function updateFeedingSchedule(): ResponseInterface
    {
        $feeding = config(Feeding::class);
        // Regla de un horario: vacio o con formato HH:MM.
        $hora = 'permit_empty|regex_match[/^([01]\d|2[0-3]):[0-5]\d$/]';

        // Revisa los horarios y la cantidad.
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

        // Con errores, responde con todos los mensajes juntos.
        if (! $valido) {
            return $this->error(422, implode(' ', $this->validator->getErrors()));
        }

        // Un horario vacio desactiva esa alimentacion programada.
        $horaGuardada = function (string $campo): ?string {
            $valor = trim((string) $this->request->getPost($campo));

            return $valor === '' ? null : $valor . ':00';
        };

        // Guarda la configuracion nueva.
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

    // Prende o apaga el modo vacaciones.
    public function toggleVacation(): ResponseInterface
    {
        $activo = (int) $this->panel()->config()['modo_vacaciones'] === 1;
        $this->panel()->guardarConfig(['modo_vacaciones' => $activo ? 0 : 1]);

        return $this->respuesta(['success' => true]);
    }

    // Guarda la temperatura objetivo.
    public function updateTargetTemperature(): ResponseInterface
    {
        $this->panel()->guardarConfig([
            'temp_objetivo' => round((float) $this->request->getPost('temp_objetivo'), 2),
        ]);

        return $this->respuesta(['success' => true]);
    }

    // Devuelve el panel del usuario (lo crea una sola vez por pedido).
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

    // Arma la pagina del panel con todos sus datos.
    private function pagina(string $seccion): string
    {
        // Filtros del historial y datos de la cuenta.
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
            // Datos de "Mi cuenta".
            'profile'        => [
                'nombre'     => (string) ($usuario['nombre'] ?? session()->get('user_nombre')),
                'email'      => (string) ($usuario['email'] ?? session()->get('user_email')),
                'created_at' => $usuario['created_at'] ?? null,
                'updated_at' => $usuario['updated_at'] ?? null,
            ],
            // Errores y valores del formulario de la cuenta, si volvio con problemas.
            'profileErrors'  => session()->getFlashdata('profile_errors') ?? [],
            'profileForm'    => session()->getFlashdata('profile_form') ?? [],
            // Todo lo que dibuja el panel, mas las direcciones que usa el JavaScript.
            'dashboardData'  => $this->panel()->datos($filtros) + [
                'userName'  => (string) session()->get('user_nombre'),
                'endpoints' => $this->endpoints($filtros),
            ],
        ]);
    }

    /** Direcciones que usa funciones/dashboard.js (mantienen los filtros del historial). */
    private function endpoints(array $filtros): array
    {
        // Los filtros del historial se agregan al final de cada direccion.
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

    // Respuesta JSON con los datos actualizados del panel (mas lo que se quiera agregar).
    private function respuesta(array $extra = [], int $status = 200): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON(array_merge($this->panel()->datos($this->filtros()), $extra));
    }

    // Respuesta JSON de error con su mensaje.
    private function error(int $status, string $mensaje): ResponseInterface
    {
        return $this->respuesta(['success' => false, 'message' => $mensaje], $status);
    }
}
