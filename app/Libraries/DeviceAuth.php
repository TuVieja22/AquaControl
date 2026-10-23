<?php

namespace App\Libraries;

use App\Models\DispositivoModel;
use CodeIgniter\HTTP\RequestInterface;

/**
 * Resuelve el dispositivo que hace la peticion a partir de su API key.
 *
 * La key se acepta en el header `X-Device-Key` o como `Authorization: Bearer <key>`.
 */
class DeviceAuth
{
    // Dispositivo reconocido en este pedido (null si la clave no sirve).
    private ?array $device = null;

    // Recibe el modelo de dispositivos ya armado.
    public function __construct(private readonly DispositivoModel $dispositivoModel)
    {
    }

    // Busca el dispositivo por la API key del pedido y anota que se conecto.
    public function authenticate(RequestInterface $request): ?array
    {
        $apiKey = $this->extractKey($request);
        $this->device = $apiKey === '' ? null : $this->dispositivoModel->buscarPorApiKey($apiKey);

        if ($this->device !== null) {
            $this->dispositivoModel->registrarConexion($this->device);
        }

        return $this->device;
    }

    // Dispositivo que se reconocio en authenticate().
    public function device(): ?array
    {
        return $this->device;
    }

    // Saca la API key de los headers del pedido.
    private function extractKey(RequestInterface $request): string
    {
        $apiKey = trim($request->getHeaderLine('X-Device-Key'));
        if ($apiKey !== '') {
            return $apiKey;
        }

        // Segunda forma aceptada: el header Authorization con la palabra Bearer adelante.
        $authorization = trim($request->getHeaderLine('Authorization'));

        return stripos($authorization, 'Bearer ') === 0 ? trim(substr($authorization, 7)) : '';
    }
}
