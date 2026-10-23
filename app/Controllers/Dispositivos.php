<?php

namespace App\Controllers;

use App\Models\DispositivoModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Alta, edicion y baja de los equipos del usuario, y sus API keys.
 */
class Dispositivos extends BaseController
{
    // Modelo de dispositivos: lo usan todos los metodos.
    private DispositivoModel $dispositivoModel;

    public function __construct()
    {
        $this->dispositivoModel = new DispositivoModel();
    }

    // Lista de dispositivos del usuario.
    public function index(): string
    {
        return $this->listado();
    }

    // Registra un dispositivo nuevo con los datos del formulario.
    public function create(): string|RedirectResponse
    {
        // Si el modelo rechaza los datos, se vuelve a la lista mostrando los errores.
        if (! $this->dispositivoModel->insert($this->datosDelFormulario())) {
            return $this->listado($this->dispositivoModel->errors());
        }

        return $this->volver('success', 'Dispositivo registrado correctamente.');
    }

    // Muestra el formulario de edicion y guarda los cambios.
    public function edit(int $id): string|RedirectResponse
    {
        // Solo se puede editar un dispositivo propio.
        $device = $this->dispositivoModel->buscarParaUsuario($id, $this->userId());
        if (! $device) {
            return $this->volver('error', 'El dispositivo no existe o no tienes permisos para editarlo.');
        }

        $errores = [];
        // Si llego el formulario, intenta guardar; si falla, muestra los errores con lo escrito.
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

    // Elimina un dispositivo propio.
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
        // Solo sobre un dispositivo propio.
        $device = $this->dispositivoModel->buscarParaUsuario($id, $this->userId());
        if (! $device) {
            return $this->volver('error', 'El dispositivo no existe o no tienes permisos sobre el.');
        }

        // Crea la clave nueva y se la pasa a la pagina una sola vez, para mostrarla.
        $apiKey = $this->dispositivoModel->generarApiKey($id);

        return redirect()
            ->to(base_url('dispositivos') . '#api-key-nueva')
            ->with('device_api_key', ['id' => $id, 'nombre' => $device['nombre'], 'key' => $apiKey])
            ->with('success', empty($device['api_key_hash'])
                ? 'API key generada. Copiala ahora: no se volvera a mostrar.'
                : 'API key regenerada. La anterior dejo de funcionar.');
    }

    // Anula la API key: el dispositivo deja de poder conectarse.
    public function revokeApiKey(int $id): RedirectResponse
    {
        if (! $this->dispositivoModel->buscarParaUsuario($id, $this->userId())) {
            return $this->volver('error', 'El dispositivo no existe o no tienes permisos sobre el.');
        }

        $this->dispositivoModel->revocarApiKey($id);

        return $this->volver('success', 'API key revocada. El dispositivo ya no podra enviar lecturas.');
    }

    // Muestra la pagina de dispositivos (con errores y lo escrito, si los hay).
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

    // Datos del formulario, sin espacios de mas y a nombre del usuario.
    private function datosDelFormulario(): array
    {
        return [
            'usuario_id' => $this->userId(),
            'nombre'     => trim((string) $this->request->getPost('nombre')),
            'tipo'       => (string) $this->request->getPost('tipo'),
            'ubicacion'  => trim((string) $this->request->getPost('ubicacion')),
        ];
    }

    // Vuelve a la lista con un mensaje.
    private function volver(string $tipo, string $mensaje): RedirectResponse
    {
        return redirect()->to(base_url('dispositivos'))->with($tipo, $mensaje);
    }
}
