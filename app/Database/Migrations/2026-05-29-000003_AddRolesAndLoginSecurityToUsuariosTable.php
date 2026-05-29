<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRolesAndLoginSecurityToUsuariosTable extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('usuarios', [
            'rol' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'usuario',
                'after'      => 'password',
            ],
            'login_intentos' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'unsigned'   => true,
                'default'    => 0,
                'after'      => 'token_expira',
            ],
            'bloqueado_hasta' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'login_intentos',
            ],
        ]);

        $adminExists = $this->db->table('usuarios')
            ->where('rol', 'administrador')
            ->countAllResults() > 0;

        if (! $adminExists) {
            $firstUser = $this->db->table('usuarios')
                ->orderBy('id', 'ASC')
                ->get(1)
                ->getRowArray();

            if ($firstUser) {
                $this->db->table('usuarios')
                    ->where('id', $firstUser['id'])
                    ->update(['rol' => 'administrador']);
            }
        }
    }

    public function down(): void
    {
        foreach (['bloqueado_hasta', 'login_intentos', 'rol'] as $column) {
            if ($this->db->fieldExists($column, 'usuarios')) {
                $this->forge->dropColumn('usuarios', $column);
            }
        }
    }
}
