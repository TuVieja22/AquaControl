<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pedidos de la tienda. Se crean al generar la preferencia de Mercado Pago y su
 * estado se actualiza consultando el pago real a la API (webhook y back_urls).
 */
class CreatePedidosTable extends Migration
{
    // Crea la tabla pedidos.
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // Codigo unico del pedido.
            'referencia' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'comment'    => 'external_reference enviada a Mercado Pago',
            ],
            // Quien compro: su cuenta (si tenia sesion) y su correo.
            'usuario_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            // Que compro: producto, cantidad, precio, total y moneda.
            'producto_sku' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'producto_nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'cantidad' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'precio_unitario' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
            ],
            'total' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
            ],
            'moneda' => [
                'type'       => 'CHAR',
                'constraint' => 3,
            ],
            // Datos del cobro en Mercado Pago.
            'proveedor' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'mercadopago',
            ],
            'preferencia_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
            'pago_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
            // Estado del pedido y su detalle.
            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'pendiente',
                'comment'    => 'pendiente | en_proceso | aprobado | rechazado | cancelado | reembolsado',
            ],
            'estado_detalle' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
            // Cuanto se pago y cuando.
            'monto_pagado' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => true,
            ],
            'pagado_at' => [
                'type' => 'DATETIME',
                'null' => true,
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

        // Claves e indices; si se borra el usuario, sus pedidos quedan (sin usuario).
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('referencia');
        $this->forge->addKey('estado');
        $this->forge->addKey('pago_id');
        $this->forge->addForeignKey('usuario_id', 'usuarios', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('pedidos', true);
    }

    // Deshace la migracion: borra la tabla.
    public function down(): void
    {
        $this->forge->dropTable('pedidos', true);
    }
}
