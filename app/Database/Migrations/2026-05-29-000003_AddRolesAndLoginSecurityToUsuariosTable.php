<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Agrega a usuarios el bloqueo por intentos fallidos. (Antes tambien agregaba un rol; los
// roles se quitaron y la migracion 000015 borra esa columna en las bases que ya la tenian.
// El nombre de la clase queda igual: CodeIgniter lo usa para saber que ya se corrio.)
class AddRolesAndLoginSecurityToUsuariosTable extends Migration
{
    // Agrega las dos columnas nuevas.
    public function up(): void
    {
        $this->forge->addColumn('usuarios', [
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
    }

    // Deshace la migracion: quita las dos columnas.
    public function down(): void
    {
        foreach (['bloqueado_hasta', 'login_intentos'] as $column) {
            if ($this->db->fieldExists($column, 'usuarios')) {
                $this->forge->dropColumn('usuarios', $column);
            }
        }
    }
}
