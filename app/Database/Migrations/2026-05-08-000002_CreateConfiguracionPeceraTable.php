<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateConfiguracionPeceraTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
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
            'temp_objetivo' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 25.50,
            ],
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

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('usuario_id');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('configuracion_pecera', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('configuracion_pecera', true);
    }
}
