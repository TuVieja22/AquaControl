<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * UserModel
 * app/Models/UserModel.php
 */
class UserModel extends Model
{
    protected $table         = 'usuarios';
    protected $primaryKey    = 'id';
    protected $useAutoIncrement = true;
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'nombre',
        'email',
        'password',
        'token_recuperacion',
        'token_expira',
        'activo',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // ── VALIDATION RULES ───────────────────────────────────────
    protected $validationRules = [
        'nombre'   => 'required|min_length[2]|max_length[100]',
        'email'    => 'required|valid_email|max_length[150]|is_unique[usuarios.email,id,{id}]',
        'password' => 'required|min_length[8]',
    ];

    protected $validationMessages = [
        'nombre' => [
            'required'   => 'El nombre es obligatorio.',
            'min_length' => 'El nombre debe tener al menos 2 caracteres.',
        ],
        'email' => [
            'required'    => 'El correo electrónico es obligatorio.',
            'valid_email' => 'Ingresa un correo electrónico válido.',
            'is_unique'   => 'Este correo ya está registrado.',
        ],
        'password' => [
            'required'   => 'La contraseña es obligatoria.',
            'min_length' => 'La contraseña debe tener al menos 8 caracteres.',
        ],
    ];

    // ── CALLBACKS ───────────────────────────────────────────────
    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPasswordOnUpdate'];

    protected function hashPassword(array $data): array
    {
        if (isset($data['data']['password'])) {
            $data['data']['password'] = password_hash(
                $data['data']['password'],
                PASSWORD_BCRYPT,
                ['cost' => 12]
            );
        }
        return $data;
    }

    protected function hashPasswordOnUpdate(array $data): array
    {
        if (isset($data['data']['password'])) {
            $data['data']['password'] = password_hash(
                $data['data']['password'],
                PASSWORD_BCRYPT,
                ['cost' => 12]
            );
        }
        return $data;
    }

    // ── MÉTODOS PÚBLICOS ────────────────────────────────────────

    /**
     * Busca usuario por email
     */
    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)
                    ->where('activo', 1)
                    ->first();
    }

    /**
     * Verifica contraseña contra el hash almacenado
     */
    public function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /**
     * Genera y guarda token de recuperación (expira en 1 hora)
     */
    public function generarTokenRecuperacion(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->update($userId, [
            'token_recuperacion' => $token,
            'token_expira'       => $expira,
        ]);

        return $token;
    }

    /**
     * Busca usuario por token válido (no expirado)
     */
    public function findByTokenValido(string $token): ?array
    {
        return $this->where('token_recuperacion', $token)
                    ->where('token_expira >', date('Y-m-d H:i:s'))
                    ->where('activo', 1)
                    ->first();
    }

    /**
     * Restablece contraseña y elimina el token
     */
    public function restablecerPassword(int $userId, string $nuevaPassword): bool
    {
        return $this->update($userId, [
            'password'           => $nuevaPassword, // el callback lo hashea
            'token_recuperacion' => null,
            'token_expira'       => null,
        ]);
    }

    /**
     * Registra nuevo usuario con estado activo
     */
    public function registrar(array $datos): int|false
    {
        $datos['activo'] = 1;
        return $this->insert($datos);
    }
}
