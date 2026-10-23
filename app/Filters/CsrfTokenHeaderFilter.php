<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Expone el token CSRF vigente en un header de respuesta.
 *
 * Con `Security::$regenerate = true` el token cambia en cada POST valido; las
 * llamadas AJAX leen este header para actualizar el token que envian despues.
 */
class CsrfTokenHeaderFilter implements FilterInterface
{
    // Antes de la pagina no hace nada.
    public function before(RequestInterface $request, $arguments = null)
    {
    }

    // Despues de la pagina agrega el header con el token actual.
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader(csrf_header(), csrf_hash());

        return $response;
    }
}
