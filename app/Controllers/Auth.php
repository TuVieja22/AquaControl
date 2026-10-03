<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Registro, inicio y cierre de sesion, y recuperacion de contrasena.
 *
 * Contra ataques de fuerza bruta hay dos frenos: por email+IP en la cache y por
 * cuenta en la base (bloqueo temporal despues de varios intentos fallidos).
 */
class Auth extends BaseController
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCK_MINUTES = 15;

    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function register(): string|RedirectResponse
    {
        if (session()->get('user_id')) {
            return redirect()->to(base_url('dashboard'));
        }

        if ($this->request->getMethod() !== 'POST') {
            return view('auth/register', ['title' => 'Crear cuenta']);
        }

        $valido = $this->validate([
            'nombre'           => 'required|min_length[2]|max_length[100]',
            'email'            => 'required|valid_email|max_length[150]|is_unique[usuarios.email]',
            'password'         => 'required|' . UserModel::PASSWORD_RULE,
            'password_confirm' => 'required|matches[password]',
            'terms'            => 'required',
        ], [
            'nombre' => [
                'required'   => 'El nombre es obligatorio.',
                'min_length' => 'El nombre debe tener al menos 2 caracteres.',
            ],
            'email' => [
                'required'    => 'El correo electronico es obligatorio.',
                'valid_email' => 'Ingresa un correo valido.',
                'is_unique'   => 'Este correo ya esta registrado.',
            ],
            'password'         => UserModel::PASSWORD_MESSAGES,
            'password_confirm' => [
                'required' => 'Confirma tu contrasena.',
                'matches'  => 'Las contrasenas no coinciden.',
            ],
            'terms' => ['required' => 'Debes aceptar los terminos y condiciones.'],
        ]);

        if (! $valido) {
            return view('auth/register', ['title' => 'Crear cuenta', 'errors' => $this->validator->getErrors()]);
        }

        $creado = $this->userModel->registrar([
            'nombre'   => $this->request->getPost('nombre'),
            'email'    => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
        ]);

        if (! $creado) {
            return view('auth/register', [
                'title'  => 'Crear cuenta',
                'errors' => ['general' => 'Error al crear la cuenta. Intenta nuevamente.'],
            ]);
        }

        return redirect()
            ->to(base_url('auth/login'))
            ->with('success', 'Cuenta creada exitosamente. Por seguridad, inicia sesion con tus credenciales.');
    }

    public function login(): string|RedirectResponse
    {
        if (session()->get('user_id')) {
            return redirect()->to(base_url('dashboard'));
        }

        if ($this->request->getMethod() !== 'POST') {
            return view('auth/login', ['title' => 'Iniciar sesion']);
        }

        $valido = $this->validate([
            'email'    => 'required|valid_email',
            'password' => 'required',
        ], [
            'email'    => ['required' => 'El correo es obligatorio.', 'valid_email' => 'Correo invalido.'],
            'password' => ['required' => 'La contrasena es obligatoria.'],
        ]);

        if (! $valido) {
            return view('auth/login', ['title' => 'Iniciar sesion', 'errors' => $this->validator->getErrors()]);
        }

        $email = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');

        if ($this->demasiadosIntentos($email)) {
            return $this->volverAlLogin('Demasiados intentos. Espera unos minutos antes de volver a probar.');
        }

        $user = $this->userModel->findByEmail($email);

        if ($user && ($segundos = $this->userModel->segundosBloqueoRestantes($user)) > 0) {
            $minutos = max(1, (int) ceil($segundos / 60));

            return $this->volverAlLogin("La cuenta esta bloqueada temporalmente por intentos fallidos. Intenta nuevamente en {$minutos} minuto" . ($minutos === 1 ? '' : 's') . '.');
        }

        if (! $user || ! $this->userModel->verifyPassword($password, $user['password'])) {
            $this->anotarIntentoFallido($email, $user);

            return $this->volverAlLogin('Correo o contrasena incorrectos. Si el problema continua, usa la recuperacion de contrasena.');
        }

        cache()->delete($this->claveIntentos($email));
        $this->userModel->resetearSeguridadLogin((int) $user['id']);

        session()->set([
            'user_id'     => $user['id'],
            'user_email'  => $user['email'],
            'user_nombre' => $user['nombre'],
            'user_role'   => $user['rol'] ?? UserModel::DEFAULT_ROLE,
            'logged_in'   => true,
        ]);
        session()->regenerate(true);

        return redirect()->to(base_url('dashboard'))->with('success', 'Bienvenido/a, ' . $user['nombre'] . '!');
    }

    public function logout(): RedirectResponse
    {
        // Se vacia la sesion (en vez de destruirla) para que llegue el mensaje de despedida.
        session()->remove(['user_id', 'user_email', 'user_nombre', 'user_role', 'logged_in']);
        session()->regenerate(true);

        return redirect()->to(base_url('auth/login'))->with('info', 'Sesion cerrada correctamente.');
    }

    public function recover(): string|RedirectResponse
    {
        if ($this->request->getMethod() !== 'POST') {
            return view('auth/recover', ['title' => 'Recuperar contrasena']);
        }

        $valido = $this->validate(
            ['email' => 'required|valid_email'],
            ['email' => ['required' => 'El correo es obligatorio.', 'valid_email' => 'Correo invalido.']]
        );

        if (! $valido) {
            return view('auth/recover', ['title' => 'Recuperar contrasena', 'errors' => $this->validator->getErrors()]);
        }

        $user = $this->userModel->findByEmail((string) $this->request->getPost('email'));
        if ($user) {
            $this->enviarEmailRecuperacion($user, $this->userModel->generarTokenRecuperacion((int) $user['id']));
        }

        // Siempre el mismo mensaje, para no revelar que emails estan registrados.
        return redirect()
            ->to(base_url('auth/recover'))
            ->with('success', 'Si ese correo existe en nuestro sistema, recibiras un enlace en los proximos minutos.');
    }

    public function reset(?string $token = null): string|RedirectResponse
    {
        $token ??= $this->request->getPost('token') ?? $this->request->getGet('token');

        if (! $token) {
            return redirect()->to(base_url('auth/recover'))->with('error', 'Token invalido o expirado.');
        }

        $user = $this->userModel->findByTokenValido($token);
        if (! $user) {
            return redirect()->to(base_url('auth/recover'))->with('error', 'El enlace expiro o ya fue usado. Solicita uno nuevo.');
        }

        if ($this->request->getMethod() !== 'POST') {
            return view('auth/reset', ['title' => 'Nueva contrasena', 'token' => $token]);
        }

        $valido = $this->validate([
            'password'         => 'required|' . UserModel::PASSWORD_RULE,
            'password_confirm' => 'required|matches[password]',
        ], [
            'password'         => UserModel::PASSWORD_MESSAGES,
            'password_confirm' => ['required' => 'Confirma la contrasena.', 'matches' => 'Las contrasenas no coinciden.'],
        ]);

        if (! $valido) {
            return view('auth/reset', ['title' => 'Nueva contrasena', 'token' => $token, 'errors' => $this->validator->getErrors()]);
        }

        $this->userModel->restablecerPassword((int) $user['id'], (string) $this->request->getPost('password'));

        return redirect()->to(base_url('auth/login'))->with('success', 'Contrasena actualizada. Ya puedes iniciar sesion.');
    }

    private function volverAlLogin(string $mensaje): RedirectResponse
    {
        return redirect()->back()->withInput()->with('error', $mensaje);
    }

    private function demasiadosIntentos(string $email): bool
    {
        $estado = cache($this->claveIntentos($email));

        return is_array($estado) && (int) ($estado['locked_until'] ?? 0) > time();
    }

    private function anotarIntentoFallido(string $email, ?array $user): void
    {
        $clave = $this->claveIntentos($email);
        $estado = cache($clave);

        // Si el bloqueo anterior ya vencio, se empieza a contar de nuevo.
        if (! is_array($estado) || (int) ($estado['locked_until'] ?? PHP_INT_MAX) <= time()) {
            $estado = ['attempts' => 0];
        }

        $estado['attempts'] = (int) ($estado['attempts'] ?? 0) + 1;
        if ($estado['attempts'] >= self::MAX_LOGIN_ATTEMPTS) {
            $estado['locked_until'] = time() + self::LOGIN_LOCK_MINUTES * 60;
        }

        cache()->save($clave, $estado, self::LOGIN_LOCK_MINUTES * 60);

        if ($user) {
            $this->userModel->registrarIntentoFallido((int) $user['id'], self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCK_MINUTES);
        }
    }

    private function claveIntentos(string $email): string
    {
        return 'login_attempts_' . hash('sha256', strtolower(trim($email)) . '|' . $this->request->getIPAddress());
    }

    private function enviarEmailRecuperacion(array $user, string $token): void
    {
        $email = service('email');
        $email->setTo($user['email']);
        $email->setFrom(env('email.fromEmail', 'noreply@aquacontrol.com'), 'AquaControl');
        $email->setSubject('Recuperacion de contrasena - AquaControl');
        $email->setMessage(view('emails/recuperar_contrasena', [
            'nombre' => $user['nombre'],
            'enlace' => base_url('auth/reset/' . $token),
        ]));
        $email->setMailType('html');

        try {
            $email->send();
        } catch (\Throwable $exception) {
            log_message('error', 'Error enviando email de recuperacion: ' . $exception->getMessage());
        }
    }
}
