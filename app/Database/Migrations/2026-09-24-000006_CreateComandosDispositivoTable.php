<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cola de comandos para los dispositivos IoT (p. ej. activar el servo del alimentador).
 * El ESP32 no es alcanzable desde el servidor, asi que consulta esta cola periodicamente.
 */
class CreateComandosDispositivoTable extends Migration
{
    // Crea la tabla comandos_dispositivo.
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // Usuario y (opcional) dispositivo que tiene que ejecutar la orden.
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
            // Que hay que hacer y con que datos (por ejemplo, los gramos).
            'accion' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'parametros' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            // De donde salio la orden y en que estado esta.
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
            // Fechas: horario programado, vencimiento, envio y finalizacion.
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
            // Mensaje que devuelve el dispositivo (por ejemplo, el motivo de una falla).
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

        // Claves e indices.
        $this->forge->addKey('id', true);
        $this->forge->addKey(['usuario_id', 'estado']);
        // Evita crear dos veces el mismo horario programado (NULL no colisiona: los manuales quedan libres).
        $this->forge->addUniqueKey(['usuario_id', 'accion', 'programado_para'], 'comandos_programado_unique');
        // Si se borra el usuario se borran sus ordenes; si se borra el dispositivo, quedan sin dispositivo.
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('dispositivo_id', 'dispositivos', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('comandos_dispositivo', true);
    }

    // Deshace la migracion: borra la tabla.
    public function down(): void
    {
        $this->forge->dropTable('comandos_dispositivo', true);
    }
}
