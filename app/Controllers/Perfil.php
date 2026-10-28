<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * "Mi cuenta" del panel: cambiar nombre, apellido y email. Para cambiar el email se
 * pide la contrasena actual.
 */
class Perfil extends BaseController
{
    // Guarda los cambios de "Mi cuenta".
    public function actualizar(): RedirectResponse
    {
        // Busca la cuenta del usuario que inicio sesion.
        $userModel = new UserModel();
        $userId = $this->userId();
        $user = $userModel->find($userId);

        // Si la cuenta ya no existe, se cierra la sesion.
        if (! $user) {
            session()->destroy();

            return redirect()->to(base_url('auth/login'))->with('error', 'Tu sesión expiró. Inicia sesión nuevamente.');
        }

        // Nombre, apellido y correo obligatorios; el correo no puede ser el de otra cuenta.
        $valido = $this->validate([
            'nombre'   => 'required|min_length[2]|max_length[100]',
            'apellido' => 'required|min_length[2]|max_length[100]',
            'email'    => 'required|valid_email|max_length[150]|is_unique[usuarios.email,id,' . $userId . ']',
        ], [
            'nombre' => [
                'required'   => 'El nombre es obligatorio.',
                'min_length' => 'El nombre debe tener al menos 2 caracteres.',
                'max_length' => 'El nombre no puede superar los 100 caracteres.',
            ],
            'apellido' => [
                'required'   => 'El apellido es obligatorio.',
                'min_length' => 'El apellido debe tener al menos 2 caracteres.',
                'max_length' => 'El apellido no puede superar los 100 caracteres.',
            ],
            'email' => [
                'required'    => 'El correo electrónico es obligatorio.',
                'valid_email' => 'Ingresa un correo electrónico válido.',
                'max_length'  => 'El correo no puede superar los 150 caracteres.',
                'is_unique'   => 'Este correo ya está registrado en otra cuenta.',
            ],
        ]);

        // Con errores, vuelve al formulario y los muestra.
        if (! $valido) {
            return $this->volverConErrores($this->validator->getErrors(), 'Revisa los datos de tu cuenta.');
        }

        // Datos nuevos, y si cambia o no el correo.
        $nombre = trim((string) $this->request->getPost('nombre'));
        $apellido = trim((string) $this->request->getPost('apellido'));
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $passwordActual = (string) $this->request->getPost('current_password');
        $cambiaEmail = $email !== strtolower((string) $user['email']);

        // Para cambiar el correo hay que escribir bien la contrasena actual.
        if ($cambiaEmail && ! $userModel->verifyPassword($passwordActual, (string) $user['password'])) {
            return $this->volverConErrores([
                'current_password' => $passwordActual === ''
                    ? 'Ingresa tu contraseña actual para cambiar el email.'
                    : 'La contraseña actual no coincide.',
            ], 'No se pudo confirmar tu identidad.');
        }

        // Si no cambio nada, no se guarda.
        if (! $cambiaEmail && $nombre === (string) $user['nombre'] && $apellido === (string) $user['apellido']) {
            return redirect()->to(base_url('dashboard/profile') . '#perfil')->with('info', 'No había cambios para guardar.');
        }

        // Datos a guardar.
        $datos = ['id' => $userId, 'nombre' => $nombre, 'apellido' => $apellido, 'email' => $email];
        if ($cambiaEmail) {
            // Un email nuevo invalida enlaces de recuperacion pendientes y bloqueos por intentos.
            $datos += ['token_recuperacion' => null, 'token_expira' => null, 'login_intentos' => 0, 'bloqueado_hasta' => null];
        }

        // Guarda en la base.
        if (! $userModel->update($userId, $datos)) {
            return $this->volverConErrores($userModel->errors(), 'No se pudo actualizar tu cuenta.');
        }

        // Actualiza la sesion con los datos nuevos.
        session()->set(['user_nombre' => UserModel::nombreCompleto($datos), 'user_email' => $email]);
        session()->regenerate(true);

        return redirect()->to(base_url('dashboard/profile') . '#perfil')->with('success', $cambiaEmail
            ? 'Cuenta actualizada. Usa el nuevo correo en tu próximo inicio de sesión.'
            : 'Cuenta actualizada correctamente.');
    }

    // Vuelve a "Mi cuenta" con los errores y lo que se habia escrito.
    private function volverConErrores(array $errores, string $mensaje): RedirectResponse
    {
        return redirect()
            ->to(base_url('dashboard/profile') . '#perfil')
            ->with('profile_errors', $errores)
            ->with('profile_form', [
                'nombre'   => trim((string) $this->request->getPost('nombre')),
                'apellido' => trim((string) $this->request->getPost('apellido')),
                'email'    => strtolower(trim((string) $this->request->getPost('email'))),
            ])
            ->with('error', $mensaje);
    }
}
