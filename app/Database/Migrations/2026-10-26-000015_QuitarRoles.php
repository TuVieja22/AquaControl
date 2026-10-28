<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Quita el sistema de roles: la pagina es solo para usuarios (no hay administradores ni
 * tecnicos), asi que la columna usuarios.rol no se usaba. La agrego la migracion 000003.
 */
class QuitarRoles extends Migration
{
    // Borra la columna del rol.
    public function up(): void
    {
        if ($this->existeColumna('usuarios', 'rol')) {
            $this->db->query('ALTER TABLE usuarios DROP rol');
        }
    }

    // La vuelve a crear (todas las cuentas como usuario comun).
    public function down(): void
    {
        if (! $this->existeColumna('usuarios', 'rol')) {
            $this->db->query("ALTER TABLE usuarios ADD rol VARCHAR(30) NOT NULL DEFAULT 'usuario' AFTER password");
        }
    }

    /** Si la columna existe en la tabla (se pregunta cada vez: CodeIgniter guarda la lista en memoria). */
    private function existeColumna(string $tabla, string $columna): bool
    {
        return $this->db->query(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tabla, $columna]
        )->getRow() !== null;
    }
}
