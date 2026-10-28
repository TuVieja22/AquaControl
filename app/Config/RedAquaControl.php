<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Red WiFi que prende cada AquaControl para configurarlo (la misma en todos, asi el QR de
 * Dispositivos y del manual es siempre el mismo). Tiene que coincidir con RED_NOMBRE y
 * RED_CLAVE del firmware (firmware/aquacontrol_esp32/aquacontrol_esp32.ino) y con la imagen
 * public/img/qr-red-aquacontrol.png (si se cambian, hay que generar el QR de nuevo).
 */
class RedAquaControl extends BaseConfig
{
    // Nombre de la red.
    public string $nombre = 'AquaControl';

    // Contrasena de la red.
    public string $clave = 'aquacontrol';
}
