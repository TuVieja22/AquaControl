<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Normaliza la base hasta la tercera forma normal (3FN). El diagrama de como queda
 * esta en base_de_datos.dbml (raiz del proyecto, se abre con https://dbdiagram.io).
 *
 * 1FN (un solo dato por campo y sin grupos repetidos):
 *   - usuarios: "nombre" guardaba nombre y apellido juntos; se separa en dos campos.
 *   - configuracion_pecera: hora_alim_1 y hora_alim_2 pasan a la tabla horarios_alimentacion.
 *   - comandos_dispositivo: parametros (texto JSON con los gramos) pasa a cantidad_gramos.
 * 2FN: todas las tablas tienen una clave de una sola columna (id): no hay nada que cambiar.
 * 3FN (ningun dato depende de otro que no sea la clave):
 *   - lecturas_sensores pierde usuario_id (sale de su dispositivo) y modo_vacaciones
 *     (es dato de configuracion_pecera). Las lecturas viejas sin dispositivo pasan al
 *     primer dispositivo de su usuario.
 *   - pedidos pierde producto_sku y producto_nombre (tabla productos) y total (se
 *     calcula: cantidad x precio_unitario, en la vista vista_pedidos).
 * Campos mal definidos:
 *   - lecturas_sensores.turbidez: no hay sensor de turbidez y siempre estaba vacia.
 *   - configuracion_pecera: pH con 2 decimales (antes 1) y valores por defecto iguales a
 *     los de la pagina (ConfiguracionPeceraModel::VALORES_POR_DEFECTO).
 *
 * Cada paso revisa primero si ya esta hecho: se puede correr sobre una base que ya se
 * cambio a mano con app/Database/normalizacion_3fn.sql (por ejemplo desde phpMyAdmin).
 */
class NormalizarHastaTerceraFormaNormal extends Migration
{
    /** Motor y juego de caracteres de las tablas nuevas (los mismos que las demas). */
    private const OPCIONES_TABLA = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

    // Aplica todos los pasos, en orden.
    public function up(): void
    {
        $this->separarNombreYApellido();
        $this->corregirConfiguracionPecera();
        $this->crearHorariosAlimentacion();
        $this->pasarParametrosACantidadGramos();
        $this->normalizarLecturas();
        $this->crearProductos();
        $this->crearVistaPedidos();
    }

    // Deshace los cambios de estructura (los datos vuelven a sus columnas de antes).
    public function down(): void
    {
        // La precision del pH y los valores por defecto nuevos se dejan: volver a 1
        // decimal podria redondear datos guardados.
        $this->db->query('DROP VIEW IF EXISTS vista_pedidos');
        $this->volverPedidosSinProductos();
        $this->volverLecturasConUsuario();
        $this->volverParametros();
        $this->volverHorariosEnColumnas();
        $this->volverNombreCompleto();
    }

    /* ── 1FN: nombre y apellido ─────────────────────────── */

    // Agrega "apellido" y reparte lo que habia en "nombre".
    private function separarNombreYApellido(): void
    {
        if ($this->existeColumna('usuarios', 'apellido')) {
            return;
        }

        $this->db->query("ALTER TABLE usuarios ADD apellido VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre");

        // El nombre es la primera palabra y el apellido todo lo que sigue.
        $this->db->query('UPDATE usuarios SET nombre = TRIM(nombre)');
        $this->db->query(
            "UPDATE usuarios
             SET apellido = TRIM(SUBSTRING(nombre, LOCATE(' ', nombre) + 1)),
                 nombre = SUBSTRING_INDEX(nombre, ' ', 1)
             WHERE LOCATE(' ', nombre) > 0"
        );

        // De ahora en mas el apellido es obligatorio (sin valor por defecto).
        $this->db->query('ALTER TABLE usuarios ALTER apellido DROP DEFAULT');
    }

    /* ── Campos mal definidos de configuracion_pecera ───── */

    // pH con 2 decimales y valores por defecto iguales a los de la pagina.
    private function corregirConfiguracionPecera(): void
    {
        $this->db->query(
            'ALTER TABLE configuracion_pecera
             MODIFY temp_min DECIMAL(4,1) NOT NULL DEFAULT 24.0,
             MODIFY temp_max DECIMAL(4,1) NOT NULL DEFAULT 27.0,
             MODIFY ph_min DECIMAL(4,2) NOT NULL DEFAULT 6.80,
             MODIFY ph_max DECIMAL(4,2) NOT NULL DEFAULT 7.60'
        );
    }

    /* ── 1FN: horarios del alimentador ──────────────────── */

    // Crea horarios_alimentacion (una fila por horario) y le pasa los dos que habia.
    private function crearHorariosAlimentacion(): void
    {
        if (! $this->existeTabla('horarios_alimentacion')) {
            $this->db->query(
                'CREATE TABLE horarios_alimentacion (
                    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    configuracion_id INT(11) UNSIGNED NOT NULL,
                    hora TIME NOT NULL COMMENT \'hora Argentina\',
                    created_at DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_configuracion_hora (configuracion_id, hora),
                    CONSTRAINT fk_horarios_configuracion FOREIGN KEY (configuracion_id)
                        REFERENCES configuracion_pecera (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ' . self::OPCIONES_TABLA
            );
        }

        // Copia cada horario que estaba en una columna y despues borra la columna.
        foreach (['hora_alim_1', 'hora_alim_2'] as $columna) {
            if (! $this->existeColumna('configuracion_pecera', $columna)) {
                continue;
            }

            $this->db->query(
                "INSERT IGNORE INTO horarios_alimentacion (configuracion_id, hora, created_at)
                 SELECT id, {$columna}, NOW() FROM configuracion_pecera WHERE {$columna} IS NOT NULL"
            );
            $this->db->query("ALTER TABLE configuracion_pecera DROP {$columna}");
        }
    }

    /* ── 1FN: los gramos de cada orden ──────────────────── */

    // Cambia el texto JSON {"gramos": 8} por la columna cantidad_gramos.
    private function pasarParametrosACantidadGramos(): void
    {
        if (! $this->existeColumna('comandos_dispositivo', 'cantidad_gramos')) {
            $this->db->query('ALTER TABLE comandos_dispositivo ADD cantidad_gramos DECIMAL(5,2) NULL DEFAULT NULL AFTER accion');
        }

        if (! $this->existeColumna('comandos_dispositivo', 'parametros')) {
            return;
        }

        $this->db->query(
            'UPDATE comandos_dispositivo
             SET cantidad_gramos = CAST(JSON_UNQUOTE(JSON_EXTRACT(parametros, \'$.gramos\')) AS DECIMAL(5,2))
             WHERE cantidad_gramos IS NULL AND JSON_VALID(parametros) AND JSON_EXTRACT(parametros, \'$.gramos\') IS NOT NULL'
        );
        $this->db->query('ALTER TABLE comandos_dispositivo DROP parametros');
    }

    /* ── 3FN y campos mal definidos de lecturas_sensores ── */

    // Quita usuario_id, modo_vacaciones y turbidez; el dispositivo pasa a ser obligatorio.
    private function normalizarLecturas(): void
    {
        if ($this->existeColumna('lecturas_sensores', 'usuario_id')) {
            // Usuarios con lecturas sin dispositivo y ningun dispositivo: se les crea uno.
            $this->db->query(
                "INSERT INTO dispositivos (usuario_id, nombre, tipo, ubicacion, created_at, updated_at)
                 SELECT DISTINCT l.usuario_id, 'Lecturas anteriores', 'otro', 'Sin especificar', NOW(), NOW()
                 FROM lecturas_sensores l
                 WHERE l.dispositivo_id IS NULL
                   AND NOT EXISTS (SELECT 1 FROM dispositivos d WHERE d.usuario_id = l.usuario_id)"
            );

            // Cada lectura sin dispositivo pasa al primer dispositivo de su usuario.
            $this->db->query(
                'UPDATE lecturas_sensores l
                 JOIN (SELECT usuario_id, MIN(id) AS dispositivo_id FROM dispositivos GROUP BY usuario_id) primero
                   ON primero.usuario_id = l.usuario_id
                 SET l.dispositivo_id = primero.dispositivo_id
                 WHERE l.dispositivo_id IS NULL'
            );

            // Se quita la clave foranea, los indices y la columna usuario_id.
            $clave = $this->claveForanea('lecturas_sensores', 'usuario_id');
            if ($clave !== null) {
                $this->db->query("ALTER TABLE lecturas_sensores DROP FOREIGN KEY {$clave}");
            }

            foreach ($this->indicesConColumna('lecturas_sensores', 'usuario_id') as $indice) {
                $this->db->query("ALTER TABLE lecturas_sensores DROP INDEX {$indice}");
            }

            $this->db->query('ALTER TABLE lecturas_sensores DROP usuario_id');
        }

        // Columnas que no corresponden a una lectura.
        foreach (['turbidez', 'modo_vacaciones'] as $columna) {
            if ($this->existeColumna('lecturas_sensores', $columna)) {
                $this->db->query("ALTER TABLE lecturas_sensores DROP {$columna}");
            }
        }

        // dispositivo_id obligatorio; al borrar un dispositivo se borran sus lecturas.
        $clave = $this->claveForanea('lecturas_sensores', 'dispositivo_id');
        $listo = $clave !== null
            && ! $this->aceptaNull('lecturas_sensores', 'dispositivo_id')
            && $this->reglaAlBorrar('lecturas_sensores', $clave) === 'CASCADE';

        if (! $listo) {
            if ($clave !== null) {
                $this->db->query("ALTER TABLE lecturas_sensores DROP FOREIGN KEY {$clave}");
            }

            $this->db->query(
                'ALTER TABLE lecturas_sensores
                 MODIFY dispositivo_id INT(11) UNSIGNED NOT NULL,
                 ADD CONSTRAINT fk_lecturas_dispositivo FOREIGN KEY (dispositivo_id)
                     REFERENCES dispositivos (id) ON DELETE CASCADE ON UPDATE CASCADE'
            );
        }
    }

    /* ── 3FN: productos y pedidos ───────────────────────── */

    // Crea productos, la conecta con pedidos y quita lo que sobra en pedidos.
    private function crearProductos(): void
    {
        if (! $this->existeTabla('productos')) {
            $this->db->query(
                'CREATE TABLE productos (
                    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    sku VARCHAR(60) NOT NULL,
                    nombre VARCHAR(120) NOT NULL,
                    created_at DATETIME NULL DEFAULT NULL,
                    updated_at DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_sku (sku)
                ) ' . self::OPCIONES_TABLA
            );
        }

        if ($this->existeColumna('pedidos', 'producto_sku')) {
            // Un producto por cada sku que aparece en los pedidos.
            $this->db->query(
                'INSERT IGNORE INTO productos (sku, nombre, created_at, updated_at)
                 SELECT producto_sku, MAX(producto_nombre), NOW(), NOW() FROM pedidos GROUP BY producto_sku'
            );

            // Cada pedido apunta a su producto.
            if (! $this->existeColumna('pedidos', 'producto_id')) {
                $this->db->query('ALTER TABLE pedidos ADD producto_id INT(11) UNSIGNED NULL DEFAULT NULL AFTER email');
            }

            $this->db->query('UPDATE pedidos p JOIN productos pr ON pr.sku = p.producto_sku SET p.producto_id = pr.id');
            $this->db->query(
                'ALTER TABLE pedidos
                 MODIFY producto_id INT(11) UNSIGNED NOT NULL,
                 ADD CONSTRAINT fk_pedidos_producto FOREIGN KEY (producto_id)
                     REFERENCES productos (id) ON DELETE RESTRICT ON UPDATE CASCADE,
                 DROP producto_sku,
                 DROP producto_nombre'
            );
        }

        // El total se calcula (cantidad x precio_unitario): no se guarda.
        if ($this->existeColumna('pedidos', 'total')) {
            $this->db->query('ALTER TABLE pedidos DROP total');
        }
    }

    // Vista para mirar los pedidos en phpMyAdmin con el producto y el total.
    private function crearVistaPedidos(): void
    {
        $this->db->query(
            'CREATE OR REPLACE VIEW vista_pedidos AS
             SELECT p.id, p.referencia, p.usuario_id, p.email,
                    pr.sku AS producto_sku, pr.nombre AS producto_nombre,
                    p.cantidad, p.precio_unitario, ROUND(p.cantidad * p.precio_unitario, 2) AS total,
                    p.moneda, p.proveedor, p.preferencia_id, p.pago_id, p.estado, p.estado_detalle,
                    p.monto_pagado, p.pagado_at, p.created_at, p.updated_at
             FROM pedidos p
             JOIN productos pr ON pr.id = p.producto_id'
        );
    }

    /* ── Para deshacer (php spark migrate:rollback) ─────── */

    // pedidos vuelve a guardar sku, nombre y total; se borra productos.
    private function volverPedidosSinProductos(): void
    {
        if ($this->existeColumna('pedidos', 'producto_id')) {
            $this->db->query(
                'ALTER TABLE pedidos
                 ADD producto_sku VARCHAR(60) NULL DEFAULT NULL AFTER email,
                 ADD producto_nombre VARCHAR(120) NULL DEFAULT NULL AFTER producto_sku,
                 ADD total DECIMAL(12,2) NULL DEFAULT NULL AFTER precio_unitario'
            );
            $this->db->query(
                'UPDATE pedidos p JOIN productos pr ON pr.id = p.producto_id
                 SET p.producto_sku = pr.sku, p.producto_nombre = pr.nombre, p.total = ROUND(p.cantidad * p.precio_unitario, 2)'
            );

            $clave = $this->claveForanea('pedidos', 'producto_id');
            if ($clave !== null) {
                $this->db->query("ALTER TABLE pedidos DROP FOREIGN KEY {$clave}");
            }

            $this->db->query(
                'ALTER TABLE pedidos
                 MODIFY producto_sku VARCHAR(60) NOT NULL,
                 MODIFY producto_nombre VARCHAR(120) NOT NULL,
                 MODIFY total DECIMAL(12,2) NOT NULL,
                 DROP producto_id'
            );
        }

        $this->db->query('DROP TABLE IF EXISTS productos');
    }

    // lecturas_sensores vuelve a tener usuario_id (sacado del dispositivo) y sus columnas.
    private function volverLecturasConUsuario(): void
    {
        if (! $this->existeColumna('lecturas_sensores', 'usuario_id')) {
            $this->db->query('ALTER TABLE lecturas_sensores ADD usuario_id INT(11) UNSIGNED NULL DEFAULT NULL AFTER id');
            $this->db->query('UPDATE lecturas_sensores l JOIN dispositivos d ON d.id = l.dispositivo_id SET l.usuario_id = d.usuario_id');
            $this->db->query(
                'ALTER TABLE lecturas_sensores
                 MODIFY usuario_id INT(11) UNSIGNED NOT NULL,
                 ADD KEY idx_usuario_fecha (usuario_id, created_at),
                 ADD CONSTRAINT fk_lecturas_usuario FOREIGN KEY (usuario_id)
                     REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE'
            );

            // El dispositivo vuelve a ser opcional (al borrarlo, la lectura queda del usuario).
            $clave = $this->claveForanea('lecturas_sensores', 'dispositivo_id');
            if ($clave !== null) {
                $this->db->query("ALTER TABLE lecturas_sensores DROP FOREIGN KEY {$clave}");
            }

            $this->db->query(
                'ALTER TABLE lecturas_sensores
                 MODIFY dispositivo_id INT(11) UNSIGNED NULL DEFAULT NULL,
                 ADD CONSTRAINT fk_lecturas_dispositivo FOREIGN KEY (dispositivo_id)
                     REFERENCES dispositivos (id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }

        if (! $this->existeColumna('lecturas_sensores', 'turbidez')) {
            $this->db->query('ALTER TABLE lecturas_sensores ADD turbidez DECIMAL(6,2) NULL DEFAULT NULL AFTER ph');
        }

        if (! $this->existeColumna('lecturas_sensores', 'modo_vacaciones')) {
            $this->db->query('ALTER TABLE lecturas_sensores ADD modo_vacaciones TINYINT(1) NOT NULL DEFAULT 0 AFTER calefactor');
        }
    }

    // comandos_dispositivo vuelve a guardar los gramos como texto JSON.
    private function volverParametros(): void
    {
        if (! $this->existeColumna('comandos_dispositivo', 'parametros')) {
            $this->db->query('ALTER TABLE comandos_dispositivo ADD parametros TEXT NULL DEFAULT NULL AFTER accion');
            $this->db->query("UPDATE comandos_dispositivo SET parametros = JSON_OBJECT('gramos', cantidad_gramos) WHERE cantidad_gramos IS NOT NULL");
        }

        if ($this->existeColumna('comandos_dispositivo', 'cantidad_gramos')) {
            $this->db->query('ALTER TABLE comandos_dispositivo DROP cantidad_gramos');
        }
    }

    // configuracion_pecera vuelve a tener hora_alim_1 y hora_alim_2 (los dos primeros horarios).
    private function volverHorariosEnColumnas(): void
    {
        if (! $this->existeColumna('configuracion_pecera', 'hora_alim_1')) {
            $this->db->query(
                "ALTER TABLE configuracion_pecera
                 ADD hora_alim_1 TIME NULL DEFAULT '08:00:00' AFTER temp_objetivo,
                 ADD hora_alim_2 TIME NULL DEFAULT '18:00:00' AFTER hora_alim_1"
            );

            if ($this->existeTabla('horarios_alimentacion')) {
                $this->db->query(
                    'UPDATE configuracion_pecera c
                     SET hora_alim_1 = (SELECT MIN(h.hora) FROM horarios_alimentacion h WHERE h.configuracion_id = c.id),
                         hora_alim_2 = (SELECT MIN(h.hora) FROM horarios_alimentacion h
                                        WHERE h.configuracion_id = c.id
                                          AND h.hora > (SELECT MIN(h2.hora) FROM horarios_alimentacion h2 WHERE h2.configuracion_id = c.id))'
                );
            }
        }

        $this->db->query('DROP TABLE IF EXISTS horarios_alimentacion');
    }

    // "nombre" vuelve a guardar nombre y apellido juntos.
    private function volverNombreCompleto(): void
    {
        if ($this->existeColumna('usuarios', 'apellido')) {
            $this->db->query("UPDATE usuarios SET nombre = LEFT(TRIM(CONCAT(nombre, ' ', apellido)), 100)");
            $this->db->query('ALTER TABLE usuarios DROP apellido');
        }
    }

    /* ── Consultas sobre la estructura actual ───────────── */

    /** Si la tabla existe (se pregunta cada vez: CodeIgniter guarda la lista en memoria). */
    private function existeTabla(string $tabla): bool
    {
        return $this->db->query(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$tabla]
        )->getRow() !== null;
    }

    /** Si la columna existe en la tabla. */
    private function existeColumna(string $tabla, string $columna): bool
    {
        return $this->datosColumna($tabla, $columna) !== null;
    }

    /** Si la columna acepta NULL. */
    private function aceptaNull(string $tabla, string $columna): bool
    {
        return ($this->datosColumna($tabla, $columna)['IS_NULLABLE'] ?? 'NO') === 'YES';
    }

    // Datos de una columna (o null si no existe).
    private function datosColumna(string $tabla, string $columna): ?array
    {
        return $this->db->query(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tabla, $columna]
        )->getRowArray();
    }

    /** Nombre de la clave foranea de esa columna (cambia segun como se instalo la base). */
    private function claveForanea(string $tabla, string $columna): ?string
    {
        $fila = $this->db->query(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$tabla, $columna]
        )->getRow();

        return $fila->CONSTRAINT_NAME ?? null;
    }

    /** Que hace la clave foranea al borrar la fila de la otra tabla (CASCADE, SET NULL...). */
    private function reglaAlBorrar(string $tabla, string $clave): ?string
    {
        $fila = $this->db->query(
            'SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$tabla, $clave]
        )->getRow();

        return $fila->DELETE_RULE ?? null;
    }

    /** Indices (sin la clave primaria) que usan esa columna. */
    private function indicesConColumna(string $tabla, string $columna): array
    {
        $filas = $this->db->query(
            'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND INDEX_NAME <> \'PRIMARY\'',
            [$tabla, $columna]
        )->getResultArray();

        return array_column($filas, 'INDEX_NAME');
    }
}
