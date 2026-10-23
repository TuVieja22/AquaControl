<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Tabla de los dispositivos (ESP32, sensores) de cada usuario.
class CreateDispositivosTable extends Migration
{
    // Crea la tabla dispositivos.
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // Usuario al que pertenece.
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            // Nombre, tipo y lugar donde esta el equipo.
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => false,
            ],
            'ubicacion' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => false,
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

        // Claves e indices; si se borra el usuario, se borran sus dispositivos.
        $this->forge->addKey('id', true);
        $this->forge->addKey('usuario_id');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('dispositivos', true);
    }

    // Deshace la migracion: borra la tabla.
    public function down(): void
    {
        $this->forge->dropTable('dispositivos', true);
    }
}
