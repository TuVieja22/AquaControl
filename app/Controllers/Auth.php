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
    // Cuantos intentos fallidos se permiten y cuantos minutos dura el bloqueo.
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCK_MINUTES = 15;

    // Modelo de usuarios: lo usan todos los metodos de este controlador.
    private UserModel $userModel;

    // Al crear el controlador se prepara el modelo de usuarios.
    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    // Registro: muestra el formulario o, si llega completo, crea la cuenta.
    public function register(): string|RedirectResponse
    {
        // Si ya inicio sesion, va directo al panel.
        if (session()->get('user_id')) {
            return redirect()->to(base_url('dashboard'));
        }

        // Si todavia no se envio el formulario, solo se muestra la pagina.
        if ($this->request->getMethod() !== 'POST') {
            return view('auth/register', ['title' => 'Crear cuenta']);
        }

        // Reglas que tienen que cumplir los datos, con el mensaje de cada error.
        $valido = $this->validate([
            'nombre'           => 'required|min_length[2]|max_length[100]',
            'apellido'         => 'required|min_length[2]|max_length[100]',
            'email'            => 'required|valid_email|max_length[150]|is_unique[usuarios.email]',
            'password'         => 'required|' . UserModel::PASSWORD_RULE,
            'password_confirm' => 'required|matches[password]',
            'terms'            => 'required',
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
                'valid_email' => 'Ingresa un correo válido.',
                'is_unique'   => 'Este correo ya está registrado.',
            ],
            'password'         => UserModel::PASSWORD_MESSAGES,
            'password_confirm' => [
                'required' => 'Confirma tu contraseña.',
                'matches'  => 'Las contraseñas no coinciden.',
            ],
            'terms' => ['required' => 'Debes aceptar los términos y condiciones.'],
        ]);

        // Con errores, vuelve al formulario y los muestra.
        if (! $valido) {
            return view('auth/register', ['title' => 'Crear cuenta', 'errors' => $this->validator->getErrors()]);
        }

        // Guarda la cuenta nueva (el modelo encripta la contrasena).
        $creado = $this->userModel->registrar([
            'nombre'   => trim((string) $this->request->getPost('nombre')),
            'apellido' => trim((string) $this->request->getPost('apellido')),
            'email'    => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
        ]);

        // Si la base no pudo guardarla, avisa.
        if (! $creado) {
            return view('auth/register', [
                'title'  => 'Crear cuenta',
                'errors' => ['general' => 'Error al crear la cuenta. Intenta nuevamente.'],
            ]);
        }

        // Cuenta creada: lleva al login con un mensaje.
        return redirect()
            ->to(base_url('auth/login'))
            ->with('success', 'Cuenta creada exitosamente. Por seguridad, inicia sesión con tus credenciales.');
    }

    // Inicio de sesion: muestra el formulario o revisa el correo y la contrasena.
    public function login(): string|RedirectResponse
    {
        // Si ya inicio sesion, va directo al panel.
        if (session()->get('user_id')) {
            return redirect()->to(base_url('dashboard'));
        }

        // Si todavia no se envio el formulario, solo se muestra la pagina.
        if ($this->request->getMethod() !== 'POST') {
            return view('auth/login', ['title' => 'Iniciar sesión']);
        }

        // Los dos campos son obligatorios.
        $valido = $this->validate([
            'email'    => 'required|valid_email',
            'password' => 'required',
        ], [
            'email'    => ['required' => 'El correo es obligatorio.', 'valid_email' => 'Correo inválido.'],
            'password' => ['required' => 'La contraseña es obligatoria.'],
        ]);

        // Con errores, vuelve al formulario y los muestra.
        if (! $valido) {
            return view('auth/login', ['title' => 'Iniciar sesión', 'errors' => $this->validator->getErrors()]);
        }

        // Lo que escribio el usuario (el correo, en minusculas y sin espacios).
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');

        // Primer freno: demasiados intentos desde este correo y esta conexion.
        if ($this->demasiadosIntentos($email)) {
            return $this->volverAlLogin('Demasiados intentos. Espera unos minutos antes de volver a probar.');
        }

        // Busca la cuenta por su correo.
        $user = $this->userModel->findByEmail($email);

        // Segundo freno: la cuenta esta bloqueada por intentos fallidos.
        if ($user && ($segundos = $this->userModel->segundosBloqueoRestantes($user)) > 0) {
            $minutos = max(1, (int) ceil($segundos / 60));

            return $this->volverAlLogin("La cuenta está bloqueada temporalmente por intentos fallidos. Intenta nuevamente en {$minutos} minuto" . ($minutos === 1 ? '' : 's') . '.');
        }

        // Correo o contrasena incorrectos: se anota el intento y se avisa.
        if (! $user || ! $this->userModel->verifyPassword($password, $user['password'])) {
            $this->anotarIntentoFallido($email, $user);

            return $this->volverAlLogin('Correo o contraseña incorrectos. Si el problema continúa, usa la recuperación de contraseña.');
        }

        // Datos correctos: se borran los intentos fallidos.
        cache()->delete($this->claveIntentos($email));
        $this->userModel->resetearSeguridadLogin((int) $user['id']);

        // Guarda en la sesion quien es el usuario.
        session()->set([
            'user_id'     => $user['id'],
            'user_email'  => $user['email'],
            'user_nombre' => UserModel::nombreCompleto($user),
            'logged_in'   => true,
        ]);
        // Cambia el identificador de la sesion por seguridad.
        session()->regenerate(true);

        // Entra al panel con un saludo.
        return redirect()->to(base_url('dashboard'))->with('success', '¡Bienvenido/a, ' . $user['nombre'] . '!');
    }

    // Cierre de sesion.
    public function logout(): RedirectResponse
    {
        // Se vacia la sesion (en vez de destruirla) para que llegue el mensaje de despedida.
        session()->remove(['user_id', 'user_email', 'user_nombre', 'logged_in', 'prueba_hasta']);
        session()->regenerate(true);

        return redirect()->to(base_url('auth/login'))->with('info', 'Sesión cerrada correctamente.');
    }

    // Recuperar contrasena: pide el correo y manda un enlace para cambiarla.
    public function recover(): string|RedirectResponse
    {
        // Si todavia no se envio el formulario, solo se muestra la pagina.
        if ($this->request->getMethod() !== 'POST') {
            return view('auth/recover', ['title' => 'Recuperar contraseña']);
        }

        // El correo tiene que estar bien escrito.
        $valido = $this->validate(
            ['email' => 'required|valid_email'],
            ['email' => ['required' => 'El correo es obligatorio.', 'valid_email' => 'Correo inválido.']]
        );

        // Con errores, vuelve al formulario y los muestra.
        if (! $valido) {
            return view('auth/recover', ['title' => 'Recuperar contraseña', 'errors' => $this->validator->getErrors()]);
        }

        // Si el correo existe, se le manda el enlace con un codigo de un solo uso.
        $user = $this->userModel->findByEmail((string) $this->request->getPost('email'));
        if ($user) {
            $this->enviarEmailRecuperacion($user, $this->userModel->generarTokenRecuperacion((int) $user['id']));
        }

        // Siempre el mismo mensaje, para no revelar que emails estan registrados.
        return redirect()
            ->to(base_url('auth/recover'))
            ->with('success', 'Si ese correo existe en nuestro sistema, recibirás un enlace en los próximos minutos.');
    }

    // Nueva contrasena: se llega desde el enlace del correo.
    public function reset(?string $token = null): string|RedirectResponse
    {
        // El codigo puede venir en la direccion o en el formulario.
        $token ??= $this->request->getPost('token') ?? $this->request->getGet('token');

        // Sin codigo no se puede seguir.
        if (! $token) {
            return redirect()->to(base_url('auth/recover'))->with('error', 'Token inválido o expirado.');
        }

        // Busca de quien es el codigo (si todavia no vencio).
        $user = $this->userModel->findByTokenValido($token);
        if (! $user) {
            return redirect()->to(base_url('auth/recover'))->with('error', 'El enlace expiró o ya fue usado. Solicita uno nuevo.');
        }

        // Muestra el formulario para escribir la contrasena nueva.
        if ($this->request->getMethod() !== 'POST') {
            return view('auth/reset', ['title' => 'Nueva contraseña', 'token' => $token]);
        }

        // La contrasena nueva tiene que ser segura y estar repetida igual.
        $valido = $this->validate([
            'password'         => 'required|' . UserModel::PASSWORD_RULE,
            'password_confirm' => 'required|matches[password]',
        ], [
            'password'         => UserModel::PASSWORD_MESSAGES,
            'password_confirm' => ['required' => 'Confirma la contraseña.', 'matches' => 'Las contraseñas no coinciden.'],
        ]);

        // Con errores, vuelve al formulario y los muestra.
        if (! $valido) {
            return view('auth/reset', ['title' => 'Nueva contraseña', 'token' => $token, 'errors' => $this->validator->getErrors()]);
        }

        // Guarda la contrasena nueva y anula el codigo.
        $this->userModel->restablecerPassword((int) $user['id'], (string) $this->request->getPost('password'));

        return redirect()->to(base_url('auth/login'))->with('success', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }

    // Vuelve al login con un mensaje de error, conservando lo escrito.
    private function volverAlLogin(string $mensaje): RedirectResponse
    {
        return redirect()->back()->withInput()->with('error', $mensaje);
    }

    // Dice si este correo y esta conexion estan frenados por demasiados intentos.
    private function demasiadosIntentos(string $email): bool
    {
        $estado = cache($this->claveIntentos($email));

        return is_array($estado) && (int) ($estado['locked_until'] ?? 0) > time();
    }

    // Suma un intento fallido y bloquea si se llego al limite.
    private function anotarIntentoFallido(string $email, ?array $user): void
    {
        $clave = $this->claveIntentos($email);
        $estado = cache($clave);

        // Si el bloqueo anterior ya vencio, se empieza a contar de nuevo.
        if (! is_array($estado) || (int) ($estado['locked_until'] ?? PHP_INT_MAX) <= time()) {
            $estado = ['attempts' => 0];
        }

        // Suma el intento y, si llego al limite, bloquea por unos minutos.
        $estado['attempts'] = (int) ($estado['attempts'] ?? 0) + 1;
        if ($estado['attempts'] >= self::MAX_LOGIN_ATTEMPTS) {
            $estado['locked_until'] = time() + self::LOGIN_LOCK_MINUTES * 60;
        }

        // Guarda el contador.
        cache()->save($clave, $estado, self::LOGIN_LOCK_MINUTES * 60);

        // Si la cuenta existe, el intento tambien se anota en la base.
        if ($user) {
            $this->userModel->registrarIntentoFallido((int) $user['id'], self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCK_MINUTES);
        }
    }

    // Nombre con el que se guarda el contador: mezcla el correo y la direccion IP.
    private function claveIntentos(string $email): string
    {
        return 'login_attempts_' . hash('sha256', strtolower(trim($email)) . '|' . $this->request->getIPAddress());
    }

    // Arma y envia el correo con el enlace para cambiar la contrasena.
    private function enviarEmailRecuperacion(array $user, string $token): void
    {
        // Destinatario, remitente, asunto y contenido (la vista emails/recuperar_contrasena).
        $email = service('email');
        $email->setTo($user['email']);
        $email->setFrom(env('email.fromEmail', 'noreply@aquacontrol.com'), 'AquaControl');
        $email->setSubject('Recuperación de contraseña - AquaControl');
        $email->setMessage(view('emails/recuperar_contrasena', [
            'nombre' => $user['nombre'],
            'enlace' => base_url('auth/reset/' . $token),
        ]));
        $email->setMailType('html');

        // Si el envio falla, queda anotado en el registro de errores.
        try {
            $email->send();
        } catch (\Throwable $exception) {
            log_message('error', 'Error enviando email de recuperacion: ' . $exception->getMessage());
        }
    }
}
