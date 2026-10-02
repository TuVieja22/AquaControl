<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Administracion de cuentas (solo administradores): nombre, rol y contrasena.
 */
class Usuarios extends BaseController
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function index(): string
    {
        return view('users/index', [
            'title'     => 'Usuarios',
            'users'     => $this->userModel->orderBy('created_at', 'DESC')->findAll(),
            'roleNames' => UserModel::ROLES,
        ]);
    }

    public function edit(int $id): string|RedirectResponse
    {
        $user = $this->userModel->find($id);
        if (! $user) {
            return redirect()->to(base_url('usuarios'))->with('error', 'El usuario solicitado no existe.');
        }

        if ($this->request->getMethod() !== 'POST') {
            return $this->formulario($user);
        }

        $valido = $this->validate([
            'nombre'           => 'required|min_length[2]|max_length[100]',
            'rol'              => 'required|in_list[administrador,usuario,tecnico]',
            'password'         => 'permit_empty|' . UserModel::PASSWORD_RULE,
            'password_confirm' => 'matches[password]',
        ], [
            'nombre' => [
                'required'   => 'El nombre y apellido es obligatorio.',
                'min_length' => 'El nombre debe tener al menos 2 caracteres.',
            ],
            'rol' => [
                'required' => 'Selecciona un rol.',
                'in_list'  => 'Selecciona un rol valido.',
            ],
            'password'         => UserModel::PASSWORD_MESSAGES,
            'password_confirm' => ['matches' => 'Las contrasenas no coinciden.'],
        ]);

        if (! $valido) {
            return $this->formulario($user, $this->validator->getErrors());
        }

        $rol = (string) $this->request->getPost('rol');
        if ($user['rol'] === UserModel::ROLE_ADMIN && $rol !== UserModel::ROLE_ADMIN && $this->userModel->administradoresActivos() <= 1) {
            return $this->formulario($user, ['rol' => 'Debe quedar al menos un administrador activo.']);
        }

        $datos = ['id' => $id, 'nombre' => trim((string) $this->request->getPost('nombre')), 'rol' => $rol];
        $password = (string) $this->request->getPost('password');
        if ($password !== '') {
            $datos['password'] = $password;
        }

        if (! $this->userModel->update($id, $datos)) {
            return $this->formulario($user, $this->userModel->errors());
        }

        // Si el administrador se edito a si mismo, se actualiza su sesion.
        if ($this->userId() === $id) {
            session()->set(['user_nombre' => $datos['nombre'], 'user_role' => $rol]);
        }

        return redirect()->to(base_url('usuarios'))->with('success', 'Usuario actualizado correctamente.');
    }

    private function formulario(array $user, array $errores = []): string
    {
        return view('users/edit', [
            'title'     => 'Editar usuario',
            'user'      => $user,
            'roleNames' => UserModel::ROLES,
            'errors'    => $errores,
            'form'      => [
                'nombre' => $this->request->getPost('nombre') ?? $user['nombre'],
                'rol'    => $this->request->getPost('rol') ?? $user['rol'],
            ],
        ]);
    }
}
