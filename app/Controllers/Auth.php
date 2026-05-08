<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\Controller;

/**
 * Auth Controller
 * app/Controllers/Auth.php
 *
 * Maneja: registro, login, logout, recuperación y reset de contraseña.
 */
class Auth extends BaseController
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    // ════════════════════════════════════════════════════════════
    // REGISTRO
    // ════════════════════════════════════════════════════════════

    public function register(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        // Si ya está logueado, redirigir al dashboard
        if (session()->get('user_id')) {
            return redirect()->to(base_url('dashboard'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->processRegister();
        }

        return view('auth/register');
    }

    private function processRegister(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $rules = [
            'nombre'           => 'required|min_length[2]|max_length[100]',
            'email'            => 'required|valid_email|max_length[150]|is_unique[usuarios.email]',
            'password'         => 'required|min_length[8]|regex_match[/^(?=.*[A-Z])(?=.*[0-9]).+$/]',
            'password_confirm' => 'required|matches[password]',
            'terms'            => 'required',
        ];

        $messages = [
            'nombre' => [
                'required'   => 'El nombre es obligatorio.',
                'min_length' => 'El nombre debe tener al menos 2 caracteres.',
            ],
            'email' => [
                'required'    => 'El correo electrónico es obligatorio.',
                'valid_email' => 'Ingresa un correo válido.',
                'is_unique'   => 'Este correo ya está registrado.',
            ],
            'password' => [
                'required'    => 'La contraseña es obligatoria.',
                'min_length'  => 'La contraseña debe tener al menos 8 caracteres.',
                'regex_match' => 'La contraseña debe contener al menos una mayúscula y un número.',
            ],
            'password_confirm' => [
                'required' => 'Confirma tu contraseña.',
                'matches'  => 'Las contraseñas no coinciden.',
            ],
            'terms' => [
                'required' => 'Debes aceptar los términos y condiciones.',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return view('auth/register', [
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $userId = $this->userModel->registrar([
            'nombre'   => $this->request->getPost('nombre'),
            'email'    => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
        ]);

        if (! $userId) {
            return view('auth/register', [
                'errors' => ['general' => 'Error al crear la cuenta. Intenta nuevamente.'],
            ]);
        }

        // Auto-login tras registro
        $user = $this->userModel->find($userId);
        $this->setSession($user);

        session()->setFlashdata('success', '¡Cuenta creada exitosamente! Bienvenido/a a AquaControl.');
        return redirect()->to(base_url('dashboard'));
    }

    // ════════════════════════════════════════════════════════════
    // LOGIN
    // ════════════════════════════════════════════════════════════

    public function login(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if (session()->get('user_id')) {
            return redirect()->to(base_url('dashboard'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->processLogin();
        }

        return view('auth/login');
    }

    private function processLogin(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
        ];

        $messages = [
            'email'    => ['required' => 'El correo es obligatorio.', 'valid_email' => 'Correo inválido.'],
            'password' => ['required' => 'La contraseña es obligatoria.'],
        ];

        if (! $this->validate($rules, $messages)) {
            return view('auth/login', [
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $user = $this->userModel->findByEmail($email);

        // Mensaje genérico para evitar enumeración de usuarios
        if (! $user || ! $this->userModel->verifyPassword($password, $user['password'])) {
            session()->setFlashdata('error', 'Correo o contraseña incorrectos.');
            return redirect()->back()->withInput();
        }

        $this->setSession($user);

        // Regenerar ID de sesión tras login (seguridad)
        session()->regenerate(true);

        session()->setFlashdata('success', '¡Bienvenido/a, ' . esc($user['nombre']) . '!');
        return redirect()->to(base_url('dashboard'));
    }

    // ════════════════════════════════════════════════════════════
    // LOGOUT
    // ════════════════════════════════════════════════════════════

    public function logout(): \CodeIgniter\HTTP\RedirectResponse
    {
        session()->destroy();
        session()->setFlashdata('info', 'Sesión cerrada correctamente.');
        return redirect()->to(base_url('auth/login'));
    }

    // ════════════════════════════════════════════════════════════
    // RECUPERACIÓN DE CONTRASEÑA
    // ════════════════════════════════════════════════════════════

    public function recover(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if ($this->request->getMethod() === 'POST') {
            return $this->processRecover();
        }

        return view('auth/recover');
    }

    private function processRecover(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $rules    = ['email' => 'required|valid_email'];
        $messages = ['email' => ['required' => 'El correo es obligatorio.', 'valid_email' => 'Correo inválido.']];

        if (! $this->validate($rules, $messages)) {
            return view('auth/recover', ['errors' => $this->validator->getErrors()]);
        }

        $email = $this->request->getPost('email');
        $user  = $this->userModel->findByEmail($email);

        // Siempre mostrar el mismo mensaje (evitar enumeración)
        $msg = 'Si ese correo existe en nuestro sistema, recibirás un enlace en los próximos minutos.';

        if ($user) {
            $token = $this->userModel->generarTokenRecuperacion($user['id']);
            $this->enviarEmailRecuperacion($user['email'], $user['nombre'], $token);
        }

        session()->setFlashdata('success', $msg);
        return redirect()->to(base_url('auth/recover'));
    }

    // ════════════════════════════════════════════════════════════
    // RESET DE CONTRASEÑA
    // ════════════════════════════════════════════════════════════

    public function reset(?string $token = null): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $token = $token ?? $this->request->getGet('token');

        if (! $token) {
            session()->setFlashdata('error', 'Token inválido o expirado.');
            return redirect()->to(base_url('auth/recover'));
        }

        $user = $this->userModel->findByTokenValido($token);

        if (! $user) {
            session()->setFlashdata('error', 'El enlace expiró o ya fue usado. Solicita uno nuevo.');
            return redirect()->to(base_url('auth/recover'));
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->processReset($user, $token);
        }

        return view('auth/reset', ['token' => $token]);
    }

    private function processReset(array $user, string $token): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $rules = [
            'password'         => 'required|min_length[8]|regex_match[/^(?=.*[A-Z])(?=.*[0-9]).+$/]',
            'password_confirm' => 'required|matches[password]',
        ];

        $messages = [
            'password' => [
                'required'    => 'La contraseña es obligatoria.',
                'min_length'  => 'Mínimo 8 caracteres.',
                'regex_match' => 'Debe contener al menos una mayúscula y un número.',
            ],
            'password_confirm' => [
                'required' => 'Confirma la contraseña.',
                'matches'  => 'Las contraseñas no coinciden.',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return view('auth/reset', [
                'token'  => $token,
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $this->userModel->restablecerPassword(
            $user['id'],
            $this->request->getPost('password')
        );

        session()->setFlashdata('success', '¡Contraseña actualizada! Ya puedes iniciar sesión.');
        return redirect()->to(base_url('auth/login'));
    }

    // ════════════════════════════════════════════════════════════
    // HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════

    /**
     * Establece los datos de sesión del usuario autenticado
     */
    private function setSession(array $user): void
    {
        session()->set([
            'user_id'    => $user['id'],
            'user_email' => $user['email'],
            'user_nombre'=> $user['nombre'],
            'logged_in'  => true,
        ]);
    }

    /**
     * Envía el email de recuperación de contraseña.
     * Configura el email en app/Config/Email.php
     */
    private function enviarEmailRecuperacion(string $email, string $nombre, string $token): void
    {
        $resetUrl = base_url('auth/reset/' . $token);

        $emailService = \Config\Services::email();
        $emailService->setTo($email);
        $emailService->setFrom(env('email.fromEmail', 'noreply@aquacontrol.com'), 'AquaControl');
        $emailService->setSubject('Recuperación de contraseña – AquaControl');
        $emailService->setMessage("
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;background:#0D2B24;color:#E1F5EE;padding:40px;border-radius:16px;'>
                <h2 style='color:#5DCAA5;font-size:24px;margin-bottom:8px;'>🐟 AquaControl</h2>
                <p style='color:rgba(159,225,203,0.7);font-size:13px;margin-bottom:32px;'>Sistema Inteligente IoT</p>
                <h3 style='color:#fff;margin-bottom:12px;'>Hola, {$nombre}</h3>
                <p style='color:rgba(159,225,203,0.75);line-height:1.7;'>
                    Recibimos una solicitud para restablecer la contraseña de tu cuenta.
                    Haz clic en el botón a continuación para crear una nueva contraseña.
                    <br><br>
                    <strong style='color:#EF9F27;'>Este enlace expira en 1 hora.</strong>
                </p>
                <div style='text-align:center;margin:32px 0;'>
                    <a href='{$resetUrl}'
                       style='background:#1D9E75;color:#fff;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:600;font-size:15px;display:inline-block;'>
                        Restablecer contraseña
                    </a>
                </div>
                <p style='color:rgba(159,225,203,0.4);font-size:12px;line-height:1.6;'>
                    Si no solicitaste este cambio, ignora este correo. Tu contraseña seguirá siendo la misma.<br>
                    O copia este enlace en tu navegador:<br>
                    <a href='{$resetUrl}' style='color:#5DCAA5;word-break:break-all;'>{$resetUrl}</a>
                </p>
            </div>
        ");
        $emailService->setMailType('html');

        try {
            $emailService->send();
        } catch (\Exception $e) {
            log_message('error', 'Error enviando email recuperación: ' . $e->getMessage());
        }
    }
}
