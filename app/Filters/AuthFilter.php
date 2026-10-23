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
    // Se ejecuta antes de la pagina: sin sesion, manda al login.
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('logged_in')) {
            session()->setFlashdata('error', 'Debes iniciar sesion para acceder.');

            return redirect()->to(base_url('auth/login'));
        }

        // Si la ruta pide un rol, el usuario tiene que tenerlo.
        $roles = array_filter((array) $arguments);
        if ($roles !== [] && ! in_array((string) session()->get('user_role'), $roles, true)) {
            session()->setFlashdata('error', 'No tienes permisos para acceder a esa seccion.');

            return redirect()->to(base_url('dashboard'));
        }

        // Todo bien: sigue hacia la pagina.
        return null;
    }

    // Despues de la pagina no hace nada.
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
