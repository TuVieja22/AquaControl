<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Configuracion de la pecera de cada usuario (rangos ideales, horarios del alimentador...).
 */
class ConfiguracionPeceraModel extends Model
{
    /** Valores que se usan mientras el usuario no guardo su propia configuracion. */
    public const VALORES_POR_DEFECTO = [
        'temp_min'             => 24.00,
        'temp_max'             => 27.00,
        'ph_min'               => 6.80,
        'ph_max'               => 7.60,
        'temp_objetivo'        => 25.50,
        'modo_vacaciones'      => 0,
        'hora_alim_1'          => '08:00:00',
        'hora_alim_2'          => '18:00:00',
        'cantidad_alim_gramos' => 1.00,
    ];

    protected $table            = 'configuracion_pecera';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'usuario_id',
        'temp_min',
        'temp_max',
        'ph_min',
        'ph_max',
        'temp_objetivo',
        'hora_alim_1',
        'hora_alim_2',
        'cantidad_alim_gramos',
        'modo_vacaciones',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function porUsuario(int $userId): ?array
    {
        return $this->where('usuario_id', $userId)->first();
    }

    /** Configuracion guardada del usuario, completada con los valores por defecto. */
    public function deUsuario(int $userId): array
    {
        return array_merge(self::VALORES_POR_DEFECTO, $this->porUsuario($userId) ?? []);
    }

    /** Guarda cambios en la configuracion del usuario (la crea si todavia no tenia). */
    public function guardar(int $userId, array $cambios): void
    {
        $actual = $this->porUsuario($userId);

        if ($actual !== null) {
            $this->update($actual['id'], $cambios);
        } else {
            $this->insert(['usuario_id' => $userId] + $cambios);
        }
    }
}
