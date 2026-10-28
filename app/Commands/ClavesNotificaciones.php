<?php

namespace App\Commands;

use App\Libraries\WebPush;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Crea las claves VAPID que necesitan las notificaciones push y las muestra para pegarlas en .env.
 *
 *   php spark notificaciones:claves
 *
 * Se hace una sola vez: si se cambian, los celulares que ya aceptaron las notificaciones
 * tienen que volver a activarlas.
 */
class ClavesNotificaciones extends BaseCommand
{
    // Nombre y descripcion del comando (lo que muestra "php spark").
    protected $group       = 'AquaControl';
    protected $name        = 'notificaciones:claves';
    protected $description = 'Crea las claves de las notificaciones push (para pegar en .env).';

    // Lo que hace el comando al ejecutarlo.
    public function run(array $params)
    {
        $claves = WebPush::nuevasClavesVapid();

        CLI::write('Pega estas dos lineas en el archivo .env (la privada no se comparte):', 'yellow');
        CLI::write('webpush.clavePublica = ' . $claves['publica']);
        CLI::write('webpush.clavePrivada = ' . $claves['privada']);

        return EXIT_SUCCESS;
    }
}
