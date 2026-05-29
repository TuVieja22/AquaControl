<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('logged_in')) {
            session()->setFlashdata('error', 'Debes iniciar sesion para acceder.');

            return redirect()->to(base_url('auth/login'));
        }

        $allowedRoles = array_filter((array) $arguments);
        $currentRole = (string) session()->get('user_role');

        if ($allowedRoles !== [] && ! in_array($currentRole, $allowedRoles, true)) {
            session()->setFlashdata('error', 'No tienes permisos para acceder a esa seccion.');

            return redirect()->to(base_url('dashboard'));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
