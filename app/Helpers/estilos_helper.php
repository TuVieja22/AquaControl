<?php

/*
 * Hojas de estilo de las vistas. Se cargan solas en todas las paginas
 * (ver $helpers en app/Config/Autoload.php).
 *
 * Cada vista tiene su hoja en public/css, con su misma ruta y nombre:
 *   app/Views/auth/login.php  ->  public/css/auth/login.css
 */

if (! function_exists('usar_css')) {
    /**
     * Anota la hoja de estilos de una vista. Va en la primera linea de la vista:
     *
     *   <?php usar_css('css/auth/login.css') ?>
     *
     * No escribe nada: los <link> los arma enlaces_css() dentro del <head>.
     * Sin argumento devuelve las hojas anotadas hasta ese momento y vacia la lista.
     *
     * @return list<string>
     */
    function usar_css(?string $archivo = null): array
    {
        static $anotadas = [];

        if ($archivo === null) {
            [$lista, $anotadas] = [$anotadas, []];

            return $lista;
        }

        // Un componente que aparece dos veces en la pagina pide su hoja una sola vez.
        if (! in_array($archivo, $anotadas, true)) {
            $anotadas[] = $archivo;
        }

        return $anotadas;
    }
}

if (! function_exists('enlaces_css')) {
    /**
     * Los <link> de todas las hojas anotadas con usar_css(). Lo llama layouts/main.php
     * dentro del <head>.
     *
     * Van de lo mas general a lo mas particular (moldes, componentes y por ultimo la
     * pagina con sus partes), asi una vista puede pisar un estilo de su molde.
     */
    function enlaces_css(): string
    {
        $moldes = $componentes = $vistas = [];

        foreach (usar_css() as $archivo) {
            if (str_starts_with($archivo, 'css/layouts/')) {
                // El molde de mas afuera (main) se arma ultimo, pero su hoja va primera.
                array_unshift($moldes, $archivo);
            } elseif (str_starts_with($archivo, 'css/components/')) {
                $componentes[] = $archivo;
            } else {
                $vistas[] = $archivo;
            }
        }

        $links = array_map(
            static fn (string $archivo): string => '<link rel="stylesheet" href="' . base_url($archivo) . '">',
            [...$moldes, ...$componentes, ...$vistas]
        );

        // Un <link> por renglon, con la sangria del <head>.
        return implode("\n  ", $links) . "\n";
    }
}
