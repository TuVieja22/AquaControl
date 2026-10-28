<?php

namespace App\Controllers;

use App\Models\ComandoDispositivoModel;
use App\Models\DispositivoModel;
use App\Models\DispositivoNuevoModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Feeding;
use Config\RedAquaControl;

/**
 * Equipos del usuario. Un AquaControl se conecta al WiFi de la casa desde su propia red (QR
 * de Dispositivos o del manual) y despues pregunta solo si ya lo agregaron (DeviceApi::vincular). Esta
 * pagina muestra los que encontro en la red del usuario, para agregarlos con un toque (su
 * MAC se reconoce sola). Para cambiarlo de red no hace falta tocar la placa: "Cambiar WiFi"
 * le pide por internet que prenda su red (y si no encuentra su WiFi, la prende solo).
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

    /**
     * "Agregar a mi cuenta": el AquaControl que la pagina encontro en la red del usuario
     * (ya tiene internet y pregunto hace poco). Se crea en la cuenta y, la proxima vez que
     * pregunte (en unos segundos), recibe su API key.
     */
    public function agregar(int $id): RedirectResponse
    {
        // Tiene que ser uno de los que se ven desde esta red (sin los que ya son de otra cuenta).
        $nuevos = new DispositivoNuevoModel();
        $nuevo = null;
        foreach ($this->nuevosEnMiRed() as $fila) {
            if ((int) $fila['id'] === $id) {
                $nuevo = $fila;
            }
        }
        if ($nuevo === null) {
            return $this->volver('error', 'Ese AquaControl ya no aparece en tu red. Revisa que esté enchufado y conectado a tu WiFi.');
        }

        // Si ya era de esta cuenta (lo habian desvinculado), se reusa con sus lecturas; si no, se crea.
        $device = $this->dispositivoModel->buscarPorMac($nuevo['mac']);
        $deviceId = $device !== null ? (int) $device['id'] : $this->dispositivoModel->agregarPorMac($this->userId(), $nuevo['mac']);
        $nuevos->marcarAgregado($id, $deviceId);

        // Con un AquaControl, la cuenta deja de ser de prueba (ya no se borra).
        (new UserModel())->hacerPermanente($this->userId());

        return $this->volver('success', '¡Listo! Tu AquaControl quedó en tu cuenta. En unos segundos aparece como «Conectado».');
    }

    /**
     * Estado de cada dispositivo y cuantos AquaControl nuevos hay en la red del usuario
     * (lo pregunta funciones/dispositivos.js para actualizar la pagina sola).
     */
    public function estado(): ResponseInterface
    {
        $segundos = config(Feeding::class)->onlineThresholdSeconds;
        $estados = [];
        foreach ($this->dispositivoModel->porUsuario($this->userId()) as $device) {
            $estados[(string) $device['id']] = DispositivoModel::estado($device, $segundos);
        }

        return $this->response->setJSON([
            'estados' => $estados,
            'nuevos'  => array_column($this->nuevosEnMiRed(), 'id'),
        ]);
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
     * "Cambiar WiFi": le pide al AquaControl (por internet, con la cola de ordenes) que prenda
     * su red WiFi, para elegir otra red escaneando el QR de "Conectar mi AquaControl".
     */
    public function cambiarWifi(int $id): RedirectResponse
    {
        $device = $this->dispositivoModel->buscarParaUsuario($id, $this->userId());
        if (! $device) {
            return $this->volver('error', 'El dispositivo no existe o no tienes permisos sobre él.');
        }

        // Si no esta conectado no le llega la orden; pero si no encuentra su WiFi, prende su red solo.
        if (DispositivoModel::estado($device, config(Feeding::class)->onlineThresholdSeconds) !== 'conectado') {
            return $this->volver('error', 'Tu AquaControl no está conectado, así que no le llega la orden. Si no encuentra su WiFi, en un minuto prende su red solo: toca «Conectar mi AquaControl» y escanea el QR.');
        }

        (new ComandoDispositivoModel())->crearPedidoConfiguracion($this->userId(), $id);

        return redirect()->to(base_url('dispositivos') . '#conectar')->with('success', 'Listo: en unos segundos tu AquaControl prende su red WiFi. Toca «Conectar mi AquaControl», escanea el QR y elige la red nueva.');
    }

    // Anula la API key: el dispositivo deja de poder conectarse.
    public function desvincular(int $id): RedirectResponse
    {
        if (! $this->dispositivoModel->buscarParaUsuario($id, $this->userId())) {
            return $this->volver('error', 'El dispositivo no existe o no tienes permisos sobre él.');
        }

        $this->dispositivoModel->revocarApiKey($id);

        return $this->volver('success', 'Dispositivo desvinculado: ya no puede enviar lecturas. En unos segundos vuelve a aparecer arriba como nuevo, por si lo quieres agregar otra vez.');
    }

    /**
     * AquaControl nuevos que se ven desde la red del usuario, sin los que ya son de otra
     * cuenta (esos no se le muestran: no los puede agregar).
     */
    private function nuevosEnMiRed(): array
    {
        $nuevos = (new DispositivoNuevoModel())->visiblesDesde($this->request->getIPAddress());

        return array_values(array_filter($nuevos, function (array $nuevo): bool {
            $dueno = $this->dispositivoModel->buscarPorMac($nuevo['mac']);

            return $dueno === null || (int) $dueno['usuario_id'] === $this->userId();
        }));
    }

    // Muestra la pagina de dispositivos.
    private function listado(): string
    {
        // Cada dispositivo con su estado (conectado, sin conexion...) y su MAC lista para mostrar.
        $segundos = config(Feeding::class)->onlineThresholdSeconds;
        $devices = array_map(
            static fn (array $device): array => $device + [
                'estado'   => DispositivoModel::estado($device, $segundos),
                'macTexto' => empty($device['mac']) ? '' : DispositivoModel::mostrarMac($device['mac']),
            ],
            $this->dispositivoModel->porUsuario($this->userId())
        );

        // AquaControl nuevos (con internet, sin cuenta) que se ven desde la red del usuario.
        $nuevos = array_map(
            static fn (array $nuevo): array => $nuevo + [
                'nombre'   => 'AquaControl ' . substr($nuevo['mac'], -4),
                'macTexto' => DispositivoModel::mostrarMac($nuevo['mac']),
            ],
            $this->nuevosEnMiRed()
        );

        return view('devices/index', [
            'title'   => 'Dispositivos',
            'devices' => $devices,
            'nuevos'  => $nuevos,
            // Red del AquaControl (para el QR y para conectarse a mano).
            'red'     => config(RedAquaControl::class),
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
