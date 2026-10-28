<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Protege las paginas que requieren iniciar sesion (en las rutas: filtro 'auth').
 *
 * Tambien controla las cuentas de prueba (las que todavia no tienen un AquaControl): si ya
 * vencio, cierra la sesion y borra la cuenta; si no, deja su vencimiento en la sesion para
 * el aviso del panel (components/aviso_prueba).
 */
class AuthFilter implements FilterInterface
{
    // Se ejecuta antes de la pagina: sin sesion, manda al login.
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('logged_in')) {
            session()->setFlashdata('error', 'Debes iniciar sesión para acceder.');

            return redirect()->to(base_url('auth/login'));
        }

        // La cuenta tiene que seguir existiendo (la pudo borrar la limpieza de cuentas de prueba).
        $users = new UserModel();
        $user = $users->find((int) session()->get('user_id'));
        if ($user === null) {
            return $this->cerrarSesion('La cuenta ya no existe.');
        }

        // Cuenta de prueba vencida: se borra y se cierra la sesion.
        if (UserModel::pruebaVencida($user)) {
            $users->delete((int) $user['id']);

            return $this->cerrarSesion('Tu cuenta de prueba venció y se borró: las cuentas quedan solo si tienen un AquaControl conectado o comprado.');
        }
        session()->set('prueba_hasta', $user['prueba_hasta']);

        // Todo bien: sigue hacia la pagina.
        return null;
    }

    // Despues de la pagina no hace nada.
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    // Vacia la sesion (como "Salir") y vuelve al login con el motivo.
    private function cerrarSesion(string $motivo): ResponseInterface
    {
        session()->remove(['user_id', 'user_email', 'user_nombre', 'logged_in', 'prueba_hasta']);
        session()->regenerate(true);

        return redirect()->to(base_url('auth/login'))->with('error', $motivo);
    }
}
