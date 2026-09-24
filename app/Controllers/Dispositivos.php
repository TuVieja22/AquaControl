<?php

namespace App\Controllers;

use App\Models\DispositivoModel;
use CodeIgniter\HTTP\RedirectResponse;

class Dispositivos extends BaseController
{
    private const ASSETS = [
        'extraCss' => ['css/dashboard.css', 'css/management.css'],
    ];

    private const TYPE_OPTIONS = [
        'sensor'      => 'Sensor',
        'actuador'   => 'Actuador',
        'controlador' => 'Controlador',
        'kit_iot'    => 'Kit IoT',
        'otro'       => 'Otro',
    ];

    private DispositivoModel $dispositivoModel;

    public function __construct()
    {
        $this->dispositivoModel = new DispositivoModel();
    }

    public function index(): string
    {
        return $this->renderIndex();
    }

    public function create(): string|RedirectResponse
    {
        if ($this->request->getMethod() !== 'POST') {
            return redirect()->to(base_url('dispositivos'));
        }

        $payload = $this->devicePayload();

        if (! $this->dispositivoModel->insert($payload)) {
            return $this->renderIndex($this->dispositivoModel->errors());
        }

        return redirect()
            ->to(base_url('dispositivos'))
            ->with('success', 'Dispositivo registrado correctamente.');
    }

    public function edit(int $id): string|RedirectResponse
    {
        $device = $this->dispositivoModel->buscarParaUsuario($id, $this->userId());
        if (! $device) {
            return redirect()
                ->to(base_url('dispositivos'))
                ->with('error', 'El dispositivo no existe o no tienes permisos para editarlo.');
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->updateDevice($id, $device);
        }

        return view('devices/edit', array_merge(self::ASSETS, [
            'title'       => 'Editar dispositivo',
            'device'      => $device,
            'typeOptions' => self::TYPE_OPTIONS,
            'errors'      => [],
        ]));
    }

    public function delete(int $id): RedirectResponse
    {
        $device = $this->dispositivoModel->buscarParaUsuario($id, $this->userId());
        if (! $device) {
            return redirect()
                ->to(base_url('dispositivos'))
                ->with('error', 'No se pudo eliminar el dispositivo solicitado.');
        }

        $this->dispositivoModel->delete($id);

        return redirect()
            ->to(base_url('dispositivos'))
            ->with('success', 'Dispositivo eliminado correctamente.');
    }

    /**
     * Genera una API key nueva para el dispositivo (invalida la anterior si existia).
     * La key en claro se muestra una sola vez; en la base solo queda su hash.
     */
    public function generateApiKey(int $id): RedirectResponse
    {
        $device = $this->dispositivoModel->buscarParaUsuario($id, $this->userId());
        if (! $device) {
            return redirect()
                ->to(base_url('dispositivos'))
                ->with('error', 'El dispositivo no existe o no tienes permisos sobre el.');
        }

        $apiKey = $this->dispositivoModel->generarApiKey($id);

        return redirect()
            ->to(base_url('dispositivos') . '#api-key-nueva')
            ->with('device_api_key', [
                'id'     => $id,
                'nombre' => $device['nombre'],
                'key'    => $apiKey,
            ])
            ->with('success', empty($device['api_key_hash'])
                ? 'API key generada. Copiala ahora: no se volvera a mostrar.'
                : 'API key regenerada. La anterior dejo de funcionar.');
    }

    public function revokeApiKey(int $id): RedirectResponse
    {
        $device = $this->dispositivoModel->buscarParaUsuario($id, $this->userId());
        if (! $device) {
            return redirect()
                ->to(base_url('dispositivos'))
                ->with('error', 'El dispositivo no existe o no tienes permisos sobre el.');
        }

        $this->dispositivoModel->revocarApiKey($id);

        return redirect()
            ->to(base_url('dispositivos'))
            ->with('success', 'API key revocada. El dispositivo ya no podra enviar lecturas.');
    }

    private function updateDevice(int $id, array $device): string|RedirectResponse
    {
        $payload = $this->devicePayload();
        $payload['id'] = $id;

        if (! $this->dispositivoModel->update($id, $payload)) {
            return view('devices/edit', array_merge(self::ASSETS, [
                'title'       => 'Editar dispositivo',
                'device'      => array_merge($device, $payload),
                'typeOptions' => self::TYPE_OPTIONS,
                'errors'      => $this->dispositivoModel->errors(),
            ]));
        }

        return redirect()
            ->to(base_url('dispositivos'))
            ->with('success', 'Dispositivo actualizado correctamente.');
    }

    private function renderIndex(array $errors = []): string
    {
        return view('devices/index', array_merge(self::ASSETS, [
            'title'       => 'Dispositivos',
            'devices'     => $this->dispositivoModel->porUsuario($this->userId()),
            'typeOptions' => self::TYPE_OPTIONS,
            'errors'      => $errors,
            'newApiKey'   => session()->getFlashdata('device_api_key'),
            'apiEndpoint' => base_url('dashboard/api/data'),
            'form'        => [
                'nombre'    => $this->request->getPost('nombre') ?? '',
                'tipo'      => $this->request->getPost('tipo') ?? '',
                'ubicacion' => $this->request->getPost('ubicacion') ?? '',
            ],
        ]));
    }

    private function devicePayload(): array
    {
        return [
            'usuario_id' => $this->userId(),
            'nombre'     => trim((string) $this->request->getPost('nombre')),
            'tipo'       => (string) $this->request->getPost('tipo'),
            'ubicacion'  => trim((string) $this->request->getPost('ubicacion')),
        ];
    }

    private function userId(): int
    {
        return (int) session()->get('user_id');
    }
}
