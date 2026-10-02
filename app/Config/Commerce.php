<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Datos del producto que se vende en la portada.
 *
 * Cada valor se puede cambiar desde .env con el prefijo "commerce.", por ejemplo:
 *   commerce.unitPrice = 250000
 *   commerce.productName = 'AquaControl'
 */
class Commerce extends BaseConfig
{
    public string $productSku = 'aquacontrol';
    public string $productName = 'AquaControl';
    public string $productDescription = 'Elegi la cantidad, revisa tu pedido y continua con el medio de pago que prefieras sin salir de la experiencia AquaControl.';

    /** Precio por unidad. Vacio = "Consultar" y el checkout queda deshabilitado. */
    public string $unitPrice = '';

    public string $currency = 'ARS';
    public int $maxQuantity = 6;
    public string $deliveryMessage = 'La entrega y activacion se coordinan al confirmar el pago.';
    public string $locale = 'es-AR';

    /** Precio como numero, o null si no esta configurado (o no es mayor a cero). */
    public function precio(): ?float
    {
        return is_numeric($this->unitPrice) && (float) $this->unitPrice > 0 ? round((float) $this->unitPrice, 2) : null;
    }

    public function moneda(): string
    {
        return strtoupper($this->currency);
    }

    public function cantidadMaxima(): int
    {
        return max(1, $this->maxQuantity);
    }
}
