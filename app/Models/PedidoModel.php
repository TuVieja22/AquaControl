<?php

namespace App\Models;

use CodeIgniter\Model;

class PedidoModel extends Model
{
    public const ESTADOS = [
        'pendiente'   => 'Pendiente',
        'en_proceso'  => 'En proceso',
        'aprobado'    => 'Aprobado',
        'rechazado'   => 'Rechazado',
        'cancelado'   => 'Cancelado',
        'reembolsado' => 'Reembolsado',
    ];

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

    public function porReferencia(string $reference): ?array
    {
        return $this->where('referencia', $reference)->first();
    }

    public function listado(?string $status = null): array
    {
        if ($status !== null) {
            $this->where('estado', $status);
        }

        return $this->orderBy('created_at', 'DESC')->findAll(200);
    }

    public function resumen(): array
    {
        $rows = $this->select('estado, COUNT(*) AS cantidad, SUM(total) AS total')
            ->groupBy('estado')
            ->findAll();

        return array_column($rows, null, 'estado');
    }

    /**
     * Actualiza el pedido con los datos de un pago consultado a la API de Mercado Pago.
     * Devuelve el pedido actualizado, o null si el pago no corresponde a ningun pedido.
     *
     * @param array{id: int|string, status: ?string, status_detail: ?string, external_reference: ?string, transaction_amount: ?float, currency_id: ?string, date_approved: ?string} $payment
     */
    public function aplicarPagoMercadoPago(array $payment): ?array
    {
        $order = $this->porReferencia((string) ($payment['external_reference'] ?? ''));
        if ($order === null) {
            return null;
        }

        $status = self::ESTADOS_MERCADO_PAGO[$payment['status'] ?? ''] ?? 'pendiente';
        $detail = $payment['status_detail'] ?? null;
        $paid = isset($payment['transaction_amount']) ? round((float) $payment['transaction_amount'], 2) : null;

        // Un pago aprobado por otro monto u otra moneda no se da por bueno automaticamente.
        if ($status === 'aprobado' && ($paid !== round((float) $order['total'], 2) || ($payment['currency_id'] ?? $order['moneda']) !== $order['moneda'])) {
            $status = 'en_proceso';
            $detail = 'monto_no_coincide';
        }

        $changes = [
            'pago_id'        => (string) $payment['id'],
            'estado'         => $status,
            'estado_detalle' => $detail !== null ? mb_substr((string) $detail, 0, 80) : null,
            'monto_pagado'   => $paid,
        ];

        if ($status === 'aprobado' && empty($order['pagado_at'])) {
            $changes['pagado_at'] = ! empty($payment['date_approved'])
                ? date('Y-m-d H:i:s', strtotime((string) $payment['date_approved']))
                : date('Y-m-d H:i:s');
        }

        $this->update($order['id'], $changes);

        return $this->find($order['id']);
    }
}
