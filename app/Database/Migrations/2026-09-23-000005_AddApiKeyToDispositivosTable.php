<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddApiKeyToDispositivosTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('dispositivos', [
            'api_key_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => true,
                'after'      => 'ubicacion',
            ],
            'api_key_prefijo' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'null'       => true,
                'after'      => 'api_key_hash',
            ],
            'api_key_generada_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'api_key_prefijo',
            ],
            'ultima_conexion' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'api_key_generada_at',
            ],
        ]);
        $this->db->query('ALTER TABLE ' . $this->db->prefixTable('dispositivos') . ' ADD UNIQUE KEY dispositivos_api_key_hash_unique (api_key_hash)');

        // lecturas_sensores se creo fuera de las migraciones; solo se extiende si existe.
        if ($this->db->tableExists('lecturas_sensores') && ! $this->db->fieldExists('dispositivo_id', 'lecturas_sensores')) {
            $this->forge->addColumn('lecturas_sensores', [
                'dispositivo_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'usuario_id',
                ],
            ]);

            $table = $this->db->prefixTable('lecturas_sensores');
            $this->db->query("ALTER TABLE {$table} ADD KEY idx_dispositivo_fecha (dispositivo_id, created_at)");
            $this->db->query(
                "ALTER TABLE {$table} ADD CONSTRAINT fk_lecturas_dispositivo FOREIGN KEY (dispositivo_id) "
                . 'REFERENCES ' . $this->db->prefixTable('dispositivos') . ' (id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('lecturas_sensores') && $this->db->fieldExists('dispositivo_id', 'lecturas_sensores')) {
            $table = $this->db->prefixTable('lecturas_sensores');
            $this->db->query("ALTER TABLE {$table} DROP FOREIGN KEY fk_lecturas_dispositivo");
            $this->db->query("ALTER TABLE {$table} DROP KEY idx_dispositivo_fecha");
            $this->forge->dropColumn('lecturas_sensores', 'dispositivo_id');
        }

        $this->db->query('ALTER TABLE ' . $this->db->prefixTable('dispositivos') . ' DROP KEY dispositivos_api_key_hash_unique');
        $this->forge->dropColumn('dispositivos', ['api_key_hash', 'api_key_prefijo', 'api_key_generada_at', 'ultima_conexion']);
    }
}
