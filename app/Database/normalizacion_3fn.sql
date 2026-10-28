-- ============================================================================
-- Normalizacion de la base aquacare hasta la tercera forma normal (3FN).
--
-- Es el mismo cambio que la migracion
-- app/Database/Migrations/2026-10-23-000010_NormalizarHastaTerceraFormaNormal.php,
-- escrito en SQL para correrlo a mano en phpMyAdmin (pestania SQL, con la base
-- aquacare elegida). Sirve para una base con la estructura anterior (la de la
-- migracion 2026-10-01-000009). Despues de correrlo, `php spark migrate` solo anota
-- la migracion como hecha.
--
-- ANTES de correrlo: exportar la base (phpMyAdmin > Exportar) como copia de seguridad.
-- El diagrama de como queda esta en base_de_datos.dbml (https://dbdiagram.io).
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1FN: "nombre" guardaba nombre y apellido juntos -> dos campos.
-- El nombre es la primera palabra y el apellido todo lo que sigue.
-- ----------------------------------------------------------------------------
ALTER TABLE usuarios ADD apellido VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre;
UPDATE usuarios SET nombre = TRIM(nombre);
UPDATE usuarios
SET apellido = TRIM(SUBSTRING(nombre, LOCATE(' ', nombre) + 1)),
    nombre = SUBSTRING_INDEX(nombre, ' ', 1)
WHERE LOCATE(' ', nombre) > 0;
ALTER TABLE usuarios ALTER apellido DROP DEFAULT;

-- ----------------------------------------------------------------------------
-- Campos mal definidos: pH con 2 decimales (antes 1) y valores por defecto
-- iguales a los que muestra la pagina.
-- ----------------------------------------------------------------------------
ALTER TABLE configuracion_pecera
  MODIFY temp_min DECIMAL(4,1) NOT NULL DEFAULT 24.0,
  MODIFY temp_max DECIMAL(4,1) NOT NULL DEFAULT 27.0,
  MODIFY ph_min DECIMAL(4,2) NOT NULL DEFAULT 6.80,
  MODIFY ph_max DECIMAL(4,2) NOT NULL DEFAULT 7.60;

-- ----------------------------------------------------------------------------
-- 1FN: hora_alim_1 y hora_alim_2 eran un grupo repetido -> una fila por horario.
-- ----------------------------------------------------------------------------
CREATE TABLE horarios_alimentacion (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  configuracion_id INT(11) UNSIGNED NOT NULL,
  hora TIME NOT NULL COMMENT 'hora Argentina',
  created_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_configuracion_hora (configuracion_id, hora),
  CONSTRAINT fk_horarios_configuracion FOREIGN KEY (configuracion_id)
    REFERENCES configuracion_pecera (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO horarios_alimentacion (configuracion_id, hora, created_at)
SELECT id, hora_alim_1, NOW() FROM configuracion_pecera WHERE hora_alim_1 IS NOT NULL;
INSERT IGNORE INTO horarios_alimentacion (configuracion_id, hora, created_at)
SELECT id, hora_alim_2, NOW() FROM configuracion_pecera WHERE hora_alim_2 IS NOT NULL;

ALTER TABLE configuracion_pecera DROP hora_alim_1, DROP hora_alim_2;

-- ----------------------------------------------------------------------------
-- 1FN: parametros guardaba un texto JSON ({"gramos": 8}) -> columna cantidad_gramos.
-- ----------------------------------------------------------------------------
ALTER TABLE comandos_dispositivo ADD cantidad_gramos DECIMAL(5,2) NULL DEFAULT NULL AFTER accion;
UPDATE comandos_dispositivo
SET cantidad_gramos = CAST(JSON_UNQUOTE(JSON_EXTRACT(parametros, '$.gramos')) AS DECIMAL(5,2))
WHERE JSON_VALID(parametros) AND JSON_EXTRACT(parametros, '$.gramos') IS NOT NULL;
ALTER TABLE comandos_dispositivo DROP parametros;

-- ----------------------------------------------------------------------------
-- 3FN: en lecturas_sensores el usuario depende del dispositivo (dependencia
-- transitiva) -> se quita usuario_id y el dispositivo pasa a ser obligatorio.
-- modo_vacaciones es dato de configuracion_pecera y turbidez nunca tuvo sensor.
-- ----------------------------------------------------------------------------

-- Usuarios con lecturas sin dispositivo y ningun dispositivo: se les crea uno.
INSERT INTO dispositivos (usuario_id, nombre, tipo, ubicacion, created_at, updated_at)
SELECT DISTINCT l.usuario_id, 'Lecturas anteriores', 'otro', 'Sin especificar', NOW(), NOW()
FROM lecturas_sensores l
WHERE l.dispositivo_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM dispositivos d WHERE d.usuario_id = l.usuario_id);

-- Cada lectura sin dispositivo pasa al primer dispositivo de su usuario.
UPDATE lecturas_sensores l
JOIN (SELECT usuario_id, MIN(id) AS dispositivo_id FROM dispositivos GROUP BY usuario_id) primero
  ON primero.usuario_id = l.usuario_id
SET l.dispositivo_id = primero.dispositivo_id
WHERE l.dispositivo_id IS NULL;

ALTER TABLE lecturas_sensores
  DROP FOREIGN KEY fk_lecturas_usuario,
  DROP FOREIGN KEY fk_lecturas_dispositivo;

-- Al borrar un dispositivo se borran sus lecturas (antes quedaban a nombre del usuario).
ALTER TABLE lecturas_sensores
  DROP INDEX idx_usuario_fecha,
  DROP usuario_id,
  DROP turbidez,
  DROP modo_vacaciones,
  MODIFY dispositivo_id INT(11) UNSIGNED NOT NULL,
  ADD CONSTRAINT fk_lecturas_dispositivo FOREIGN KEY (dispositivo_id)
    REFERENCES dispositivos (id) ON DELETE CASCADE ON UPDATE CASCADE;

-- ----------------------------------------------------------------------------
-- 3FN: en pedidos el nombre del producto depende del sku (tabla productos) y el
-- total se calcula con cantidad x precio_unitario (vista vista_pedidos).
-- ----------------------------------------------------------------------------
CREATE TABLE productos (
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(60) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  created_at DATETIME NULL DEFAULT NULL,
  updated_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO productos (sku, nombre, created_at, updated_at)
SELECT producto_sku, MAX(producto_nombre), NOW(), NOW() FROM pedidos GROUP BY producto_sku;

ALTER TABLE pedidos ADD producto_id INT(11) UNSIGNED NULL DEFAULT NULL AFTER email;
UPDATE pedidos p JOIN productos pr ON pr.sku = p.producto_sku SET p.producto_id = pr.id;

ALTER TABLE pedidos
  MODIFY producto_id INT(11) UNSIGNED NOT NULL,
  ADD CONSTRAINT fk_pedidos_producto FOREIGN KEY (producto_id)
    REFERENCES productos (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  DROP producto_sku,
  DROP producto_nombre,
  DROP total;

-- Para mirar los pedidos en phpMyAdmin con el producto y el total calculado.
CREATE OR REPLACE VIEW vista_pedidos AS
SELECT p.id, p.referencia, p.usuario_id, p.email,
       pr.sku AS producto_sku, pr.nombre AS producto_nombre,
       p.cantidad, p.precio_unitario, ROUND(p.cantidad * p.precio_unitario, 2) AS total,
       p.moneda, p.proveedor, p.preferencia_id, p.pago_id, p.estado, p.estado_detalle,
       p.monto_pagado, p.pagado_at, p.created_at, p.updated_at
FROM pedidos p
JOIN productos pr ON pr.id = p.producto_id;
