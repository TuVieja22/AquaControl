<?php

/*
 * Ayudas para los formularios de las vistas. Se cargan solas en todas las paginas
 * (ver $helpers en app/Config/Autoload.php).
 */

if (! function_exists('error_campo')) {
    /**
     * Cajita roja con el error de un campo. Si no hay error queda oculta: igual se
     * imprime porque auth.js la usa para mostrar errores sin recargar la pagina.
     *
     *   <?= error_campo($errors ?? [], 'email') ?>
     */
    function error_campo(array $errores, string $campo, string $id = ''): string
    {
        $atributoId = $id !== '' ? ' id="' . esc($id, 'attr') . '"' : '';

        if (! isset($errores[$campo])) {
            return '<div class="invalid-feedback"' . $atributoId . ' style="display:none"></div>';
        }

        return '<div class="invalid-feedback"' . $atributoId . '><span>&#9888;</span> ' . esc($errores[$campo]) . '</div>';
    }
}

if (! function_exists('clase_error')) {
    /** Devuelve ' is-invalid' si el campo tiene error (para pintar el borde en rojo). */
    function clase_error(array $errores, string $campo): string
    {
        return isset($errores[$campo]) ? ' is-invalid' : '';
    }
}
