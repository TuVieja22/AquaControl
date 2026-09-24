<?php

namespace App\Controllers;

use App\Models\PedidoModel;

/**
 * Panel de pedidos de la tienda (solo administradores).
 */
class Pedidos extends BaseController
{
    public function index(): string
    {
        $pedidoModel = new PedidoModel();
        $status = (string) $this->request->getGet('estado');
        $status = array_key_exists($status, PedidoModel::ESTADOS) ? $status : null;

        return view('orders/index', [
            'extraCss'     => ['css/dashboard.css', 'css/management.css'],
            'title'        => 'Pedidos',
            'orders'       => $pedidoModel->listado($status),
            'summary'      => $pedidoModel->resumen(),
            'statusNames'  => PedidoModel::ESTADOS,
            'activeStatus' => $status,
        ]);
    }
}
