<?php

namespace App\Commands;

use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Borra las cuentas de prueba vencidas (las que no conectaron un AquaControl a tiempo).
 *
 *   php spark cuentas:limpiar
 *
 * Quien vuelve a entrar con una cuenta vencida la pierde en ese momento (AuthFilter); esto
 * borra las de quienes no vuelven. Tambien lo hace solo, cada hora, `php spark alertas:revisar --siempre`.
 */
class LimpiarCuentas extends BaseCommand
{
    // Nombre y descripcion del comando (lo que muestra "php spark").
    protected $group       = 'AquaControl';
    protected $name        = 'cuentas:limpiar';
    protected $description = 'Borra las cuentas de prueba vencidas (sin AquaControl conectado ni comprado).';

    // Lo que hace el comando al ejecutarlo.
    public function run(array $params)
    {
        $borradas = (new UserModel())->borrarPruebasVencidas();
        CLI::write(date('H:i:s') . " Cuentas de prueba vencidas borradas: {$borradas}.");

        return EXIT_SUCCESS;
    }
}
