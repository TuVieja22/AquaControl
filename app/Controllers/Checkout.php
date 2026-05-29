<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use Throwable;

class Checkout extends BaseController
{
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

        MercadoPagoConfig::setAccessToken($accessToken);

        if (strtolower((string) env('mercadopago.runtime', '')) === 'local') {
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        }

        try {
            $preference = (new PreferenceClient())->create(
                $this->buildPreferenceRequest($product, $validation['quantity'], $validation['email'])
            );

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

    public function mercadoPagoSuccess(): ResponseInterface
    {
        return $this->redirectWithFlash('success', 'Pago aprobado. Recibimos la confirmacion de Mercado Pago.');
    }

    public function mercadoPagoFailure(): ResponseInterface
    {
        return $this->redirectWithFlash('error', 'El pago no fue aprobado. Podes intentarlo nuevamente.');
    }

    public function mercadoPagoPending(): ResponseInterface
    {
        return $this->redirectWithFlash('info', 'El pago quedo pendiente de confirmacion.');
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

    private function buildPreferenceRequest(array $product, int $quantity, string $email): array
    {
        $externalReference = $this->externalReference();
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

        $notificationUrl = trim((string) env('mercadopago.notificationUrl', ''));
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

        return redirect()->to(base_url('/#comprar'));
    }
}
