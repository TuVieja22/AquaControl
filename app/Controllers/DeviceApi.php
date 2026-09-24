<?php

namespace App\Controllers;

use App\Models\AlimentacionModel;
use App\Models\ComandoDispositivoModel;
use App\Models\ConfiguracionPeceraModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Endpoints que consume el ESP32 (autenticados con el filtro `deviceauth`).
 *
 *   GET  dashboard/api/commands           -> comandos pendientes (p. ej. mover el servo)
 *   POST dashboard/api/commands/{id}/ack  -> {"estado": "ejecutado" | "fallido", "mensaje": "..."}
 */
class DeviceApi extends BaseController
{
    /** Los sensores puros no tienen servo: no reciben comandos de alimentacion. */
    private const TIPOS_SIN_ACTUADOR = ['sensor'];

    private ComandoDispositivoModel $comandoModel;

    public function __construct()
    {
        $this->comandoModel = new ComandoDispositivoModel();
    }

    public function commands(): ResponseInterface
    {
        $device = service('deviceAuth')->device();
        if ($device === null) {
            return $this->unauthorized();
        }

        if (in_array($device['tipo'], self::TIPOS_SIN_ACTUADOR, true)) {
            return $this->response->setJSON(['success' => true, 'comandos' => []]);
        }

        $userId = (int) $device['usuario_id'];
        $config = (new ConfiguracionPeceraModel())->porUsuario($userId) ?? [];
        $this->comandoModel->programarAlimentaciones($userId, $config);

        $commands = array_map(static function (array $command): array {
            return array_merge([
                'id'     => (int) $command['id'],
                'accion' => $command['accion'],
                'origen' => $command['origen'],
            ], ComandoDispositivoModel::parametros($command));
        }, $this->comandoModel->reclamarPendientes($device));

        return $this->response->setJSON([
            'success'  => true,
            'comandos' => $commands,
        ]);
    }

    public function ack(int $commandId): ResponseInterface
    {
        $device = service('deviceAuth')->device();
        if ($device === null) {
            return $this->unauthorized();
        }

        try {
            $input = $this->request->is('json') ? $this->request->getJSON(true) : $this->request->getPost();
        } catch (Throwable) {
            $input = null;
        }

        $status = is_array($input) ? (string) ($input['estado'] ?? 'ejecutado') : '';
        if (! in_array($status, ['ejecutado', 'fallido'], true)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['success' => false, 'message' => 'El estado debe ser "ejecutado" o "fallido".']);
        }

        $command = $this->comandoModel->finalizar(
            $commandId,
            (int) $device['id'],
            $status,
            isset($input['mensaje']) ? (string) $input['mensaje'] : null
        );

        if ($command === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['success' => false, 'message' => 'Comando inexistente o ya finalizado.']);
        }

        if ($status === 'ejecutado' && $command['accion'] === ComandoDispositivoModel::ACCION_ALIMENTAR) {
            $this->registrarAlimentacion($command);
        }

        return $this->response->setJSON(['success' => true]);
    }

    private function registrarAlimentacion(array $command): void
    {
        $userId = (int) $command['usuario_id'];
        $type = 'manual';

        if ($command['origen'] === 'programado') {
            $config = (new ConfiguracionPeceraModel())->porUsuario($userId) ?? [];
            $type = (int) ($config['modo_vacaciones'] ?? 0) === 1 ? 'vacaciones' : 'automatica';
        }

        (new AlimentacionModel())->insert([
            'usuario_id'      => $userId,
            'cantidad_gramos' => (float) (ComandoDispositivoModel::parametros($command)['gramos'] ?? 0),
            'tipo'            => $type,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    private function unauthorized(): ResponseInterface
    {
        return $this->response
            ->setStatusCode(401)
            ->setJSON(['success' => false, 'message' => 'Dispositivo no autenticado.']);
    }
}
