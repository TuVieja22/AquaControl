<?php

namespace App\Controllers;

use Config\Commerce;
use Config\MercadoPago;

/**
 * Portada (landing) con la compra del kit.
 */
class Home extends BaseController
{
    public function index(): string
    {
        $tienda = config(Commerce::class);
        $mp = config(MercadoPago::class);
        $precio = $tienda->precio();

        return view('home/index', [
            'title'    => 'Inicio',
            'producto' => [
                'sku'             => $tienda->productSku,
                'nombre'          => $tienda->productName,
                'descripcion'     => $tienda->productDescription,
                'precio'          => $precio,
                'moneda'          => $tienda->moneda(),
                'cantidadMaxima'  => $tienda->cantidadMaxima(),
                'mensajeEntrega'  => $tienda->deliveryMessage,
                'precioTarjeta'   => $precio !== null ? $tienda->moneda() . ' ' . number_format($precio, 0, ',', '.') : 'Consultar',
                'precioResumen'   => $precio !== null ? $tienda->moneda() . ' ' . number_format($precio, 2, ',', '.') : null,
            ],
            // Lo que necesita purchase.js para calcular el total y abrir Mercado Pago.
            'compra'   => [
                'product' => [
                    'sku'         => $tienda->productSku,
                    'name'        => $tienda->productName,
                    'unitPrice'   => $precio,
                    'currency'    => $tienda->moneda(),
                    'maxQuantity' => $tienda->cantidadMaxima(),
                ],
                'payment' => [
                    'locale'                   => $tienda->locale,
                    'mercadoPagoPublicKey'     => $mp->publicKey,
                    'mercadoPagoPreferenceUrl' => $mp->preferenceUrl ?: base_url('checkout/mercadopago/preference'),
                ],
            ],
        ]);
    }
}
