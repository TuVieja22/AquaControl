<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Agrega a usuarios el rol y el bloqueo por intentos fallidos.
class AddRolesAndLoginSecurityToUsuariosTable extends Migration
{
    // Agrega las tres columnas nuevas.
    public function up(): void
    {
        $this->forge->addColumn('usuarios', [
            // Rol de la cuenta (por defecto, usuario comun).
            'rol' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'usuario',
                'after'      => 'password',
            ],
            // Intentos fallidos seguidos y hasta cuando queda bloqueada.
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

        // Si todavia no hay ningun administrador, pasa a serlo el primer usuario.
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

    // Deshace la migracion: quita las tres columnas.
    public function down(): void
    {
        foreach (['bloqueado_hasta', 'login_intentos', 'rol'] as $column) {
            if ($this->db->fieldExists($column, 'usuarios')) {
                $this->forge->dropColumn('usuarios', $column);
            }
        }
    }
}
