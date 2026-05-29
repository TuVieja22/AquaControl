<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

class Usuarios extends BaseController
{
    private const ASSETS = [
        'extraCss' => ['css/dashboard.css', 'css/management.css'],
    ];

    private const PASSWORD_RULE = 'permit_empty|min_length[8]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).+$/]';

    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function index(): string
    {
        return view('users/index', array_merge(self::ASSETS, [
            'title'     => 'Usuarios',
            'users'     => $this->userModel->orderBy('created_at', 'DESC')->findAll(),
            'roleNames' => UserModel::ROLES,
        ]));
    }

    public function edit(int $id): string|RedirectResponse
    {
        $user = $this->userModel->find($id);
        if (! $user) {
            return redirect()
                ->to(base_url('usuarios'))
                ->with('error', 'El usuario solicitado no existe.');
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->updateUser($id, $user);
        }

        return $this->renderEdit($user);
    }

    private function updateUser(int $id, array $user): string|RedirectResponse
    {
        ['rules' => $rules, 'messages' => $messages] = $this->validationConfig($id);

        if (! $this->validate($rules, $messages)) {
            return $this->renderEdit($user, $this->validator->getErrors());
        }

        $nextRole = (string) $this->request->getPost('rol');
        if ($this->wouldRemoveLastAdmin($id, $nextRole)) {
            return $this->renderEdit($user, [
                'rol' => 'Debe quedar al menos un administrador activo.',
            ]);
        }

        $payload = [
            'id'     => $id,
            'nombre' => trim((string) $this->request->getPost('nombre')),
            'rol'    => $nextRole,
        ];

        $password = (string) $this->request->getPost('password');
        if ($password !== '') {
            $payload['password'] = $password;
        }

        if (! $this->userModel->update($id, $payload)) {
            return $this->renderEdit($user, $this->userModel->errors());
        }

        if ((int) session()->get('user_id') === $id) {
            session()->set([
                'user_nombre' => $payload['nombre'],
                'user_role'   => $payload['rol'],
            ]);
        }

        return redirect()
            ->to(base_url('usuarios'))
            ->with('success', 'Usuario actualizado correctamente.');
    }

    private function renderEdit(array $user, array $errors = []): string
    {
        return view('users/edit', array_merge(self::ASSETS, [
            'title'     => 'Editar usuario',
            'user'      => $user,
            'roleNames' => UserModel::ROLES,
            'errors'    => $errors,
            'form'      => [
                'nombre' => $this->request->getPost('nombre') ?? ($user['nombre'] ?? ''),
                'rol'    => $this->request->getPost('rol') ?? ($user['rol'] ?? UserModel::DEFAULT_ROLE),
            ],
        ]));
    }

    private function validationConfig(int $id): array
    {
        return [
            'rules' => [
                'nombre'           => 'required|min_length[2]|max_length[100]',
                'rol'              => 'required|in_list[administrador,usuario,tecnico]',
                'password'         => self::PASSWORD_RULE,
                'password_confirm' => 'matches[password]',
            ],
            'messages' => [
                'nombre' => [
                    'required'   => 'El nombre y apellido es obligatorio.',
                    'min_length' => 'El nombre debe tener al menos 2 caracteres.',
                ],
                'rol' => [
                    'required' => 'Selecciona un rol.',
                    'in_list'  => 'Selecciona un rol valido.',
                ],
                'password' => [
                    'min_length'  => 'La contrasena debe tener al menos 8 caracteres.',
                    'regex_match' => 'Debe incluir mayusculas, minusculas, numeros y caracteres especiales.',
                ],
                'password_confirm' => [
                    'matches' => 'Las contrasenas no coinciden.',
                ],
            ],
        ];
    }

    private function wouldRemoveLastAdmin(int $userId, string $nextRole): bool
    {
        if ($nextRole === UserModel::ROLE_ADMIN) {
            return false;
        }

        $user = $this->userModel->find($userId);
        if (! $user || ($user['rol'] ?? '') !== UserModel::ROLE_ADMIN) {
            return false;
        }

        $activeAdmins = $this->userModel
            ->where('rol', UserModel::ROLE_ADMIN)
            ->where('activo', 1)
            ->countAllResults();

        return $activeAdmins <= 1;
    }
}
