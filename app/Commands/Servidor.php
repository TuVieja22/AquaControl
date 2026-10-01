<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Levanta la pagina para usarla en esta PC y en la red local (celular, ESP32).
 *
 *   php spark servir            (puerto 8080)
 *   php spark servir --puerto 8081
 *
 * Mejoras frente a `php spark serve`:
 * - Atiende por IPv4 y por IPv6 al mismo tiempo (dos procesos). En Windows,
 *   "localhost" prueba primero IPv6: con un servidor solo IPv4 cada pedido
 *   esperaba ~0,2 s antes de conectar. Ademas el navegador y el ESP32 ya no
 *   hacen fila en el mismo proceso.
 * - Activa OPcache: PHP guarda el codigo ya compilado entre pedidos. Igual
 *   revisa los archivos en cada pedido, asi que los cambios se ven al recargar.
 */
class Servidor extends BaseCommand
{
    protected $group       = 'AquaControl';
    protected $name        = 'servir';
    protected $description = 'Inicia la pagina (IPv4 + IPv6, con OPcache) en el puerto indicado.';
    protected $usage       = 'servir [--puerto 8080]';
    protected $options     = ['--puerto' => 'Puerto donde escuchar (por defecto 8080).'];

    public function run(array $params)
    {
        $puerto = (int) (CLI::getOption('puerto') ?? 8080);

        $this->cerrarServidoresAnteriores();

        $procesos = [];
        foreach (['0.0.0.0', '[::]'] as $direccion) {
            $procesos[$direccion] = proc_open(
                $this->comando($direccion, $puerto),
                [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR],
                $pipes
            );
        }

        sleep(1);
        foreach ($procesos as $direccion => $proceso) {
            if (! proc_get_status($proceso)['running']) {
                // Sin IPv6 la pagina igual anda por IPv4 (el ESP32 siempre usa IPv4).
                CLI::write("No se pudo escuchar en {$direccion}:{$puerto}.", 'yellow');
                unset($procesos[$direccion]);
            }
        }

        if ($procesos === []) {
            CLI::error("No se pudo iniciar la pagina: el puerto {$puerto} esta ocupado.");

            return EXIT_ERROR;
        }

        CLI::write("AquaControl listo en http://localhost:{$puerto} (en la red: http://IP-DE-ESTA-PC:{$puerto})", 'green');
        CLI::write('Para apagarlo cerra esta ventana o apreta Ctrl+C.');

        while ($procesos !== []) {
            foreach ($procesos as $direccion => $proceso) {
                if (! proc_get_status($proceso)['running']) {
                    unset($procesos[$direccion]);
                }
            }
            sleep(1);
        }

        return EXIT_SUCCESS;
    }

    private function comando(string $direccion, int $puerto): string
    {
        $ajustes = [
            'opcache.enable_cli=1',
            'opcache.validate_timestamps=1',
            'opcache.revalidate_freq=0',
        ];
        if (! extension_loaded('Zend OPcache')) {
            array_unshift($ajustes, 'zend_extension=opcache');
        }

        return implode(' ', [
            escapeshellarg(PHP_BINARY),
            implode(' ', array_map(static fn (string $ajuste): string => '-d ' . $ajuste, $ajustes)),
            "-S {$direccion}:{$puerto}",
            '-t ' . escapeshellarg(rtrim(FCPATH, '\\/')),
            escapeshellarg(SYSTEMPATH . 'rewrite.php'),
        ]);
    }

    /**
     * Cierra servidores de esta pagina que hayan quedado abiertos (p. ej. de una
     * ventana que no se cerro bien), para que el puerto quede libre.
     */
    private function cerrarServidoresAnteriores(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return;
        }

        // Solo los servidores de ESTE proyecto: su linea de comandos incluye la ruta
        // completa de su rewrite.php (asi no se cierran servidores de otros proyectos).
        $rewrite = SYSTEMPATH . 'rewrite.php';
        $script = "Get-CimInstance Win32_Process | Where-Object { \$_.Name -eq 'php.exe' -and "
            . "\$_.CommandLine -like '*{$rewrite}*' } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force }";

        exec('powershell -NoProfile -Command ' . escapeshellarg($script) . ' 2>NUL');
    }
}
