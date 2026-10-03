<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Deja la base igual sin importar como se instalo (con la copia .sql o con
 * `php spark migrate`):
 *
 * 1) Agrega a configuracion_pecera las columnas de los horarios del alimentador. Solo
 *    venian en la copia .sql, asi que en una instalacion hecha con migraciones no se
 *    podian guardar los horarios.
 * 2) Reemplaza los indices por usuario por indices (usuario, fecha): el panel siempre
 *    busca "las lecturas de este usuario ordenadas por fecha", y con un indice solo por
 *    usuario MySQL tenia que recorrer y ordenar todas las lecturas en cada consulta
 *    (el ESP32 manda unas 17.000 por dia).
 */
class TablasDeLecturasEIndices extends Migration
{
    /** tabla => [nombre del indice nuevo, columnas] */
    private const INDICES = [
        'lecturas_sensores' => ['idx_usuario_fecha', ['usuario_id', 'created_at']],
        'alimentaciones'    => ['idx_usuario_fecha', ['usuario_id', 'created_at']],
        'alertas'           => ['idx_usuario_leida_fecha', ['usuario_id', 'leida', 'created_at']],
    ];

    /** Indices de una sola columna que los nuevos dejan de mas. */
    private const INDICES_VIEJOS = [['usuario_id'], ['leida']];

    public function up(): void
    {
        $columnas = [
            'hora_alim_1'          => ['type' => 'TIME', 'null' => true, 'default' => '08:00:00', 'after' => 'temp_objetivo'],
            'hora_alim_2'          => ['type' => 'TIME', 'null' => true, 'default' => '18:00:00', 'after' => 'hora_alim_1'],
            'cantidad_alim_gramos' => ['type' => 'DECIMAL', 'constraint' => '4,2', 'null' => false, 'default' => 1.00, 'after' => 'hora_alim_2'],
        ];

        foreach ($columnas as $nombre => $definicion) {
            if (! $this->db->fieldExists($nombre, 'configuracion_pecera')) {
                $this->forge->addColumn('configuracion_pecera', [$nombre => $definicion]);
            }
        }

        foreach (self::INDICES as $tabla => [$nombre, $columnasDelIndice]) {
            $actuales = $this->indices($tabla);
            $cambios = [];

            if (! isset($actuales[$nombre])) {
                $cambios[] = "ADD KEY {$nombre} (" . implode(', ', $columnasDelIndice) . ')';
            }

            // El indice viejo puede llamarse idx_usuario (copia .sql) o usuario_id (migraciones).
            foreach ($actuales as $viejo => $columnasDelViejo) {
                if (in_array($columnasDelViejo, self::INDICES_VIEJOS, true)) {
                    $cambios[] = "DROP KEY {$viejo}";
                }
            }

            $this->alterar($tabla, $cambios);
        }
    }

    public function down(): void
    {
        // Solo se deshacen los indices: las columnas tienen datos y no se borran.
        foreach (self::INDICES as $tabla => [$nombre]) {
            if (! isset($this->indices($tabla)[$nombre])) {
                continue;
            }

            $cambios = ['ADD KEY idx_usuario (usuario_id)', "DROP KEY {$nombre}"];
            if ($tabla === 'alertas') {
                $cambios[] = 'ADD KEY idx_leida (leida)';
            }

            $this->alterar($tabla, $cambios);
        }
    }

    /** Indices de una tabla (sin la clave primaria): nombre => columnas en orden. */
    private function indices(string $tabla): array
    {
        $indices = [];
        $filas = $this->db->query('SHOW INDEX FROM ' . $this->db->prefixTable($tabla))->getResultArray();

        foreach ($filas as $fila) {
            if ($fila['Key_name'] !== 'PRIMARY') {
                $indices[$fila['Key_name']][(int) $fila['Seq_in_index'] - 1] = $fila['Column_name'];
            }
        }

        return array_map(static function (array $columnas): array {
            ksort($columnas);

            return array_values($columnas);
        }, $indices);
    }

    /** Aplica todos los cambios en una sola sentencia (agregar y quitar indices a la vez). */
    private function alterar(string $tabla, array $cambios): void
    {
        if ($cambios !== []) {
            $this->db->query('ALTER TABLE ' . $this->db->prefixTable($tabla) . ' ' . implode(', ', $cambios));
        }
    }
}
