<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Configuracion de la pecera de cada usuario (rangos ideales, gramos por racion...).
 * Los horarios del alimentador estan en su propia tabla (HorarioAlimentacionModel),
 * pero se entregan junto con la configuracion, en la clave 'horarios'.
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
        'cantidad_alim_gramos' => 1.00,
        'horarios'             => ['08:00', '18:00'],
    ];

    // Tabla, clave y columnas que se pueden escribir.
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
        'cantidad_alim_gramos',
        'modo_vacaciones',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Configuracion guardada del usuario con sus horarios (null si todavia no tiene).
    public function porUsuario(int $userId): ?array
    {
        $config = $this->where('usuario_id', $userId)->first();

        if ($config !== null) {
            $config['horarios'] = (new HorarioAlimentacionModel())->deConfiguracion((int) $config['id']);
        }

        return $config;
    }

    /** Configuracion guardada del usuario, completada con los valores por defecto. */
    public function deUsuario(int $userId): array
    {
        return array_merge(self::VALORES_POR_DEFECTO, $this->porUsuario($userId) ?? []);
    }

    /**
     * Guarda cambios en la configuracion del usuario (la crea si todavia no tenia).
     * Si los cambios traen 'horarios' (lista de "HH:MM"), reemplazan a los guardados.
     */
    public function guardar(int $userId, array $cambios): void
    {
        // Los horarios van a su tabla; el resto, a configuracion_pecera.
        $horarios = $cambios['horarios'] ?? null;
        unset($cambios['horarios']);

        $actual = $this->where('usuario_id', $userId)->first();

        if ($actual === null) {
            // Una configuracion nueva arranca con los horarios por defecto (si no trae otros).
            $configuracionId = (int) $this->insert(['usuario_id' => $userId] + $cambios);
            $horarios ??= self::VALORES_POR_DEFECTO['horarios'];
        } else {
            $configuracionId = (int) $actual['id'];
            if ($cambios !== []) {
                $this->update($configuracionId, $cambios);
            }
        }

        if (is_array($horarios)) {
            (new HorarioAlimentacionModel())->reemplazar($configuracionId, $horarios);
        }
    }
}
