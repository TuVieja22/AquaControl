<?php

namespace App\Controllers;

use App\Models\DispositivoModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Alta, edicion y baja de los equipos del usuario, y sus API keys.
 */
class Dispositivos extends BaseController
{
    private DispositivoModel $dispositivoModel;

    public function __construct()
    {
        $this->dispositivoModel = new DispositivoModel();
    }

    public function index(): string
    {
        return $this->listado();
    }

    public function create(): string|RedirectResponse
    {
        if (! $this->dispositivoModel->insert($this->datosDelFormulario())) {
            return $this->listado($this->dispositivoModel->errors());
        }

        return $this->volver('success', 'Dispositivo registrado correctamente.');
    }

    public function edit(int $id): string|RedirectResponse
    {
        $device = $this->dispositivoModel->buscarParaUsuario($id, $this->userId());
        if (! $device) {
            return $this->volver('error', 'El dispositivo no existe o no tienes permisos para editarlo.');
        }

        $errores = [];
        if ($this->request->getMethod() === 'POST') {
            $datos = $this->datosDelFormulario();
            if ($this->dispositivoModel->update($id, ['id' => $id] + $datos)) {
                return $this->volver('success', 'Dispositivo actualizado correctamente.');
            }
            $errores = $this->dispositivoModel->errors();
            $device = array_merge($device, $datos);
        }

        return view('devices/edit', [
            'title'       => 'Editar dispositivo',
            'device'      => $device,
            'typeOptions' => DispositivoModel::TIPOS,
            'errors'      => $errores,
        ]);
    }

    public function delete(int $id): RedirectResponse
    {
        if (! $this->dispositivoModel->buscarParaUsuario($id, $this->userId())) {
            return $this->volver('error', 'No se pudo eliminar el dispositivo solicitado.');
        }

        $this->dispositivoModel->delete($id);

        return $this->volver('success', 'Dispositivo eliminado correctamente.');
    }

    /**
     * Genera una API key nueva para el dispositivo (invalida la anterior si existia).
     * La key en claro se muestra una sola vez; en la base solo queda su hash.
     */
    public function generateApiKey(int $id): RedirectResponse
    {
        $device = $this->dispositivoModel->buscarParaUsuario($id, $this->userId());
        if (! $device) {
            return $this->volver('error', 'El dispositivo no existe o no tienes permisos sobre el.');
        }

        $apiKey = $this->dispositivoModel->generarApiKey($id);

        return redirect()
            ->to(base_url('dispositivos') . '#api-key-nueva')
            ->with('device_api_key', ['id' => $id, 'nombre' => $device['nombre'], 'key' => $apiKey])
            ->with('success', empty($device['api_key_hash'])
                ? 'API key generada. Copiala ahora: no se volvera a mostrar.'
                : 'API key regenerada. La anterior dejo de funcionar.');
    }

    public function revokeApiKey(int $id): RedirectResponse
    {
        if (! $this->dispositivoModel->buscarParaUsuario($id, $this->userId())) {
            return $this->volver('error', 'El dispositivo no existe o no tienes permisos sobre el.');
        }

        $this->dispositivoModel->revocarApiKey($id);

        return $this->volver('success', 'API key revocada. El dispositivo ya no podra enviar lecturas.');
    }

    private function listado(array $errores = []): string
    {
        return view('devices/index', [
            'title'       => 'Dispositivos',
            'devices'     => $this->dispositivoModel->porUsuario($this->userId()),
            'typeOptions' => DispositivoModel::TIPOS,
            'errors'      => $errores,
            'newApiKey'   => session()->getFlashdata('device_api_key'),
            'apiEndpoint' => base_url('dashboard/api/data'),
            'form'        => [
                'nombre'    => $this->request->getPost('nombre') ?? '',
                'tipo'      => $this->request->getPost('tipo') ?? '',
                'ubicacion' => $this->request->getPost('ubicacion') ?? '',
            ],
        ]);
    }

    private function datosDelFormulario(): array
    {
        return [
            'usuario_id' => $this->userId(),
            'nombre'     => trim((string) $this->request->getPost('nombre')),
            'tipo'       => (string) $this->request->getPost('tipo'),
            'ubicacion'  => trim((string) $this->request->getPost('ubicacion')),
        ];
    }

    private function volver(string $tipo, string $mensaje): RedirectResponse
    {
        return redirect()->to(base_url('dispositivos'))->with($tipo, $mensaje);
    }
}
