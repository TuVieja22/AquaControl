<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Credenciales y opciones de Mercado Pago. Se cargan desde .env con el prefijo
 * "mercadopago." (nunca se suben a GitHub), por ejemplo:
 *   mercadopago.accessToken = APP_USR-...
 *   mercadopago.publicKey   = APP_USR-...
 */
class MercadoPago extends BaseConfig
{
    /** Clave privada: solo la usa el servidor para crear preferencias y consultar pagos. */
    public string $accessToken = '';

    /** Clave publica: la usa el navegador para mostrar el boton de pago. */
    public string $publicKey = '';

    /** Si se define, se valida la firma (x-signature) de los avisos de pago (webhooks). */
    public string $webhookSecret = '';

    /** "local" para pruebas en localhost (sin auto_return). */
    public string $runtime = '';

    /** URL publica para los avisos de pago. Vacia = el webhook propio si el sitio usa https. */
    public string $notificationUrl = '';

    /** Cuotas maximas ofrecidas (0 = las que decida Mercado Pago). */
    public int $installments = 12;

    /**
     * true/false fuerza el regreso automatico al sitio despues de pagar. Si no esta en
     * .env se usa en produccion y no en "local".
     */
    public $autoReturn = null;

    /** Texto que aparece en el resumen de la tarjeta (mayusculas, max. 22). Vacio = nombre del producto. */
    public string $statementDescriptor = '';

    /** Endpoint del backend que crea la preferencia (normalmente no hace falta cambiarlo). */
    public string $preferenceUrl = '';

    public function esLocal(): bool
    {
        return strtolower($this->runtime) === 'local';
    }

    public function usarAutoReturn(): bool
    {
        if ($this->autoReturn === null) {
            return ! $this->esLocal();
        }

        return filter_var($this->autoReturn, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? ! $this->esLocal();
    }
}
