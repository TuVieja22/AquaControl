<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cola de comandos para los dispositivos IoT (p. ej. activar el servo del alimentador).
 * El ESP32 no es alcanzable desde el servidor, asi que consulta esta cola periodicamente.
 */
class CreateComandosDispositivoTable extends Migration
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
            'dispositivo_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'NULL = lo toma el primer actuador del usuario que consulte',
            ],
            'accion' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'parametros' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'origen' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'manual',
                'comment'    => 'manual | programado',
            ],
            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'pendiente',
                'comment'    => 'pendiente | enviado | ejecutado | fallido | expirado',
            ],
            'programado_para' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'expira_at' => [
                'type' => 'DATETIME',
            ],
            'enviado_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'finalizado_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'mensaje' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
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
        $this->forge->addKey(['usuario_id', 'estado']);
        // Evita crear dos veces el mismo horario programado (NULL no colisiona: los manuales quedan libres).
        $this->forge->addUniqueKey(['usuario_id', 'accion', 'programado_para'], 'comandos_programado_unique');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('dispositivo_id', 'dispositivos', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('comandos_dispositivo', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('comandos_dispositivo', true);
    }
}
