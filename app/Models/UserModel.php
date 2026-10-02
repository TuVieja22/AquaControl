<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Cuentas de usuario: datos, roles, bloqueo por intentos fallidos y recuperacion de contrasena.
 */
class UserModel extends Model
{
    public const ROLE_ADMIN = 'administrador';
    public const ROLE_USER = 'usuario';
    public const ROLE_TECHNICIAN = 'tecnico';
    public const DEFAULT_ROLE = self::ROLE_USER;

    public const ROLES = [
        self::ROLE_ADMIN      => 'Administrador',
        self::ROLE_USER       => 'Usuario comun',
        self::ROLE_TECHNICIAN => 'Tecnico',
    ];

    /** Regla de contrasena segura (se le antepone "required|" o "permit_empty|"). */
    public const PASSWORD_RULE = 'min_length[8]|regex_match[/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).+$/]';

    public const PASSWORD_MESSAGES = [
        'required'    => 'La contrasena es obligatoria.',
        'min_length'  => 'La contrasena debe tener al menos 8 caracteres.',
        'regex_match' => 'La contrasena debe incluir mayusculas, minusculas, numeros y caracteres especiales.',
    ];

    protected $table            = 'usuarios';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields = [
        'nombre',
        'email',
        'password',
        'rol',
        'token_recuperacion',
        'token_expira',
        'login_intentos',
        'bloqueado_hasta',
        'activo',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'nombre'   => 'required|min_length[2]|max_length[100]',
        'email'    => 'required|valid_email|max_length[150]|is_unique[usuarios.email,id,{id}]',
        'password' => 'required|' . self::PASSWORD_RULE,
        'rol'      => 'permit_empty|in_list[administrador,usuario,tecnico]',
    ];

    protected $validationMessages = [
        'nombre' => [
            'required'   => 'El nombre es obligatorio.',
            'min_length' => 'El nombre debe tener al menos 2 caracteres.',
        ],
        'email' => [
            'required'    => 'El correo electronico es obligatorio.',
            'valid_email' => 'Ingresa un correo electronico valido.',
            'is_unique'   => 'Este correo ya esta registrado.',
        ],
        'password' => self::PASSWORD_MESSAGES,
        'rol'      => [
            'in_list' => 'Selecciona un rol valido.',
        ],
    ];

    // Antes de guardar: el email en minusculas y la contrasena convertida en hash.
    protected $beforeInsert = ['normalizarDatos'];
    protected $beforeUpdate = ['normalizarDatos'];

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

    public function findByEmail(string $email): ?array
    {
        return $this->where('email', strtolower(trim($email)))
            ->where('activo', 1)
            ->first();
    }

    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

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

    public function findByTokenValido(string $token): ?array
    {
        return $this->where('token_recuperacion', hash('sha256', $token))
            ->where('token_expira >', date('Y-m-d H:i:s'))
            ->where('activo', 1)
            ->first();
    }

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

    /** Alta de una cuenta nueva: la primera que se registra queda como administrador. */
    public function registrar(array $datos): int|false
    {
        $datos['activo'] = 1;
        $datos['rol'] ??= $this->countAll() === 0 ? self::ROLE_ADMIN : self::DEFAULT_ROLE;

        return $this->insert($datos);
    }

    public function registrarIntentoFallido(int $userId, int $maxAttempts, int $lockMinutes): bool
    {
        $user = $this->find($userId);
        if (! $user) {
            return false;
        }

        $vencido = ! empty($user['bloqueado_hasta']) && strtotime((string) $user['bloqueado_hasta']) <= time();
        $attempts = ($vencido ? 0 : (int) ($user['login_intentos'] ?? 0)) + 1;

        return $this->update($userId, [
            'login_intentos'  => $attempts,
            'bloqueado_hasta' => $attempts >= $maxAttempts ? date('Y-m-d H:i:s', strtotime("+{$lockMinutes} minutes")) : null,
        ]);
    }

    public function resetearSeguridadLogin(int $userId): bool
    {
        return $this->update($userId, [
            'login_intentos'  => 0,
            'bloqueado_hasta' => null,
        ]);
    }

    public function segundosBloqueoRestantes(array $user): int
    {
        return max(0, (strtotime((string) ($user['bloqueado_hasta'] ?? '')) ?: 0) - time());
    }

    public function roleLabel(string $role): string
    {
        return self::ROLES[$role] ?? self::ROLES[self::DEFAULT_ROLE];
    }

    /** Cuantos administradores activos hay (para no dejar el sistema sin ninguno). */
    public function administradoresActivos(): int
    {
        return $this->where('rol', self::ROLE_ADMIN)->where('activo', 1)->countAllResults();
    }
}
