<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Claves para mandar notificaciones push (alertas de la pecera al celular).
 * Se cargan en .env (webpush.*); para crearlas: php spark notificaciones:claves
 */
class WebPush extends BaseConfig
{
    /** Clave publica VAPID (base64url). La usa tambien el navegador al suscribirse. */
    public string $clavePublica = '';

    /** Clave privada VAPID (base64url). Secreta: no se sube a GitHub. */
    public string $clavePrivada = '';

    /** Contacto para los servicios de push (lo piden por si hay que avisar algo de esta pagina). */
    public string $contacto = 'mailto:contacto@aquacontrol.local';
}
