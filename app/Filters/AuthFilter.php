<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Protege las paginas que requieren iniciar sesion.
 *
 * En las rutas: 'auth' deja pasar a cualquier usuario logueado y 'auth:administrador'
 * solo a esos roles (se pueden poner varios: 'auth:administrador,tecnico').
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('logged_in')) {
            session()->setFlashdata('error', 'Debes iniciar sesion para acceder.');

            return redirect()->to(base_url('auth/login'));
        }

        $roles = array_filter((array) $arguments);
        if ($roles !== [] && ! in_array((string) session()->get('user_role'), $roles, true)) {
            session()->setFlashdata('error', 'No tienes permisos para acceder a esa seccion.');

            return redirect()->to(base_url('dashboard'));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
