<?php

namespace App\Controllers;

use App\Models\PedidoModel;
use CodeIgniter\HTTP\ResponseInterface;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use Throwable;

class Checkout extends BaseController
{
    private PedidoModel $pedidoModel;

    public function __construct()
    {
        $this->pedidoModel = new PedidoModel();
    }

    public function mercadoPagoPreference(): ResponseInterface
    {
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->jsonError('Solicitud invalida.', 400);
        }

        $product = $this->productConfig();
        $validation = $this->validateMercadoPagoPayload($payload, $product);

        if ($validation['error'] !== null) {
            return $this->jsonError($validation['error'], $validation['status']);
        }

        $accessToken = trim((string) env('mercadopago.accessToken', ''));

        if ($accessToken === '') {
            return $this->jsonError('Configura mercadopago.accessToken en el archivo .env.', 503);
        }

        $this->configureSdk($accessToken);
        $externalReference = $this->externalReference();

        try {
            $preference = (new PreferenceClient())->create(
                $this->buildPreferenceRequest($product, $validation['quantity'], $validation['email'], $externalReference)
            );

            $this->pedidoModel->insert([
                'referencia'      => $externalReference,
                'usuario_id'      => session()->get('user_id') ?: null,
                'email'           => $validation['email'],
                'producto_sku'    => $product['sku'],
                'producto_nombre' => $product['name'],
                'cantidad'        => $validation['quantity'],
                'precio_unitario' => $product['unitPrice'],
                'total'           => round($product['unitPrice'] * $validation['quantity'], 2),
                'moneda'          => $product['currency'],
                'proveedor'       => 'mercadopago',
                'preferencia_id'  => $preference->id,
                'estado'          => 'pendiente',
            ]);

            return $this->response->setJSON([
                'preferenceId'     => $preference->id,
                'initPoint'        => $preference->init_point,
                'sandboxInitPoint' => $preference->sandbox_init_point,
            ]);
        } catch (MPApiException $exception) {
            log_message('error', 'Mercado Pago API error: {status} {content}', [
                'status'  => $exception->getStatusCode(),
                'content' => json_encode($exception->getApiResponse()->getContent()),
            ]);

            return $this->jsonError('Mercado Pago rechazo la preferencia. Revisa credenciales y datos del pedido.', 502);
        } catch (Throwable $exception) {
            log_message('error', 'Mercado Pago SDK error: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $this->jsonError('No se pudo conectar con Mercado Pago.', 502);
        }
    }

    /*
     * Retorno desde Mercado Pago (back_urls). Los parametros de la URL los controla el
     * comprador, asi que no se confia en ellos: se consulta el pago real a la API y el
     * mensaje se arma con ese estado verificado.
     */
    public function mercadoPagoSuccess(): ResponseInterface
    {
        return $this->handleReturn();
    }

    public function mercadoPagoFailure(): ResponseInterface
    {
        return $this->handleReturn();
    }

    public function mercadoPagoPending(): ResponseInterface
    {
        return $this->handleReturn();
    }

    /**
     * Notificaciones (webhooks) de Mercado Pago. Configurar en la aplicacion de Mercado Pago
     * o en mercadopago.notificationUrl: https://TU-DOMINIO/checkout/mercadopago/webhook
     *
     * Aunque alguien falsifique una notificacion, solo provoca que se vuelva a consultar el
     * pago a la API; si ademas se define mercadopago.webhookSecret se valida la firma.
     */
    public function mercadoPagoWebhook(): ResponseInterface
    {
        try {
            $body = $this->request->is('json') ? ($this->request->getJSON(true) ?? []) : [];
        } catch (Throwable) {
            $body = [];
        }

        $type = (string) ($body['type'] ?? $this->request->getGet('type') ?? $this->request->getGet('topic') ?? '');
        $paymentId = (string) ($body['data']['id'] ?? $this->request->getGet('data_id') ?? $this->request->getGet('id') ?? '');

        if ($type !== 'payment' || ! ctype_digit($paymentId)) {
            // Otros eventos (merchant_order, etc.) no se usan: se confirman para que no se reintenten.
            return $this->response->setStatusCode(200)->setJSON(['received' => true]);
        }

        if (! $this->validWebhookSignature($paymentId)) {
            return $this->response->setStatusCode(401)->setJSON(['received' => false]);
        }

        $order = $this->syncPayment($paymentId);

        // Si falla la consulta a la API respondemos 500 para que Mercado Pago reintente.
        return $this->response
            ->setStatusCode($order === false ? 500 : 200)
            ->setJSON(['received' => $order !== false]);
    }

    private function productConfig(): array
    {
        $unitPrice = env('commerce.unitPrice');

        return [
            'sku'             => (string) env('commerce.productSku', 'aquacontrol'),
            'name'            => (string) env('commerce.productName', 'AquaControl'),
            'description'     => (string) env('commerce.productDescription', 'Sistema inteligente IoT para peceras.'),
            'currency'        => strtoupper((string) env('commerce.currency', 'ARS')),
            'maxQuantity'     => max(1, (int) env('commerce.maxQuantity', 6)),
            'unitPrice'       => is_numeric($unitPrice) ? round((float) $unitPrice, 2) : null,
        ];
    }

    private function validateMercadoPagoPayload(array $payload, array $product): array
    {
        $paymentMethod = (string) ($payload['paymentMethod'] ?? 'mercadopago');

        if ($paymentMethod !== 'mercadopago') {
            return $this->validationResult('Metodo de pago invalido.', 400);
        }

        if ($product['unitPrice'] === null || $product['unitPrice'] <= 0) {
            return $this->validationResult('Configura commerce.unitPrice con un precio mayor a cero.', 503);
        }

        $requestedProduct = is_array($payload['product'] ?? null) ? $payload['product'] : [];
        $requestedSku = (string) ($requestedProduct['sku'] ?? '');

        if ($requestedSku !== $product['sku']) {
            return $this->validationResult('Producto invalido.', 400);
        }

        $quantity = (int) ($requestedProduct['quantity'] ?? 0);

        if ($quantity < 1 || $quantity > $product['maxQuantity']) {
            return $this->validationResult('Cantidad invalida.', 400);
        }

        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
        $email = trim((string) ($customer['email'] ?? ''));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->validationResult('Email invalido.', 400);
        }

        return [
            'email'    => $email,
            'error'    => null,
            'quantity' => $quantity,
            'status'   => 200,
        ];
    }

    private function handleReturn(): ResponseInterface
    {
        $paymentId = (string) ($this->request->getGet('payment_id') ?? $this->request->getGet('collection_id') ?? '');

        if (! ctype_digit($paymentId)) {
            // El comprador volvio sin pagar (p. ej. "Volver al sitio").
            return $this->redirectWithFlash('info', 'No se completo el pago. Podes intentarlo nuevamente cuando quieras.');
        }

        $order = $this->syncPayment($paymentId);

        if ($order === false || $order === null) {
            return $this->redirectWithFlash('info', 'Recibimos tu pago y lo estamos verificando con Mercado Pago. La confirmacion puede demorar unos minutos.');
        }

        return match ($order['estado']) {
            'aprobado'   => $this->redirectWithFlash('success', 'Pago aprobado. Tu pedido ' . $order['referencia'] . ' esta confirmado.'),
            'rechazado',
            'cancelado'  => $this->redirectWithFlash('error', 'El pago no fue aprobado. Podes intentarlo nuevamente con otro medio.'),
            default      => $this->redirectWithFlash('info', 'Tu pago quedo pendiente de acreditacion. Pedido ' . $order['referencia'] . '.'),
        };
    }

    /**
     * Consulta el pago a la API de Mercado Pago y actualiza el pedido correspondiente.
     *
     * @return array|false|null El pedido actualizado; null si el pago no es de ningun pedido; false si fallo la API.
     */
    private function syncPayment(string $paymentId): array|false|null
    {
        $accessToken = trim((string) env('mercadopago.accessToken', ''));
        if ($accessToken === '') {
            return false;
        }

        $this->configureSdk($accessToken);

        try {
            $payment = (new PaymentClient())->get((int) $paymentId);
        } catch (MPApiException $exception) {
            log_message('error', 'Mercado Pago: no se pudo consultar el pago {id}: {status}', [
                'id'     => $paymentId,
                'status' => $exception->getStatusCode(),
            ]);

            // 404: el pago no existe (id inventado). No tiene sentido reintentar.
            return $exception->getStatusCode() === 404 ? null : false;
        } catch (Throwable $exception) {
            log_message('error', 'Mercado Pago: error consultando el pago {id}: {message}', [
                'id'      => $paymentId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        return $this->pedidoModel->aplicarPagoMercadoPago([
            'id'                 => $payment->id,
            'status'             => $payment->status,
            'status_detail'      => $payment->status_detail,
            'external_reference' => $payment->external_reference,
            'transaction_amount' => $payment->transaction_amount,
            'currency_id'        => $payment->currency_id,
            'date_approved'      => $payment->date_approved,
        ]);
    }

    /**
     * Valida el header x-signature ("ts=...,v1=...") segun la documentacion de Mercado Pago.
     * Si no hay mercadopago.webhookSecret configurado, no se exige firma.
     */
    private function validWebhookSignature(string $paymentId): bool
    {
        $secret = trim((string) env('mercadopago.webhookSecret', ''));
        if ($secret === '') {
            return true;
        }

        $parts = [];
        foreach (explode(',', $this->request->getHeaderLine('x-signature')) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        if (empty($parts['ts']) || empty($parts['v1'])) {
            return false;
        }

        $manifest = 'id:' . strtolower($paymentId) . ';';
        $requestId = $this->request->getHeaderLine('x-request-id');
        if ($requestId !== '') {
            $manifest .= 'request-id:' . $requestId . ';';
        }
        $manifest .= 'ts:' . $parts['ts'] . ';';

        return hash_equals(hash_hmac('sha256', $manifest, $secret), $parts['v1']);
    }

    private function configureSdk(string $accessToken): void
    {
        MercadoPagoConfig::setAccessToken($accessToken);

        if (strtolower((string) env('mercadopago.runtime', '')) === 'local') {
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        }
    }

    private function buildPreferenceRequest(array $product, int $quantity, string $email, string $externalReference): array
    {
        $request = [
            'items' => [
                [
                    'id'          => $product['sku'],
                    'title'       => $product['name'],
                    'description' => $product['description'],
                    'quantity'    => $quantity,
                    'currency_id' => $product['currency'],
                    'unit_price'  => $product['unitPrice'],
                ],
            ],
            'payer' => [
                'email' => $email,
            ],
            'back_urls' => [
                'success' => base_url('checkout/mercadopago/success'),
                'failure' => base_url('checkout/mercadopago/failure'),
                'pending' => base_url('checkout/mercadopago/pending'),
            ],
            'external_reference'   => $externalReference,
            'statement_descriptor' => $this->statementDescriptor($product['name']),
            'metadata'             => [
                'customer_email'     => $email,
                'external_reference' => $externalReference,
                'product_sku'        => $product['sku'],
                'quantity'           => $quantity,
            ],
        ];

        if ($this->shouldUseAutoReturn()) {
            $request['auto_return'] = 'approved';
        }

        $installments = (int) env('mercadopago.installments', 12);
        if ($installments > 0) {
            $request['payment_methods'] = [
                'installments'         => min($installments, 12),
                'default_installments' => 1,
            ];
        }

        // Mercado Pago solo puede notificar a una URL publica: por defecto se usa el webhook
        // propio cuando el sitio corre con HTTPS; en local hay que definir notificationUrl (ngrok).
        $notificationUrl = trim((string) env('mercadopago.notificationUrl', ''));
        if ($notificationUrl === '' && str_starts_with(base_url(), 'https://')) {
            $notificationUrl = base_url('checkout/mercadopago/webhook');
        }
        if ($notificationUrl !== '') {
            $request['notification_url'] = $notificationUrl;
        }

        return $request;
    }

    private function shouldUseAutoReturn(): bool
    {
        $configured = filter_var(env('mercadopago.autoReturn', null), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($configured !== null) {
            return $configured;
        }

        return strtolower((string) env('mercadopago.runtime', '')) !== 'local';
    }

    private function statementDescriptor(string $fallback): string
    {
        $descriptor = (string) env('mercadopago.statementDescriptor', $fallback);
        $descriptor = preg_replace('/[^A-Z0-9 ]/', '', strtoupper($descriptor)) ?: 'AQUACONTROL';

        return substr($descriptor, 0, 22);
    }

    private function externalReference(): string
    {
        return 'AQUA-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    }

    private function validationResult(string $message, int $status): array
    {
        return [
            'email'    => null,
            'error'    => $message,
            'quantity' => null,
            'status'   => $status,
        ];
    }

    private function jsonError(string $message, int $status): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON([
                'success' => false,
                'message' => $message,
            ]);
    }

    private function redirectWithFlash(string $type, string $message): ResponseInterface
    {
        session()->setFlashdata($type, $message);

        return redirect()->to(base_url('/#checkout'));
    }
}
