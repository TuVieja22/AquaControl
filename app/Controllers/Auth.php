<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;
use Config\Services;

class Auth extends BaseController
{
    private const AUTH_ASSETS = [
        'extraCss' => ['css/auth.css'],
        'extraJs'  => ['js/auth.js'],
    ];

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCK_MINUTES = 15;
    private const PASSWORD_RULE = 'required|min_length[8]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).+$/]';

    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function register(): string|RedirectResponse
    {
        if ($redirect = $this->redirectIfAuthenticated()) {
            return $redirect;
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->processRegister();
        }

        return $this->renderAuth('register', ['title' => 'Crear cuenta']);
    }

    public function login(): string|RedirectResponse
    {
        if ($redirect = $this->redirectIfAuthenticated()) {
            return $redirect;
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->processLogin();
        }

        return $this->renderAuth('login', ['title' => 'Iniciar sesion']);
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();

        return redirect()
            ->to(base_url('auth/login'))
            ->with('info', 'Sesion cerrada correctamente.');
    }

    public function recover(): string|RedirectResponse
    {
        if ($this->request->getMethod() === 'POST') {
            return $this->processRecover();
        }

        return $this->renderAuth('recover', ['title' => 'Recuperar contrasena']);
    }

    public function reset(?string $token = null): string|RedirectResponse
    {
        $token = $token ?? $this->request->getPost('token') ?? $this->request->getGet('token');

        if (! $token) {
            return redirect()
                ->to(base_url('auth/recover'))
                ->with('error', 'Token invalido o expirado.');
        }

        $user = $this->userModel->findByTokenValido($token);
        if (! $user) {
            return redirect()
                ->to(base_url('auth/recover'))
                ->with('error', 'El enlace expiro o ya fue usado. Solicita uno nuevo.');
        }

        if ($this->request->getMethod() === 'POST') {
            return $this->processReset($user, $token);
        }

        return $this->renderAuth('reset', [
            'title' => 'Nueva contrasena',
            'token' => $token,
        ]);
    }

    private function processRegister(): string|RedirectResponse
    {
        ['rules' => $rules, 'messages' => $messages] = $this->registerValidation();

        if (! $this->validate($rules, $messages)) {
            return $this->renderValidationErrors('register', ['title' => 'Crear cuenta']);
        }

        $userId = $this->userModel->registrar([
            'nombre'   => $this->request->getPost('nombre'),
            'email'    => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
        ]);

        if (! $userId) {
            return $this->renderAuth('register', [
                'title'  => 'Crear cuenta',
                'errors' => ['general' => 'Error al crear la cuenta. Intenta nuevamente.'],
            ]);
        }

        return redirect()
            ->to(base_url('auth/login'))
            ->with('success', 'Cuenta creada exitosamente. Por seguridad, inicia sesion con tus credenciales.');
    }

    private function processLogin(): string|RedirectResponse
    {
        ['rules' => $rules, 'messages' => $messages] = $this->loginValidation();

        if (! $this->validate($rules, $messages)) {
            return $this->renderValidationErrors('login', ['title' => 'Iniciar sesion']);
        }

        $email = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');

        if ($this->isLoginThrottled($email)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Demasiados intentos. Espera unos minutos antes de volver a probar.');
        }

        $user = $this->userModel->findByEmail($email);

        if ($user && $this->userModel->estaBloqueado($user)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'La cuenta esta bloqueada temporalmente por intentos fallidos. Intenta nuevamente en ' . $this->formatLockTime($this->userModel->segundosBloqueoRestantes($user)) . '.');
        }

        if (! $user || ! $this->userModel->verifyPassword($password, $user['password'])) {
            $this->registerFailedLogin($email, $user);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Correo o contrasena incorrectos. Si el problema continua, usa la recuperacion de contrasena.');
        }

        $this->clearFailedLogin($email, (int) $user['id']);
        $this->setSession($user);
        session()->regenerate(true);

        return redirect()
            ->to(base_url('dashboard'))
            ->with('success', 'Bienvenido/a, ' . $user['nombre'] . '!');
    }

    private function processRecover(): string|RedirectResponse
    {
        ['rules' => $rules, 'messages' => $messages] = $this->recoverValidation();

        if (! $this->validate($rules, $messages)) {
            return $this->renderValidationErrors('recover', ['title' => 'Recuperar contrasena']);
        }

        $email = (string) $this->request->getPost('email');
        $user = $this->userModel->findByEmail($email);

        if ($user) {
            $token = $this->userModel->generarTokenRecuperacion((int) $user['id']);
            $this->sendRecoveryEmail($user['email'], $user['nombre'], $token);
        }

        return redirect()
            ->to(base_url('auth/recover'))
            ->with('success', 'Si ese correo existe en nuestro sistema, recibiras un enlace en los proximos minutos.');
    }

    private function processReset(array $user, string $token): string|RedirectResponse
    {
        ['rules' => $rules, 'messages' => $messages] = $this->resetValidation();

        if (! $this->validate($rules, $messages)) {
            return $this->renderValidationErrors('reset', [
                'title' => 'Nueva contrasena',
                'token' => $token,
            ]);
        }

        $this->userModel->restablecerPassword((int) $user['id'], (string) $this->request->getPost('password'));

        return redirect()
            ->to(base_url('auth/login'))
            ->with('success', 'Contrasena actualizada. Ya puedes iniciar sesion.');
    }

    private function renderAuth(string $view, array $data = []): string
    {
        return view('auth/' . $view, array_merge(self::AUTH_ASSETS, $data));
    }

    private function renderValidationErrors(string $view, array $data = []): string
    {
        return $this->renderAuth($view, array_merge($data, [
            'errors' => $this->validator->getErrors(),
        ]));
    }

    private function redirectIfAuthenticated(): ?RedirectResponse
    {
        if (! session()->get('user_id')) {
            return null;
        }

        return redirect()->to(base_url('dashboard'));
    }

    private function registerValidation(): array
    {
        return [
            'rules' => [
                'nombre'           => 'required|min_length[2]|max_length[100]',
                'email'            => 'required|valid_email|max_length[150]|is_unique[usuarios.email]',
                'password'         => self::PASSWORD_RULE,
                'password_confirm' => 'required|matches[password]',
                'terms'            => 'required',
            ],
            'messages' => [
                'nombre' => [
                    'required'   => 'El nombre es obligatorio.',
                    'min_length' => 'El nombre debe tener al menos 2 caracteres.',
                ],
                'email' => [
                    'required'    => 'El correo electronico es obligatorio.',
                    'valid_email' => 'Ingresa un correo valido.',
                    'is_unique'   => 'Este correo ya esta registrado.',
                ],
                'password' => $this->passwordMessages('La contrasena'),
                'password_confirm' => [
                    'required' => 'Confirma tu contrasena.',
                    'matches'  => 'Las contrasenas no coinciden.',
                ],
                'terms' => [
                    'required' => 'Debes aceptar los terminos y condiciones.',
                ],
            ],
        ];
    }

    private function loginValidation(): array
    {
        return [
            'rules' => [
                'email'    => 'required|valid_email',
                'password' => 'required',
            ],
            'messages' => [
                'email' => [
                    'required'    => 'El correo es obligatorio.',
                    'valid_email' => 'Correo invalido.',
                ],
                'password' => [
                    'required' => 'La contrasena es obligatoria.',
                ],
            ],
        ];
    }

    private function recoverValidation(): array
    {
        return [
            'rules' => [
                'email' => 'required|valid_email',
            ],
            'messages' => [
                'email' => [
                    'required'    => 'El correo es obligatorio.',
                    'valid_email' => 'Correo invalido.',
                ],
            ],
        ];
    }

    private function resetValidation(): array
    {
        return [
            'rules' => [
                'password'         => self::PASSWORD_RULE,
                'password_confirm' => 'required|matches[password]',
            ],
            'messages' => [
                'password' => $this->passwordMessages('La contrasena', 'Minimo 8 caracteres.'),
                'password_confirm' => [
                    'required' => 'Confirma la contrasena.',
                    'matches'  => 'Las contrasenas no coinciden.',
                ],
            ],
        ];
    }

    private function passwordMessages(string $label, string $lengthMessage = 'La contrasena debe tener al menos 8 caracteres.'): array
    {
        return [
            'required'    => $label . ' es obligatoria.',
            'min_length'  => $lengthMessage,
            'regex_match' => 'Debe contener mayusculas, minusculas, numeros y caracteres especiales.',
        ];
    }

    private function setSession(array $user): void
    {
        session()->set([
            'user_id'     => $user['id'],
            'user_email'  => $user['email'],
            'user_nombre' => $user['nombre'],
            'user_role'   => $user['rol'] ?? UserModel::DEFAULT_ROLE,
            'logged_in'   => true,
        ]);
    }

    private function isLoginThrottled(string $email): bool
    {
        $state = Services::cache()->get($this->loginThrottleKey($email));

        return is_array($state)
            && isset($state['locked_until'])
            && (int) $state['locked_until'] > time();
    }

    private function registerFailedLogin(string $email, ?array $user): void
    {
        $cache = Services::cache();
        $key = $this->loginThrottleKey($email);
        $state = $cache->get($key);

        if (is_array($state) && isset($state['locked_until']) && (int) $state['locked_until'] <= time()) {
            $state = null;
        }

        $attempts = is_array($state) ? (int) ($state['attempts'] ?? 0) : 0;
        $attempts++;

        $payload = ['attempts' => $attempts];
        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            $payload['locked_until'] = time() + (self::LOGIN_LOCK_MINUTES * 60);
        }

        $cache->save($key, $payload, self::LOGIN_LOCK_MINUTES * 60);

        if ($user) {
            $this->userModel->registrarIntentoFallido((int) $user['id'], self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCK_MINUTES);
        }
    }

    private function clearFailedLogin(string $email, int $userId): void
    {
        Services::cache()->delete($this->loginThrottleKey($email));
        $this->userModel->resetearSeguridadLogin($userId);
    }

    private function loginThrottleKey(string $email): string
    {
        $ip = $this->request->getIPAddress();

        return 'login_attempts_' . hash('sha256', strtolower(trim($email)) . '|' . $ip);
    }

    private function formatLockTime(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return $minutes . ' minuto' . ($minutes === 1 ? '' : 's');
    }

    private function sendRecoveryEmail(string $email, string $name, string $token): void
    {
        $resetUrl = base_url('auth/reset/' . $token);
        $message = <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;background:#0D2B24;color:#E1F5EE;padding:40px;border-radius:16px;">
    <h2 style="color:#5DCAA5;font-size:24px;margin-bottom:8px;">AquaControl</h2>
    <p style="color:rgba(159,225,203,0.7);font-size:13px;margin-bottom:32px;">Sistema Inteligente IoT</p>
    <h3 style="color:#fff;margin-bottom:12px;">Hola, {$name}</h3>
    <p style="color:rgba(159,225,203,0.75);line-height:1.7;">
        Recibimos una solicitud para restablecer la contrasena de tu cuenta.
        Haz clic en el boton a continuacion para crear una nueva contrasena.
        <br><br>
        <strong style="color:#EF9F27;">Este enlace expira en 1 hora.</strong>
    </p>
    <div style="text-align:center;margin:32px 0;">
        <a href="{$resetUrl}" style="background:#1D9E75;color:#fff;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:600;font-size:15px;display:inline-block;">
            Restablecer contrasena
        </a>
    </div>
    <p style="color:rgba(159,225,203,0.4);font-size:12px;line-height:1.6;">
        Si no solicitaste este cambio, ignora este correo. Tu contrasena seguira siendo la misma.<br>
        O copia este enlace en tu navegador:<br>
        <a href="{$resetUrl}" style="color:#5DCAA5;word-break:break-all;">{$resetUrl}</a>
    </p>
</div>
HTML;

        $emailService = Services::email();
        $emailService->setTo($email);
        $emailService->setFrom(env('email.fromEmail', 'noreply@aquacontrol.com'), 'AquaControl');
        $emailService->setSubject('Recuperacion de contrasena - AquaControl');
        $emailService->setMessage($message);
        $emailService->setMailType('html');

        try {
            $emailService->send();
        } catch (\Exception $exception) {
            log_message('error', 'Error enviando email de recuperacion: ' . $exception->getMessage());
        }
    }
}
