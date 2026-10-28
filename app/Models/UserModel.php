<?php

namespace App\Models;

use CodeIgniter\Model;
use Config\Cuentas;

/**
 * Cuentas de usuario: datos, bloqueo por intentos fallidos, recuperacion de contrasena y
 * cuentas de prueba (las que todavia no tienen un AquaControl).
 */
class UserModel extends Model
{
    /** Regla de contrasena segura (se le antepone "required|" o "permit_empty|"). */
    public const PASSWORD_RULE = 'min_length[8]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).+$/]';

    // Mensajes de error de la contrasena.
    public const PASSWORD_MESSAGES = [
        'required'    => 'La contraseña es obligatoria.',
        'min_length'  => 'La contraseña debe tener al menos 8 caracteres.',
        'regex_match' => 'La contraseña debe incluir mayúsculas, minúsculas, números y caracteres especiales.',
    ];

    // Tabla y clave.
    protected $table            = 'usuarios';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    // Columnas que se pueden escribir.
    protected $allowedFields = [
        'nombre',
        'apellido',
        'email',
        'password',
        'token_recuperacion',
        'token_expira',
        'login_intentos',
        'bloqueado_hasta',
        'activo',
        'prueba_hasta',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Reglas que se revisan antes de guardar una cuenta.
    protected $validationRules = [
        // El "id" necesita su propia regla porque la del email lo usa ({id}) para no
        // contar al propio usuario como "correo repetido".
        'id'       => 'permit_empty|is_natural_no_zero',
        'nombre'   => 'required|min_length[2]|max_length[100]',
        'apellido' => 'required|min_length[2]|max_length[100]',
        'email'    => 'required|valid_email|max_length[150]|is_unique[usuarios.email,id,{id}]',
        'password' => 'required|' . self::PASSWORD_RULE,
    ];

    // Mensaje de cada error.
    protected $validationMessages = [
        'nombre' => [
            'required'   => 'El nombre es obligatorio.',
            'min_length' => 'El nombre debe tener al menos 2 caracteres.',
        ],
        'apellido' => [
            'required'   => 'El apellido es obligatorio.',
            'min_length' => 'El apellido debe tener al menos 2 caracteres.',
        ],
        'email' => [
            'required'    => 'El correo electrónico es obligatorio.',
            'valid_email' => 'Ingresa un correo electrónico válido.',
            'is_unique'   => 'Este correo ya está registrado.',
        ],
        'password' => self::PASSWORD_MESSAGES,
    ];

    // Antes de guardar: el email en minusculas y la contrasena convertida en hash.
    protected $beforeInsert = ['normalizarDatos'];
    protected $beforeUpdate = ['normalizarDatos'];

    // Pasa el correo a minusculas y convierte la contrasena en un hash (bcrypt).
    protected function normalizarDatos(array $data): array
    {
        if (isset($data['data']['email'])) {
            $data['data']['email'] = strtolower(trim((string) $data['data']['email']));
        }

        if (isset($data['data']['password'])) {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        return $data;
    }

    /** Nombre y apellido juntos, para mostrar (por ejemplo "Juan Perez"). */
    public static function nombreCompleto(array $user): string
    {
        return trim(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? ''));
    }

    // Cuenta activa con ese correo.
    public function findByEmail(string $email): ?array
    {
        return $this->where('email', strtolower(trim($email)))
            ->where('activo', 1)
            ->first();
    }

    // Compara la contrasena escrita con el hash guardado.
    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    // Crea el codigo para cambiar la contrasena; vale una hora.
    public function generarTokenRecuperacion(int $userId): string
    {
        $token = bin2hex(random_bytes(32));

        // Se envia el token original, pero en la base se guarda solo su hash.
        $this->update($userId, [
            'token_recuperacion' => hash('sha256', $token),
            'token_expira'       => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);

        return $token;
    }

    // Cuenta a la que pertenece un codigo que todavia no vencio.
    public function findByTokenValido(string $token): ?array
    {
        return $this->where('token_recuperacion', hash('sha256', $token))
            ->where('token_expira >', date('Y-m-d H:i:s'))
            ->where('activo', 1)
            ->first();
    }

    // Guarda la contrasena nueva, anula el codigo y quita los bloqueos.
    public function restablecerPassword(int $userId, string $nuevaPassword): bool
    {
        return $this->update($userId, [
            'password'           => $nuevaPassword,
            'token_recuperacion' => null,
            'token_expira'       => null,
            'login_intentos'     => 0,
            'bloqueado_hasta'    => null,
        ]);
    }

    /**
     * Alta de una cuenta nueva. Arranca como cuenta de prueba (ver Config\Cuentas): pasa a
     * permanente al conectar un AquaControl.
     */
    public function registrar(array $datos): int|false
    {
        $datos['activo'] = 1;
        $datos['prueba_hasta'] = date('Y-m-d H:i:s', time() + config(Cuentas::class)->diasDePrueba * 86400);

        return $this->insert($datos);
    }

    // La cuenta deja de ser de prueba (conecto un AquaControl o le aprobaron una compra).
    public function hacerPermanente(int $userId): void
    {
        $this->builder()->where('id', $userId)->update(['prueba_hasta' => null]);
    }

    /** Si es una cuenta de prueba que ya vencio. */
    public static function pruebaVencida(array $user): bool
    {
        return ! empty($user['prueba_hasta']) && strtotime((string) $user['prueba_hasta']) <= time();
    }

    /**
     * Borra las cuentas de prueba vencidas (con todos sus datos: configuracion, alertas...;
     * los pedidos quedan, sin cuenta). Devuelve cuantas borro.
     */
    public function borrarPruebasVencidas(): int
    {
        $this->builder()
            ->where('prueba_hasta IS NOT NULL')
            ->where('prueba_hasta <=', date('Y-m-d H:i:s'))
            ->delete();

        return $this->db->affectedRows();
    }

    // Suma un intento fallido a la cuenta y la bloquea si llego al limite.
    public function registrarIntentoFallido(int $userId, int $maxAttempts, int $lockMinutes): bool
    {
        $user = $this->find($userId);
        if (! $user) {
            return false;
        }

        // Si el bloqueo anterior ya vencio, la cuenta empieza de cero.
        $vencido = ! empty($user['bloqueado_hasta']) && strtotime((string) $user['bloqueado_hasta']) <= time();
        $attempts = ($vencido ? 0 : (int) ($user['login_intentos'] ?? 0)) + 1;

        return $this->update($userId, [
            'login_intentos'  => $attempts,
            'bloqueado_hasta' => $attempts >= $maxAttempts ? date('Y-m-d H:i:s', strtotime("+{$lockMinutes} minutes")) : null,
        ]);
    }

    // Pone en cero los intentos fallidos y quita el bloqueo.
    public function resetearSeguridadLogin(int $userId): bool
    {
        return $this->update($userId, [
            'login_intentos'  => 0,
            'bloqueado_hasta' => null,
        ]);
    }

    // Segundos que faltan para que termine el bloqueo (0 si no esta bloqueada).
    public function segundosBloqueoRestantes(array $user): int
    {
        return max(0, (strtotime((string) ($user['bloqueado_hasta'] ?? '')) ?: 0) - time());
    }
}
