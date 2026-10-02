<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 1) Crea las tablas de lecturas, alimentaciones y alertas si no existen. Antes solo
 *    venian en la copia SQL de la base, asi que una instalacion nueva con
 *    `php spark migrate` quedaba incompleta.
 * 2) Reemplaza los indices por usuario por indices (usuario, fecha): el panel siempre
 *    busca "las lecturas de este usuario ordenadas por fecha", y con un indice solo por
 *    usuario MySQL tenia que recorrer y ordenar todas las lecturas en cada consulta
 *    (el ESP32 manda unas 17.000 por dia).
 */
class TablasDeLecturasEIndices extends Migration
{
    public function up(): void
    {
        $lecturas = $this->db->prefixTable('lecturas_sensores');
        $alimentaciones = $this->db->prefixTable('alimentaciones');
        $alertas = $this->db->prefixTable('alertas');
        $usuarios = $this->db->prefixTable('usuarios');
        $dispositivos = $this->db->prefixTable('dispositivos');

        if (! $this->db->tableExists('lecturas_sensores')) {
            $this->db->query("CREATE TABLE {$lecturas} (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                usuario_id int(11) unsigned NOT NULL,
                dispositivo_id int(11) unsigned DEFAULT NULL,
                temperatura decimal(5,2) DEFAULT NULL COMMENT 'Celsius (DS18B20)',
                ph decimal(4,2) DEFAULT NULL COMMENT '0.00 a 14.00',
                turbidez decimal(6,2) DEFAULT NULL,
                nivel_agua tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=OK, 0=bajo',
                calefactor tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=apagado, 1=encendido',
                modo_vacaciones tinyint(1) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY idx_usuario_fecha (usuario_id, created_at),
                KEY idx_fecha (created_at),
                KEY idx_dispositivo_fecha (dispositivo_id, created_at),
                CONSTRAINT fk_lecturas_dispositivo FOREIGN KEY (dispositivo_id) REFERENCES {$dispositivos} (id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_lecturas_usuario FOREIGN KEY (usuario_id) REFERENCES {$usuarios} (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } elseif ($this->tieneIndice('lecturas_sensores', 'idx_usuario')) {
            $this->db->query("ALTER TABLE {$lecturas} ADD KEY idx_usuario_fecha (usuario_id, created_at), DROP KEY idx_usuario");
        }

        if (! $this->db->tableExists('alimentaciones')) {
            $this->db->query("CREATE TABLE {$alimentaciones} (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                usuario_id int(11) unsigned NOT NULL,
                cantidad_gramos decimal(5,2) NOT NULL DEFAULT 0.00,
                tipo enum('manual','automatica','vacaciones') NOT NULL DEFAULT 'automatica',
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY idx_usuario_fecha (usuario_id, created_at),
                CONSTRAINT fk_alim_usuario FOREIGN KEY (usuario_id) REFERENCES {$usuarios} (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } elseif ($this->tieneIndice('alimentaciones', 'idx_usuario')) {
            $this->db->query("ALTER TABLE {$alimentaciones} ADD KEY idx_usuario_fecha (usuario_id, created_at), DROP KEY idx_usuario");
        }

        if (! $this->db->tableExists('alertas')) {
            $this->db->query("CREATE TABLE {$alertas} (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                usuario_id int(11) unsigned NOT NULL,
                nivel tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=aviso, 2=alerta, 3=critico',
                tipo enum('temperatura','ph','nivel_agua','sistema') NOT NULL,
                mensaje varchar(255) NOT NULL,
                leida tinyint(1) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY (id),
                KEY idx_usuario_leida_fecha (usuario_id, leida, created_at),
                CONSTRAINT fk_alertas_usuario FOREIGN KEY (usuario_id) REFERENCES {$usuarios} (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } elseif ($this->tieneIndice('alertas', 'idx_usuario')) {
            $this->db->query("ALTER TABLE {$alertas} ADD KEY idx_usuario_leida_fecha (usuario_id, leida, created_at), DROP KEY idx_usuario, DROP KEY idx_leida");
        }
    }

    public function down(): void
    {
        // Solo se deshacen los indices: las tablas tienen datos y no se borran.
        if ($this->tieneIndice('lecturas_sensores', 'idx_usuario_fecha')) {
            $this->db->query('ALTER TABLE ' . $this->db->prefixTable('lecturas_sensores') . ' ADD KEY idx_usuario (usuario_id), DROP KEY idx_usuario_fecha');
        }
        if ($this->tieneIndice('alimentaciones', 'idx_usuario_fecha')) {
            $this->db->query('ALTER TABLE ' . $this->db->prefixTable('alimentaciones') . ' ADD KEY idx_usuario (usuario_id), DROP KEY idx_usuario_fecha');
        }
        if ($this->tieneIndice('alertas', 'idx_usuario_leida_fecha')) {
            $this->db->query('ALTER TABLE ' . $this->db->prefixTable('alertas') . ' ADD KEY idx_usuario (usuario_id), ADD KEY idx_leida (leida), DROP KEY idx_usuario_leida_fecha');
        }
    }

    private function tieneIndice(string $tabla, string $indice): bool
    {
        if (! $this->db->tableExists($tabla)) {
            return false;
        }

        return $this->db->query('SHOW INDEX FROM ' . $this->db->prefixTable($tabla) . ' WHERE Key_name = ?', [$indice])->getRowArray() !== null;
    }
}
