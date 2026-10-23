<?php

namespace App\Models;

use CodeIgniter\Model;

// Pedidos de la tienda y su estado de pago.
class PedidoModel extends Model
{
    /** Estados de pago de Mercado Pago -> estado del pedido. */
    private const ESTADOS_MERCADO_PAGO = [
        'approved'     => 'aprobado',
        'authorized'   => 'en_proceso',
        'in_process'   => 'en_proceso',
        'in_mediation' => 'en_proceso',
        'pending'      => 'pendiente',
        'rejected'     => 'rechazado',
        'cancelled'    => 'cancelado',
        'refunded'     => 'reembolsado',
        'charged_back' => 'reembolsado',
    ];

    // Tabla, clave y columnas que se pueden escribir.
    protected $table            = 'pedidos';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'referencia',
        'usuario_id',
        'email',
        'producto_sku',
        'producto_nombre',
        'cantidad',
        'precio_unitario',
        'total',
        'moneda',
        'proveedor',
        'preferencia_id',
        'pago_id',
        'estado',
        'estado_detalle',
        'monto_pagado',
        'pagado_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Pedido que tiene ese codigo de referencia.
    public function porReferencia(string $reference): ?array
    {
        return $this->where('referencia', $reference)->first();
    }

    /**
     * Actualiza el pedido con los datos de un pago consultado a la API de Mercado Pago.
     * Devuelve el pedido actualizado, o null si el pago no corresponde a ningun pedido.
     *
     * @param array{id: int|string, status: ?string, status_detail: ?string, external_reference: ?string, transaction_amount: ?float, currency_id: ?string, date_approved: ?string} $payment
     */
    public function aplicarPagoMercadoPago(array $payment): ?array
    {
        // El pago trae la referencia del pedido al que pertenece.
        $order = $this->porReferencia((string) ($payment['external_reference'] ?? ''));
        if ($order === null) {
            return null;
        }

        // Estado del pedido segun el estado del pago, y cuanto se pago.
        $status = self::ESTADOS_MERCADO_PAGO[$payment['status'] ?? ''] ?? 'pendiente';
        $detail = $payment['status_detail'] ?? null;
        $paid = isset($payment['transaction_amount']) ? round((float) $payment['transaction_amount'], 2) : null;

        // Un pago aprobado por otro monto u otra moneda no se da por bueno automaticamente.
        if ($status === 'aprobado' && ($paid !== round((float) $order['total'], 2) || ($payment['currency_id'] ?? $order['moneda']) !== $order['moneda'])) {
            $status = 'en_proceso';
            $detail = 'monto_no_coincide';
        }

        // Datos del pago que se guardan en el pedido.
        $changes = [
            'pago_id'        => (string) $payment['id'],
            'estado'         => $status,
            'estado_detalle' => $detail !== null ? mb_substr((string) $detail, 0, 80) : null,
            'monto_pagado'   => $paid,
        ];

        // La primera vez que queda aprobado se anota la fecha de pago.
        if ($status === 'aprobado' && empty($order['pagado_at'])) {
            $changes['pagado_at'] = ! empty($payment['date_approved'])
                ? date('Y-m-d H:i:s', strtotime((string) $payment['date_approved']))
                : date('Y-m-d H:i:s');
        }

        $this->update($order['id'], $changes);

        return $this->find($order['id']);
    }
}
