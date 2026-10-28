<?php

namespace App\Controllers;

use App\Models\PedidoModel;
use App\Models\ProductoModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Commerce;
use Config\MercadoPago;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use Throwable;

/**
 * Compra con Mercado Pago:
 * 1. La portada pide una "preferencia" (el pedido) y se guarda como pendiente.
 * 2. El comprador paga en Mercado Pago y vuelve a la pagina (success/failure/pending).
 * 3. Mercado Pago avisa por el webhook. En los pasos 2 y 3 el estado real del pago se
 *    consulta siempre a la API: nunca se confia en los parametros de la URL.
 */
class Checkout extends BaseController
{
    // Modelo de pedidos y configuracion de la tienda y de Mercado Pago.
    private PedidoModel $pedidoModel;
    private Commerce $tienda;
    private MercadoPago $mp;

    // Prepara el modelo y lee la configuracion.
    public function __construct()
    {
        $this->pedidoModel = new PedidoModel();
        $this->tienda = config(Commerce::class);
        $this->mp = config(MercadoPago::class);
    }

    // Crea el cobro en Mercado Pago (la "preferencia") y guarda el pedido como pendiente.
    public function mercadoPagoPreference(): ResponseInterface
    {
        // Datos que manda el formulario de compra (funciones/purchase.js).
        $payload = $this->request->getJSON(true);
        if (! is_array($payload)) {
            return $this->jsonError('Solicitud inválida.', 400);
        }

        // Revisa el producto, la cantidad y el correo.
        [$pedido, $error, $status] = $this->validarPedido($payload);
        if ($error !== null) {
            return $this->jsonError($error, $status);
        }

        // Sin la clave de Mercado Pago no se puede cobrar.
        if (trim($this->mp->accessToken) === '') {
            return $this->jsonError('Configura mercadopago.accessToken en el archivo .env.', 503);
        }

        // Prepara la conexion y arma un codigo unico para este pedido.
        $this->configurarSdk();
        $referencia = 'AQUA-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));

        try {
            // Le pide a Mercado Pago que cree el cobro.
            $preference = (new PreferenceClient())->create($this->preferencia($pedido['cantidad'], $pedido['email'], $referencia));

            // Guarda el pedido en la base, todavia sin pagar.
            $this->pedidoModel->insert([
                'referencia'      => $referencia,
                'usuario_id'      => session()->get('user_id') ?: null,
                'email'           => $pedido['email'],
                'producto_id'     => (new ProductoModel())->idPorSku($this->tienda->productSku, $this->tienda->productName),
                'cantidad'        => $pedido['cantidad'],
                'precio_unitario' => $this->tienda->precio(),
                'moneda'          => $this->tienda->moneda(),
                'proveedor'       => 'mercadopago',
                'preferencia_id'  => $preference->id,
                'estado'          => 'pendiente',
            ]);

            // Le devuelve a la pagina los datos para mostrar el boton de pago.
            return $this->response->setJSON([
                'preferenceId'     => $preference->id,
                'initPoint'        => $preference->init_point,
                'sandboxInitPoint' => $preference->sandbox_init_point,
            ]);
        } catch (MPApiException $exception) {
            // Mercado Pago rechazo el pedido: se anota el error y se avisa.
            log_message('error', 'Mercado Pago API error: {status} {content}', [
                'status'  => $exception->getStatusCode(),
                'content' => json_encode($exception->getApiResponse()->getContent()),
            ]);

            return $this->jsonError('Mercado Pago rechazó la preferencia. Revisa credenciales y datos del pedido.', 502);
        } catch (Throwable $exception) {
            // No hubo conexion con Mercado Pago.
            log_message('error', 'Mercado Pago SDK error: {message}', ['message' => $exception->getMessage()]);

            return $this->jsonError('No se pudo conectar con Mercado Pago.', 502);
        }
    }

    // Regreso desde Mercado Pago (back_urls): las tres terminan en lo mismo.
    public function mercadoPagoSuccess(): ResponseInterface
    {
        return $this->regresoDeMercadoPago();
    }

    // Regreso cuando el pago fallo.
    public function mercadoPagoFailure(): ResponseInterface
    {
        return $this->regresoDeMercadoPago();
    }

    // Regreso cuando el pago quedo pendiente.
    public function mercadoPagoPending(): ResponseInterface
    {
        return $this->regresoDeMercadoPago();
    }

    /**
     * Avisos (webhooks) de Mercado Pago: https://TU-DOMINIO/checkout/mercadopago/webhook
     *
     * Una notificacion falsa solo provoca que se vuelva a consultar el pago a la API; si
     * ademas se define mercadopago.webhookSecret se valida la firma.
     */
    public function mercadoPagoWebhook(): ResponseInterface
    {
        // Lee el aviso (puede llegar como JSON o en la direccion).
        try {
            $body = $this->request->is('json') ? ($this->request->getJSON(true) ?? []) : [];
        } catch (Throwable) {
            $body = [];
        }

        // Que tipo de aviso es y de que pago habla.
        $type = (string) ($body['type'] ?? $this->request->getGet('type') ?? $this->request->getGet('topic') ?? '');
        $paymentId = (string) ($body['data']['id'] ?? $this->request->getGet('data_id') ?? $this->request->getGet('id') ?? '');

        // Solo interesan los avisos de pagos.
        if ($type !== 'payment' || ! ctype_digit($paymentId)) {
            // Otros eventos (merchant_order, etc.) no se usan: se confirman para que no se reintenten.
            return $this->response->setStatusCode(200)->setJSON(['received' => true]);
        }

        // Comprueba que el aviso venga de verdad de Mercado Pago.
        if (! $this->firmaValida($paymentId)) {
            return $this->response->setStatusCode(401)->setJSON(['received' => false]);
        }

        // Consulta el pago y actualiza el pedido.
        $order = $this->sincronizarPago($paymentId);

        // Si falla la consulta a la API respondemos 500 para que Mercado Pago reintente.
        return $this->response
            ->setStatusCode($order === false ? 500 : 200)
            ->setJSON(['received' => $order !== false]);
    }

    // Revisa los datos del pedido: devuelve el pedido limpio o el error que encontro.
    /** @return array{0: ?array{cantidad: int, email: string}, 1: ?string, 2: int} pedido, error y status */
    private function validarPedido(array $payload): array
    {
        // Saca del pedido el producto, la cantidad y el correo.
        $producto = is_array($payload['product'] ?? null) ? $payload['product'] : [];
        $cantidad = (int) ($producto['quantity'] ?? 0);
        $email = trim((string) (is_array($payload['customer'] ?? null) ? ($payload['customer']['email'] ?? '') : ''));

        // Devuelve el primer problema que encuentre; si no hay ninguno, el pedido.
        return match (true) {
            ($payload['paymentMethod'] ?? 'mercadopago') !== 'mercadopago'           => [null, 'Método de pago inválido.', 400],
            $this->tienda->precio() === null                                        => [null, 'Configura commerce.unitPrice con un precio mayor a cero.', 503],
            (string) ($producto['sku'] ?? '') !== $this->tienda->productSku         => [null, 'Producto inválido.', 400],
            $cantidad < 1 || $cantidad > $this->tienda->cantidadMaxima()            => [null, 'Cantidad inválida.', 400],
            ! filter_var($email, FILTER_VALIDATE_EMAIL)                              => [null, 'Email inválido.', 400],
            default                                                                 => [['cantidad' => $cantidad, 'email' => $email], null, 200],
        };
    }

    // El comprador vuelve de Mercado Pago: se consulta el pago y se le muestra el resultado.
    private function regresoDeMercadoPago(): ResponseInterface
    {
        // Numero de pago que Mercado Pago agrega a la direccion.
        $paymentId = (string) ($this->request->getGet('payment_id') ?? $this->request->getGet('collection_id') ?? '');

        if (! ctype_digit($paymentId)) {
            // El comprador volvio sin pagar (p. ej. "Volver al sitio").
            return $this->volverAlCheckout('info', 'No se completó el pago. Podés intentarlo nuevamente cuando quieras.');
        }

        // Consulta el estado real del pago.
        $order = $this->sincronizarPago($paymentId);

        // Todavia no se pudo confirmar.
        if (! is_array($order)) {
            return $this->volverAlCheckout('info', 'Recibimos tu pago y lo estamos verificando con Mercado Pago. La confirmación puede demorar unos minutos.');
        }

        // Mensaje segun como termino el pago.
        return match ($order['estado']) {
            'aprobado'              => $this->volverAlCheckout('success', 'Pago aprobado. Tu pedido ' . $order['referencia'] . ' está confirmado.'),
            'rechazado', 'cancelado' => $this->volverAlCheckout('error', 'El pago no fue aprobado. Podés intentarlo nuevamente con otro medio.'),
            default                 => $this->volverAlCheckout('info', 'Tu pago quedó pendiente de acreditación. Pedido ' . $order['referencia'] . '.'),
        };
    }

    /**
     * Consulta el pago a la API de Mercado Pago y actualiza el pedido correspondiente.
     *
     * @return array|false|null El pedido actualizado; null si el pago no es de ningun pedido; false si fallo la API.
     */
    private function sincronizarPago(string $paymentId): array|false|null
    {
        // Sin la clave de Mercado Pago no se puede consultar.
        if (trim($this->mp->accessToken) === '') {
            return false;
        }

        $this->configurarSdk();

        // Pide los datos del pago a Mercado Pago.
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

        // Pasa los datos del pago al modelo, que actualiza el pedido.
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

    /** Valida el header x-signature ("ts=...,v1=...") segun la documentacion de Mercado Pago. */
    private function firmaValida(string $paymentId): bool
    {
        // Sin clave secreta configurada no se revisa la firma.
        $secret = trim($this->mp->webhookSecret);
        if ($secret === '') {
            return true;
        }

        // Separa el header en sus partes (ts y v1).
        $partes = [];
        foreach (explode(',', $this->request->getHeaderLine('x-signature')) as $parte) {
            [$clave, $valor] = array_pad(explode('=', trim($parte), 2), 2, '');
            $partes[$clave] = $valor;
        }

        // Si falta alguna parte, la firma no sirve.
        if (empty($partes['ts']) || empty($partes['v1'])) {
            return false;
        }

        // Arma el texto que firmo Mercado Pago y compara las dos firmas.
        $manifest = 'id:' . strtolower($paymentId) . ';';
        $requestId = $this->request->getHeaderLine('x-request-id');
        if ($requestId !== '') {
            $manifest .= 'request-id:' . $requestId . ';';
        }
        $manifest .= 'ts:' . $partes['ts'] . ';';

        return hash_equals(hash_hmac('sha256', $manifest, $secret), $partes['v1']);
    }

    // Le pasa la clave a la libreria de Mercado Pago.
    private function configurarSdk(): void
    {
        MercadoPagoConfig::setAccessToken(trim($this->mp->accessToken));

        // En la PC de pruebas se avisa que es un entorno local.
        if ($this->mp->esLocal()) {
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        }
    }

    // Arma los datos del cobro que se mandan a Mercado Pago.
    private function preferencia(int $cantidad, string $email, string $referencia): array
    {
        // Nombre que aparece en el resumen de la tarjeta (solo letras y numeros).
        $descriptor = preg_replace('/[^A-Z0-9 ]/', '', strtoupper($this->mp->statementDescriptor ?: $this->tienda->productName)) ?: 'AQUACONTROL';

        // Producto, comprador, direcciones de regreso y datos para reconocer el pedido.
        $preferencia = [
            'items' => [[
                'id'          => $this->tienda->productSku,
                'title'       => $this->tienda->productName,
                'description' => $this->tienda->productDescription,
                'quantity'    => $cantidad,
                'currency_id' => $this->tienda->moneda(),
                'unit_price'  => $this->tienda->precio(),
            ]],
            'payer'     => ['email' => $email],
            'back_urls' => [
                'success' => base_url('checkout/mercadopago/success'),
                'failure' => base_url('checkout/mercadopago/failure'),
                'pending' => base_url('checkout/mercadopago/pending'),
            ],
            'external_reference'   => $referencia,
            'statement_descriptor' => substr($descriptor, 0, 22),
            'metadata'             => [
                'customer_email'     => $email,
                'external_reference' => $referencia,
                'product_sku'        => $this->tienda->productSku,
                'quantity'           => $cantidad,
            ],
        ];

        // Volver sola a la pagina cuando el pago se aprueba.
        if ($this->mp->usarAutoReturn()) {
            $preferencia['auto_return'] = 'approved';
        }

        // Limite de cuotas.
        if ($this->mp->installments > 0) {
            $preferencia['payment_methods'] = ['installments' => min($this->mp->installments, 12), 'default_installments' => 1];
        }

        // Mercado Pago solo puede avisar a una URL publica: por defecto se usa el webhook
        // propio cuando el sitio corre con HTTPS; en local hay que definir notificationUrl.
        $notificationUrl = trim($this->mp->notificationUrl);
        if ($notificationUrl === '' && str_starts_with(base_url(), 'https://')) {
            $notificationUrl = base_url('checkout/mercadopago/webhook');
        }
        if ($notificationUrl !== '') {
            $preferencia['notification_url'] = $notificationUrl;
        }

        return $preferencia;
    }

    // Respuesta de error en formato JSON.
    private function jsonError(string $message, int $status): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['success' => false, 'message' => $message]);
    }

    // Vuelve a la seccion de compra de la portada con un mensaje.
    private function volverAlCheckout(string $tipo, string $mensaje): ResponseInterface
    {
        session()->setFlashdata($tipo, $mensaje);

        return redirect()->to(base_url('/#checkout'));
    }
}
