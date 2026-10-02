<?php

namespace App\Controllers;

use App\Models\PedidoModel;

/**
 * Pedidos de la tienda (solo administradores), con filtro por estado.
 */
class Pedidos extends BaseController
{
    public function index(): string
    {
        $pedidoModel = new PedidoModel();
        $estado = (string) $this->request->getGet('estado');
        $estado = array_key_exists($estado, PedidoModel::ESTADOS) ? $estado : null;

        return view('orders/index', [
            'title'        => 'Pedidos',
            'orders'       => $pedidoModel->listado($estado),
            'summary'      => $pedidoModel->resumen(),
            'statusNames'  => PedidoModel::ESTADOS,
            'activeStatus' => $estado,
        ]);
    }
}
