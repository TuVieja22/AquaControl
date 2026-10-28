<?php

namespace App\Controllers;

use App\Libraries\Alertas;
use App\Models\AlimentacionModel;
use App\Models\ComandoDispositivoModel;
use App\Models\ConfiguracionPeceraModel;
use App\Models\DispositivoModel;
use App\Models\DispositivoNuevoModel;
use App\Models\SensorModel;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Endpoints que usa el ESP32 (no usan la sesion web ni el token CSRF).
 *
 *   POST dashboard/api/vincular           -> {"mac": "...", "token": "..."}: pregunta si ya lo agregaron
 *
 * Los demas pasan por el filtro `deviceauth`, que identifica al dispositivo por su API key:
 *
 *   POST dashboard/api/data              -> manda una lectura {"temperatura": 25.4, ...}
 *   GET  dashboard/api/commands           -> ordenes pendientes (p. ej. mover el servo)
 *   POST dashboard/api/commands/{id}/ack  -> {"estado": "ejecutado" | "fallido", "mensaje": "..."}
 */
class DeviceApi extends BaseController
{
    /** Pedidos de vinculacion por minuto desde una misma direccion (el ESP32 pregunta cada 10 s). */
    private const PEDIDOS_VINCULAR_POR_MINUTO = 20;

    /**
     * POST dashboard/api/vincular {"mac": "...", "token": "..."} (sin API key: todavia no tiene).
     * Un AquaControl recien conectado a internet pregunta si ya lo agregaron a una cuenta:
     *   202 = todavia no (la pagina lo muestra a quien entre desde su misma red);
     *   201 = si: responde con su API key (se entrega una sola vez);
     *   403 = ese AquaControl ya lo agregaron con otro token (no es el mismo aparato).
     */
    public function vincular(): ResponseInterface
    {
        // Freno: pocos pedidos por minuto desde la misma direccion.
        $ip = $this->request->getIPAddress();
        if (! service('throttler')->check('vincular-' . md5($ip), self::PEDIDOS_VINCULAR_POR_MINUTO, MINUTE)) {
            return $this->response->setStatusCode(429)->setJSON(['success' => false, 'message' => 'Demasiados pedidos.']);
        }

        // MAC (12 hexadecimales) y token (32 hexadecimales, lo crea el ESP32 una sola vez).
        $input = $this->entrada() ?? [];
        $mac = DispositivoModel::normalizarMac((string) ($input['mac'] ?? ''));
        $token = strtolower((string) ($input['token'] ?? ''));
        if ($mac === null || ! preg_match('/^[0-9a-f]{32}$/', $token)) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Falta la MAC o el token.']);
        }

        // Se anota (o se actualiza su red); si el token no es el suyo, no se le da nada.
        $nuevos = new DispositivoNuevoModel();
        $fila = $nuevos->registrar($mac, $token, $ip);
        if ($fila === null) {
            return $this->response->setStatusCode(403)->setJSON(['success' => false, 'message' => 'Este AquaControl ya se agregó desde otro aparato.']);
        }

        // Todavia nadie lo agrego.
        if ($fila['dispositivo_id'] === null) {
            return $this->response->setStatusCode(202)->setJSON(['success' => true, 'estado' => 'esperando']);
        }

        // Ya lo agregaron: se le da su API key y deja de estar entre los nuevos.
        $dispositivos = new DispositivoModel();
        $device = $dispositivos->find((int) $fila['dispositivo_id']);
        $apiKey = $dispositivos->generarApiKey((int) $device['id']);
        $nuevos->delete((int) $fila['id']);

        return $this->response->setStatusCode(201)->setJSON([
            'success'     => true,
            'api_key'     => $apiKey,
            'dispositivo' => ['id' => (int) $device['id'], 'nombre' => $device['nombre']],
        ]);
    }

    // El ESP32 manda una lectura de los sensores y se guarda.
    public function data(): ResponseInterface
    {
        // Dispositivo que llama (el filtro lo reconocio por su API key) y lo que mando.
        $device = service('deviceAuth')->device();
        $userId = (int) $device['usuario_id'];
        $input = $this->entrada();

        // Cada dato es opcional, pero si viene tiene que estar dentro de limites razonables.
        // Si el ESP32 manda otros datos (por ejemplo turbidez o modo_vacaciones), se ignoran.
        $valido = is_array($input) && $input !== [] && $this->validateData($input, [
            'temperatura' => 'permit_empty|decimal|greater_than[-10]|less_than[60]',
            'ph'          => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[14]',
            'nivel_agua'  => 'permit_empty|in_list[0,1]',
            'calefactor'  => 'permit_empty|in_list[0,1]',
        ]);

        // Datos incorrectos: se responde con los errores.
        if (! $valido) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'errors'  => $this->validator?->getErrors() ?? ['body' => 'Se esperaba un objeto JSON con las lecturas.'],
            ]);
        }

        // Guarda la lectura del dispositivo (el usuario sale del dispositivo: no se repite aca).
        $readingId = (new SensorModel())->insert([
            'dispositivo_id' => (int) $device['id'],
            'temperatura'    => $input['temperatura'] ?? null,
            'ph'             => $input['ph'] ?? null,
            'nivel_agua'     => (int) ($input['nivel_agua'] ?? 1),
            'calefactor'     => (int) ($input['calefactor'] ?? 0),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        // Si algo esta fuera de rango, queda una alerta (la notificacion sale aparte).
        (new Alertas())->revisarLectura($userId, $input);

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

    // El ESP32 pregunta si tiene ordenes pendientes (por ejemplo, mover el servo).
    public function commands(): ResponseInterface
    {
        $device = service('deviceAuth')->device();

        // Los sensores puros no tienen servo: no reciben ordenes del alimentador.
        if ($device['tipo'] === 'sensor') {
            return $this->response->setJSON(['success' => true, 'comandos' => []]);
        }

        // Si llego la hora de un horario programado, se crea su orden.
        $userId = (int) $device['usuario_id'];
        $comandoModel = new ComandoDispositivoModel();
        $comandoModel->programarAlimentaciones($userId, (new ConfiguracionPeceraModel())->porUsuario($userId) ?? []);

        // Toma las ordenes pendientes y las deja marcadas como enviadas a este dispositivo.
        $comandos = array_map(static fn (array $comando): array => [
            'id'     => (int) $comando['id'],
            'accion' => $comando['accion'],
            'origen' => $comando['origen'],
        ] + ComandoDispositivoModel::parametros($comando), $comandoModel->reclamarPendientes($device));

        return $this->response->setJSON(['success' => true, 'comandos' => $comandos]);
    }

    // El ESP32 avisa como termino una orden (ejecutada o fallida).
    public function ack(int $commandId): ResponseInterface
    {
        $device = service('deviceAuth')->device();
        $input = $this->entrada();
        $estado = is_array($input) ? (string) ($input['estado'] ?? 'ejecutado') : '';

        // Solo se aceptan esos dos resultados.
        if (! in_array($estado, ['ejecutado', 'fallido'], true)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'El estado debe ser "ejecutado" o "fallido".',
            ]);
        }

        // Cierra la orden (tiene que ser de este dispositivo y estar sin terminar).
        $comandoModel = new ComandoDispositivoModel();
        $comando = $comandoModel->finalizar($commandId, (int) $device['id'], $estado, isset($input['mensaje']) ? (string) $input['mensaje'] : null);

        if ($comando === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Comando inexistente o ya finalizado.',
            ]);
        }

        // Si se alimento de verdad, se anota en el historial de alimentaciones.
        if ($estado === 'ejecutado' && $comando['accion'] === ComandoDispositivoModel::ACCION_ALIMENTAR) {
            $this->registrarAlimentacion($comando);
        }

        // Si no pudo alimentar, se avisa al usuario.
        if ($estado === 'fallido' && $comando['accion'] === ComandoDispositivoModel::ACCION_ALIMENTAR) {
            (new Alertas())->alimentacionFallida((int) $device['usuario_id'], $device['nombre'], isset($input['mensaje']) ? (string) $input['mensaje'] : null);
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

    // Anota la alimentacion en el historial, con su tipo (manual, automatica o vacaciones).
    private function registrarAlimentacion(array $comando): void
    {
        $userId = (int) $comando['usuario_id'];
        $tipo = 'manual';

        // Las programadas cuentan como "vacaciones" si ese modo esta activo.
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
