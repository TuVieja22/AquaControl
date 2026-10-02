<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * "Mi cuenta" del panel: cambiar nombre y email. Para cambiar el email se pide la
 * contrasena actual.
 */
class Perfil extends BaseController
{
    public function actualizar(): RedirectResponse
    {
        $userModel = new UserModel();
        $userId = $this->userId();
        $user = $userModel->find($userId);

        if (! $user) {
            session()->destroy();

            return redirect()->to(base_url('auth/login'))->with('error', 'Tu sesion expiro. Inicia sesion nuevamente.');
        }

        $valido = $this->validate([
            'nombre' => 'required|min_length[2]|max_length[100]',
            'email'  => 'required|valid_email|max_length[150]|is_unique[usuarios.email,id,' . $userId . ']',
        ], [
            'nombre' => [
                'required'   => 'El nombre es obligatorio.',
                'min_length' => 'El nombre debe tener al menos 2 caracteres.',
                'max_length' => 'El nombre no puede superar los 100 caracteres.',
            ],
            'email' => [
                'required'    => 'El correo electronico es obligatorio.',
                'valid_email' => 'Ingresa un correo electronico valido.',
                'max_length'  => 'El correo no puede superar los 150 caracteres.',
                'is_unique'   => 'Este correo ya esta registrado en otra cuenta.',
            ],
        ]);

        if (! $valido) {
            return $this->volverConErrores($this->validator->getErrors(), 'Revisa los datos de tu cuenta.');
        }

        $nombre = trim((string) $this->request->getPost('nombre'));
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $passwordActual = (string) $this->request->getPost('current_password');
        $cambiaEmail = $email !== strtolower((string) $user['email']);

        if ($cambiaEmail && ! $userModel->verifyPassword($passwordActual, (string) $user['password'])) {
            return $this->volverConErrores([
                'current_password' => $passwordActual === ''
                    ? 'Ingresa tu contrasena actual para cambiar el email.'
                    : 'La contrasena actual no coincide.',
            ], 'No se pudo confirmar tu identidad.');
        }

        if (! $cambiaEmail && $nombre === (string) $user['nombre']) {
            return redirect()->to(base_url('dashboard/profile') . '#perfil')->with('info', 'No habia cambios para guardar.');
        }

        $datos = ['id' => $userId, 'nombre' => $nombre, 'email' => $email];
        if ($cambiaEmail) {
            // Un email nuevo invalida enlaces de recuperacion pendientes y bloqueos por intentos.
            $datos += ['token_recuperacion' => null, 'token_expira' => null, 'login_intentos' => 0, 'bloqueado_hasta' => null];
        }

        if (! $userModel->update($userId, $datos)) {
            return $this->volverConErrores($userModel->errors(), 'No se pudo actualizar tu cuenta.');
        }

        session()->set(['user_nombre' => $nombre, 'user_email' => $email]);
        session()->regenerate(true);

        return redirect()->to(base_url('dashboard/profile') . '#perfil')->with('success', $cambiaEmail
            ? 'Cuenta actualizada. Usa el nuevo correo en tu proximo inicio de sesion.'
            : 'Cuenta actualizada correctamente.');
    }

    private function volverConErrores(array $errores, string $mensaje): RedirectResponse
    {
        return redirect()
            ->to(base_url('dashboard/profile') . '#perfil')
            ->with('profile_errors', $errores)
            ->with('profile_form', [
                'nombre' => trim((string) $this->request->getPost('nombre')),
                'email'  => strtolower(trim((string) $this->request->getPost('email'))),
            ])
            ->with('error', $mensaje);
    }
}
