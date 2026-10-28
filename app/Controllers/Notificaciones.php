<?php

namespace App\Controllers;

use App\Libraries\Alertas;
use App\Models\SuscripcionPushModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Activa o desactiva las notificaciones de alertas en el navegador del usuario (celular o PC).
 * Lo usa funciones/notificaciones.js desde "Mi cuenta".
 */
class Notificaciones extends BaseController
{
    /**
     * Servicios de push de los navegadores (Chrome, Firefox, Edge y Safari). Solo se aceptan
     * direcciones de estos: asi nadie puede hacer que la pagina mande pedidos a cualquier lado.
     */
    private const SERVICIOS = ['fcm.googleapis.com', 'push.services.mozilla.com', 'notify.windows.com', 'push.apple.com'];

    // Guarda la suscripcion de este navegador: {"endpoint": "...", "keys": {"p256dh": "...", "auth": "..."}}.
    public function guardar(): ResponseInterface
    {
        $datos = $this->request->getJSON(true) ?? [];
        $endpoint = (string) ($datos['endpoint'] ?? '');
        $p256dh = (string) ($datos['keys']['p256dh'] ?? '');
        $auth = (string) ($datos['keys']['auth'] ?? '');

        // Las claves son base64url (letras, numeros, - y _) y la direccion, de un servicio conocido.
        $clavesOk = preg_match('/^[A-Za-z0-9_-]{80,100}$/', $p256dh) && preg_match('/^[A-Za-z0-9_-]{16,30}$/', $auth);
        if (! $clavesOk || strlen($endpoint) > 500 || ! $this->esServicioConocido($endpoint)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Este navegador mandó datos de notificación que no se pueden usar.',
            ]);
        }

        (new SuscripcionPushModel())->guardar($this->userId(), $endpoint, $p256dh, $auth);

        return $this->response->setJSON(['success' => true]);
    }

    // Desactiva las notificaciones en este navegador.
    public function borrar(): ResponseInterface
    {
        $datos = $this->request->getJSON(true) ?? [];
        (new SuscripcionPushModel())->borrar($this->userId(), (string) ($datos['endpoint'] ?? ''));

        return $this->response->setJSON(['success' => true]);
    }

    // Manda una notificacion de prueba a todos los navegadores del usuario.
    public function probar(): ResponseInterface
    {
        $entregadas = (new Alertas())->enviarPrueba($this->userId());

        return $this->response->setJSON([
            'success'    => $entregadas > 0,
            'entregadas' => $entregadas,
            'message'    => $entregadas > 0
                ? 'Listo: en unos segundos te llega la notificación de prueba.'
                : 'No se pudo mandar. Revisa tu conexión a internet y vuelve a activar las notificaciones.',
        ]);
    }

    // Si la direccion es https y de uno de los servicios de push conocidos.
    private function esServicioConocido(string $endpoint): bool
    {
        $partes = parse_url($endpoint);
        if (($partes['scheme'] ?? '') !== 'https' || empty($partes['host'])) {
            return false;
        }
        foreach (self::SERVICIOS as $servicio) {
            if ($partes['host'] === $servicio || str_ends_with($partes['host'], '.' . $servicio)) {
                return true;
            }
        }

        return false;
    }
}
