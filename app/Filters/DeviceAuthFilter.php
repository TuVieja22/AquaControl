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
    // Antes de la pagina: deja pasar solo si la API key es de un dispositivo registrado.
    public function before(RequestInterface $request, $arguments = null)
    {
        if (service('deviceAuth')->authenticate($request) !== null) {
            return null;
        }

        // Sin clave valida responde 401 (no autorizado).
        return service('response')
            ->setStatusCode(401)
            ->setHeader('WWW-Authenticate', 'Bearer realm="aquacontrol-device"')
            ->setJSON([
                'success' => false,
                'message' => 'API key de dispositivo ausente o invalida.',
            ]);
    }

    // Despues de la pagina no hace nada.
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
