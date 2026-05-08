<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * AuthFilter — protege rutas que requieren sesión activa
 * app/Filters/AuthFilter.php
 *
 * Registrar en app/Config/Filters.php:
 *   'auth' => \App\Filters\AuthFilter::class,
 *
 * Aplicar en app/Config/Routes.php:
 *   $routes->group('dashboard', ['filter' => 'auth'], function($routes) { ... });
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('logged_in')) {
            session()->setFlashdata('error', 'Debes iniciar sesión para acceder.');
            return redirect()->to(base_url('auth/login'));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No se necesita acción posterior
    }
}
