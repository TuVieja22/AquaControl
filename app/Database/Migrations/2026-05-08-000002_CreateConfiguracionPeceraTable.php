<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Tabla con la configuracion de la pecera de cada usuario.
class CreateConfiguracionPeceraTable extends Migration
{
    // Crea la tabla configuracion_pecera.
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // Usuario al que pertenece (una sola configuracion por usuario).
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            // Rango ideal de temperatura.
            'temp_min' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 24.00,
            ],
            'temp_max' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 27.00,
            ],
            // Rango ideal de pH.
            'ph_min' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,2',
                'default'    => 6.80,
            ],
            'ph_max' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,2',
                'default'    => 7.60,
            ],
            // Temperatura que se quiere mantener.
            'temp_objetivo' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 25.50,
            ],
            // Modo vacaciones prendido o apagado.
            'modo_vacaciones' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        // Claves; si se borra el usuario, se borra su configuracion.
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('usuario_id');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('configuracion_pecera', true);
    }

    // Deshace la migracion: borra la tabla.
    public function down(): void
    {
        $this->forge->dropTable('configuracion_pecera', true);
    }
}
