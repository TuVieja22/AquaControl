<?php

namespace App\Controllers;

use App\Models\AlimentacionModel;
use App\Models\ComandoDispositivoModel;
use App\Models\ConfiguracionPeceraModel;
use App\Models\SensorModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Endpoints que usa el ESP32. Todos pasan por el filtro `deviceauth`, que identifica al
 * dispositivo por su API key (no usan la sesion web ni el token CSRF).
 *
 *   POST dashboard/api/data               -> manda una lectura {"temperatura": 25.4, ...}
 *   GET  dashboard/api/commands           -> ordenes pendientes (p. ej. mover el servo)
 *   POST dashboard/api/commands/{id}/ack  -> {"estado": "ejecutado" | "fallido", "mensaje": "..."}
 */
class DeviceApi extends BaseController
{
    public function data(): ResponseInterface
    {
        $device = service('deviceAuth')->device();
        $userId = (int) $device['usuario_id'];
        $input = $this->entrada();

        $valido = is_array($input) && $input !== [] && $this->validateData($input, [
            'temperatura'     => 'permit_empty|decimal|greater_than[-10]|less_than[60]',
            'ph'              => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[14]',
            'turbidez'        => 'permit_empty|decimal',
            'nivel_agua'      => 'permit_empty|in_list[0,1]',
            'calefactor'      => 'permit_empty|in_list[0,1]',
            'modo_vacaciones' => 'permit_empty|in_list[0,1]',
        ]);

        if (! $valido) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator?->getErrors() ?? ['body' => 'Se esperaba un objeto JSON con las lecturas.'],
            ]);
        }

        $readingId = (new SensorModel())->insert([
            'usuario_id'      => $userId,
            'dispositivo_id'  => (int) $device['id'],
            'temperatura'     => $input['temperatura'] ?? null,
            'ph'              => $input['ph'] ?? null,
            'turbidez'        => $input['turbidez'] ?? null,
            'nivel_agua'      => (int) ($input['nivel_agua'] ?? 1),
            'calefactor'      => (int) ($input['calefactor'] ?? 0),
            'modo_vacaciones' => (int) ($input['modo_vacaciones'] ?? 0),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        // Respuesta corta para el microcontrolador: confirma y devuelve la config vigente.
        $config = (new ConfiguracionPeceraModel())->deUsuario($userId);

        return $this->response->setStatusCode(201)->setJSON([
            'success'    => true,
            'lectura_id' => $readingId,
            'config'     => [
                'temp_objetivo'   => (float) $config['temp_objetivo'],
                'modo_vacaciones' => (int) $config['modo_vacaciones'],
            ],
        ]);
    }

    public function commands(): ResponseInterface
    {
        $device = service('deviceAuth')->device();

        // Los sensores puros no tienen servo: no reciben ordenes del alimentador.
        if ($device['tipo'] === 'sensor') {
            return $this->response->setJSON(['success' => true, 'comandos' => []]);
        }

        $userId = (int) $device['usuario_id'];
        $comandoModel = new ComandoDispositivoModel();
        $comandoModel->programarAlimentaciones($userId, (new ConfiguracionPeceraModel())->porUsuario($userId) ?? []);

        $comandos = array_map(static fn (array $comando): array => [
            'id'     => (int) $comando['id'],
            'accion' => $comando['accion'],
            'origen' => $comando['origen'],
        ] + ComandoDispositivoModel::parametros($comando), $comandoModel->reclamarPendientes($device));

        return $this->response->setJSON(['success' => true, 'comandos' => $comandos]);
    }

    public function ack(int $commandId): ResponseInterface
    {
        $device = service('deviceAuth')->device();
        $input = $this->entrada();
        $estado = is_array($input) ? (string) ($input['estado'] ?? 'ejecutado') : '';

        if (! in_array($estado, ['ejecutado', 'fallido'], true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'El estado debe ser "ejecutado" o "fallido".',
            ]);
        }

        $comandoModel = new ComandoDispositivoModel();
        $comando = $comandoModel->finalizar($commandId, (int) $device['id'], $estado, isset($input['mensaje']) ? (string) $input['mensaje'] : null);

        if ($comando === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Comando inexistente o ya finalizado.',
            ]);
        }

        if ($estado === 'ejecutado' && $comando['accion'] === ComandoDispositivoModel::ACCION_ALIMENTAR) {
            $this->registrarAlimentacion($comando);
        }

        return $this->response->setJSON(['success' => true]);
    }

    /** Cuerpo del pedido: JSON o formulario. Null si el JSON vino mal formado. */
    private function entrada(): ?array
    {
        try {
            return $this->request->is('json') ? $this->request->getJSON(true) : $this->request->getPost();
        } catch (Throwable) {
            return null;
        }
    }

    private function registrarAlimentacion(array $comando): void
    {
        $userId = (int) $comando['usuario_id'];
        $tipo = 'manual';

        if ($comando['origen'] === 'programado') {
            $vacaciones = (int) ((new ConfiguracionPeceraModel())->porUsuario($userId)['modo_vacaciones'] ?? 0) === 1;
            $tipo = $vacaciones ? 'vacaciones' : 'automatica';
        }

        (new AlimentacionModel())->insert([
            'usuario_id'      => $userId,
            'cantidad_gramos' => (float) (ComandoDispositivoModel::parametros($comando)['gramos'] ?? 0),
            'tipo'            => $tipo,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
    }
}
