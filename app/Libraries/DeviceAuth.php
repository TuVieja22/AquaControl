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
    private ?array $device = null;

    public function __construct(private readonly DispositivoModel $dispositivoModel)
    {
    }

    public function authenticate(RequestInterface $request): ?array
    {
        $apiKey = $this->extractKey($request);
        $this->device = $apiKey === '' ? null : $this->dispositivoModel->buscarPorApiKey($apiKey);

        if ($this->device !== null) {
            $this->dispositivoModel->registrarConexion((int) $this->device['id']);
        }

        return $this->device;
    }

    public function device(): ?array
    {
        return $this->device;
    }

    private function extractKey(RequestInterface $request): string
    {
        $apiKey = trim($request->getHeaderLine('X-Device-Key'));
        if ($apiKey !== '') {
            return $apiKey;
        }

        $authorization = trim($request->getHeaderLine('Authorization'));
        if (stripos($authorization, 'Bearer ') === 0) {
            return trim(substr($authorization, 7));
        }

        return '';
    }
}
