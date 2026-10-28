<?php

namespace App\Libraries;

use Config\WebPush as ConfigWebPush;
use RuntimeException;

/**
 * Manda notificaciones "push" al celular o a la computadora (Web Push), sin librerias extra.
 *
 * Cada navegador que acepto las notificaciones tiene una direccion (endpoint) en el
 * servicio de su fabricante (Google, Mozilla, Microsoft, Apple) y dos claves propias.
 * Para mandarle un aviso:
 *   1. Se cifra el mensaje con sus claves (RFC 8291, "aes128gcm"): solo ese navegador lo lee.
 *   2. Se firma el pedido con las claves VAPID de esta pagina (RFC 8292): asi el servicio
 *      sabe que viene de nosotros.
 *   3. Se manda al endpoint; el servicio se lo entrega al navegador aunque este cerrado.
 */
class WebPush
{
    /** Comienzo fijo de una clave publica P-256 en formato DER (lo que pide OpenSSL). */
    private const DER_CLAVE_PUBLICA = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public function __construct(private ?ConfigWebPush $config = null)
    {
        $this->config ??= config(ConfigWebPush::class);
    }

    /** Si ya se cargaron las claves VAPID en .env. */
    public function configurado(): bool
    {
        return $this->config->clavePublica !== '' && $this->config->clavePrivada !== '';
    }

    /**
     * Manda un aviso a una suscripcion. Devuelve el codigo HTTP del servicio:
     * 201 = entregado; 404 o 410 = la suscripcion ya no existe (hay que borrarla).
     *
     * @param array{endpoint: string, p256dh: string, auth: string} $suscripcion
     */
    public function enviar(array $suscripcion, array $datos): int
    {
        $cuerpo = $this->cifrar(
            json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            self::desdeBase64Url($suscripcion['p256dh']),
            self::desdeBase64Url($suscripcion['auth'])
        );

        // El servicio de push es el "destinatario" de la firma (protocolo + dominio del endpoint).
        $partes = parse_url($suscripcion['endpoint']);
        $destinatario = $partes['scheme'] . '://' . $partes['host'] . (isset($partes['port']) ? ':' . $partes['port'] : '');

        // Un cliente HTTP nuevo para cada aviso (cada uno lleva sus propios headers).
        $respuesta = single_service('curlrequest')->post($suscripcion['endpoint'], [
            'headers' => [
                'Content-Type'     => 'application/octet-stream',
                'Content-Encoding' => 'aes128gcm',
                'TTL'              => '86400',
                'Urgency'          => 'high',
                'Authorization'    => 'vapid t=' . $this->firmaVapid($destinatario) . ', k=' . $this->config->clavePublica,
            ],
            'body'        => $cuerpo,
            'http_errors' => false,
            'timeout'     => 10,
        ]);

        return $respuesta->getStatusCode();
    }

    /**
     * Cifra el mensaje para un navegador (RFC 8291). $clavesPropias y $sal solo se pasan en
     * la prueba con el ejemplo de la norma; normalmente se crean nuevas en cada aviso.
     */
    public function cifrar(string $mensaje, string $publicaNavegador, string $secretoNavegador, ?array $clavesPropias = null, ?string $sal = null): string
    {
        // Claves de un solo uso para este aviso.
        [$privadaPropia, $publicaPropia] = $clavesPropias ?? self::nuevasClaves();
        $sal ??= random_bytes(16);

        // Secreto compartido entre nuestras claves y las del navegador (ECDH).
        $compartido = openssl_pkey_derive(self::clavePublicaPem($publicaNavegador), $privadaPropia, 32);
        if ($compartido === false) {
            throw new RuntimeException('No se pudo calcular el secreto compartido de Web Push.');
        }

        // Derivacion de claves (HKDF con SHA-256), tal como la describe la norma.
        $prkClave = hash_hmac('sha256', $compartido, $secretoNavegador, true);
        $ikm = hash_hmac('sha256', "WebPush: info\0" . $publicaNavegador . $publicaPropia . "\x01", $prkClave, true);
        $prk = hash_hmac('sha256', $ikm, $sal, true);
        $claveContenido = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);

        // Cifra el mensaje (con un 0x02 al final: "ultimo bloque") con AES-128-GCM.
        $cifrado = openssl_encrypt($mensaje . "\x02", 'aes-128-gcm', $claveContenido, OPENSSL_RAW_DATA, $nonce, $etiqueta);

        // Encabezado: sal, tamano de bloque (4096), largo de nuestra clave publica y la clave.
        return $sal . pack('N', 4096) . chr(strlen($publicaPropia)) . $publicaPropia . $cifrado . $etiqueta;
    }

    /** Firma VAPID: un JWT con ES256 que vale 12 horas. */
    private function firmaVapid(string $destinatario): string
    {
        $cabecera = self::aBase64Url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $datos = self::aBase64Url(json_encode([
            'aud' => $destinatario,
            'exp' => time() + 12 * 3600,
            'sub' => $this->config->contacto,
        ], JSON_UNESCAPED_SLASHES));

        $privada = self::clavePrivadaPem(self::desdeBase64Url($this->config->clavePrivada), self::desdeBase64Url($this->config->clavePublica));
        openssl_sign($cabecera . '.' . $datos, $firmaDer, $privada, OPENSSL_ALGO_SHA256);

        return $cabecera . '.' . $datos . '.' . self::aBase64Url(self::firmaDerACruda($firmaDer));
    }

    /** Par de claves P-256 nuevo: [clave privada de OpenSSL, clave publica de 65 bytes]. */
    public static function nuevasClaves(): array
    {
        $opciones = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        // En Windows (XAMPP) OpenSSL no encuentra solo su archivo de configuracion: esta junto a php.exe.
        $cnf = getenv('OPENSSL_CONF') ?: dirname(PHP_BINARY) . '/extras/openssl/openssl.cnf';
        if (is_file($cnf)) {
            $opciones['config'] = $cnf;
        }
        $clave = openssl_pkey_new($opciones);
        if ($clave === false) {
            throw new RuntimeException('OpenSSL no pudo crear claves: ' . openssl_error_string());
        }
        $ec = openssl_pkey_get_details($clave)['ec'];

        return [$clave, "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)];
    }

    /** Claves VAPID nuevas en base64url (publica de 65 bytes y privada de 32), para el .env. */
    public static function nuevasClavesVapid(): array
    {
        [$clave, $publica] = self::nuevasClaves();
        $privada = str_pad(openssl_pkey_get_details($clave)['ec']['d'], 32, "\0", STR_PAD_LEFT);

        return ['publica' => self::aBase64Url($publica), 'privada' => self::aBase64Url($privada)];
    }

    /** Clave publica de 65 bytes -> formato PEM que entiende OpenSSL. */
    private static function clavePublicaPem(string $publica): string
    {
        $der = hex2bin(self::DER_CLAVE_PUBLICA) . $publica;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** Clave privada de 32 bytes (y su publica) -> formato PEM (SEC1) que entiende OpenSSL. */
    public static function clavePrivadaPem(string $privada, string $publica): string
    {
        $der = hex2bin('30770201010420') . $privada . hex2bin('a00a06082a8648ce3d030107a144034200') . $publica;

        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    }

    /** OpenSSL firma en formato DER; JWT pide los dos numeros (r y s) pegados, 32 bytes cada uno. */
    private static function firmaDerACruda(string $der): string
    {
        $pos = 3;
        $largoR = ord($der[$pos]);
        $r = substr($der, $pos + 1, $largoR);
        $pos += 2 + $largoR;
        $largoS = ord($der[$pos]);
        $s = substr($der, $pos + 1, $largoS);

        $ajustar = static fn (string $n): string => str_pad(ltrim($n, "\0"), 32, "\0", STR_PAD_LEFT);

        return $ajustar($r) . $ajustar($s);
    }

    // Base64 "para direcciones" (sin + / =), el que usan Web Push y JWT.
    public static function aBase64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function desdeBase64Url(string $texto): string
    {
        return (string) base64_decode(strtr($texto, '-_', '+/'), true);
    }
}
