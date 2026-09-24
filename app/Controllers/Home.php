<?php

namespace App\Controllers;

/**
 * Home Controller
 * app/Controllers/Home.php
 */
class Home extends BaseController
{
    public function index(): string
    {
        $unitPrice = env('commerce.unitPrice');

        return view('home/index', [
            'title'    => 'Inicio',
            'extraCss' => ['css/components/payment-button.css', 'css/purchase.css'],
            'extraJs'  => ['js/purchase.js'],
            'purchase' => [
                'product' => [
                    'sku'             => env('commerce.productSku', 'aquacontrol'),
                    'name'            => env('commerce.productName', 'AquaControl'),
                    'headline'        => env('commerce.productHeadline', 'Monitoreo inteligente para peceras en una sola compra.'),
                    'description'     => env('commerce.productDescription', 'Elegi la cantidad, revisa tu pedido y continua con el medio de pago que prefieras sin salir de la experiencia AquaControl.'),
                    'unitPrice'       => is_numeric($unitPrice) ? (float) $unitPrice : null,
                    'currency'        => strtoupper((string) env('commerce.currency', 'ARS')),
                    'maxQuantity'     => max(1, (int) env('commerce.maxQuantity', 6)),
                    'deliveryMessage' => env('commerce.deliveryMessage', 'La entrega y activacion se coordinan al confirmar el pago.'),
                ],
                'payment' => [
                    'locale'                 => env('commerce.locale', 'es-AR'),
                    'checkoutOrderUrl'         => env('commerce.checkoutOrderUrl', ''),
                    'mercadoPagoPublicKey'     => env('mercadopago.publicKey', ''),
                    'mercadoPagoPreferenceUrl' => env('mercadopago.preferenceUrl', base_url('checkout/mercadopago/preference')),
                ],
            ],
        ]);
    }
}
