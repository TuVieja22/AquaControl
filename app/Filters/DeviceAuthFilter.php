<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Protege los endpoints que consumen los dispositivos IoT (ESP32).
 * No usa la sesion web: exige una API key valida del dispositivo.
 */
class DeviceAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (service('deviceAuth')->authenticate($request) !== null) {
            return null;
        }

        return service('response')
            ->setStatusCode(401)
            ->setHeader('WWW-Authenticate', 'Bearer realm="aquacontrol-device"')
            ->setJSON([
                'success' => false,
                'message' => 'API key de dispositivo ausente o invalida.',
            ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
