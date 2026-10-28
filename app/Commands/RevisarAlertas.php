<?php

namespace App\Commands;

use App\Libraries\Alertas;
use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Revisa las alertas que no dependen de una lectura y manda las notificaciones pendientes.
 *
 *   php spark alertas:revisar             (una vez)
 *   php spark alertas:revisar --siempre   (cada 10 s, sin parar; lo arranca `php spark servir`)
 *
 * - Avisa de los AquaControl que dejaron de mandar datos.
 * - Manda al celular las alertas que todavia no salieron (Web Push).
 * - Con --siempre, una vez por hora borra las cuentas de prueba vencidas (cuentas:limpiar).
 */
class RevisarAlertas extends BaseCommand
{
    // Nombre, descripcion y opciones del comando (lo que muestra "php spark").
    protected $group       = 'AquaControl';
    protected $name        = 'alertas:revisar';
    protected $description = 'Avisa de los AquaControl desconectados y manda las notificaciones pendientes.';
    protected $usage       = 'alertas:revisar [--siempre]';
    protected $options     = ['--siempre' => 'Repetir cada 10 segundos sin parar.'];

    /** Cada cuantos segundos se repite con --siempre. */
    private const SEGUNDOS = 10;

    /** Cada cuantos segundos se borran las cuentas de prueba vencidas (con --siempre). */
    private const SEGUNDOS_LIMPIEZA = 3600;

    // Lo que hace el comando al ejecutarlo.
    public function run(array $params)
    {
        $siempre = CLI::getOption('siempre') !== null;
        $ultimaLimpieza = 0;

        do {
            // Con --siempre, una vez por hora tambien borra las cuentas de prueba vencidas.
            if ($siempre && time() - $ultimaLimpieza >= self::SEGUNDOS_LIMPIEZA) {
                $ultimaLimpieza = time();
                try {
                    $borradas = (new UserModel())->borrarPruebasVencidas();
                    if ($borradas > 0) {
                        CLI::write(date('H:i:s') . " Cuentas de prueba vencidas borradas: {$borradas}.");
                    }
                } catch (Throwable $error) {
                    CLI::error(date('H:i:s') . ' No se pudieron borrar las cuentas vencidas: ' . $error->getMessage());
                }
            }

            try {
                $alertas = new Alertas();
                $desconectados = $alertas->revisarDesconectados();
                $entregadas = $alertas->enviarPendientes();
                // Con --siempre solo se muestra algo cuando paso algo (si no, llena la ventana).
                if (! $siempre || $desconectados > 0 || $entregadas > 0) {
                    CLI::write(date('H:i:s') . " Alertas: {$desconectados} desconexiones nuevas, {$entregadas} notificaciones entregadas.");
                }
            } catch (Throwable $error) {
                // Por ejemplo, MySQL apagado o reiniciado: la conexion vieja ya no sirve. Se abre
                // una nueva para la proxima vuelta (si MySQL sigue apagado, se vuelve a probar).
                CLI::error(date('H:i:s') . ' No se pudieron revisar las alertas: ' . $error->getMessage());
                try {
                    db_connect()->reconnect();
                } catch (Throwable) {
                    // Todavia no hay MySQL: en 10 s se prueba otra vez.
                }
            }

            if ($siempre) {
                sleep(self::SEGUNDOS);
            }
        } while ($siempre);

        return EXIT_SUCCESS;
    }
}
