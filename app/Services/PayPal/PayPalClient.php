<?php

namespace App\Services\PayPal;

use App\Models\Orden;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cliente mínimo de la API REST de PayPal (Orders v2).
 *
 * Todas las operaciones que cambian estado envían PayPal-Request-Id, de modo
 * que repetir una llamada (reintento, doble clic, caída de red) nunca cobra
 * ni reembolsa dos veces.
 */
class PayPalClient
{
    public function configurado(): bool
    {
        return filled(config('services.paypal.client_id')) && filled(config('services.paypal.secret'));
    }

    public function esProduccion(): bool
    {
        return config('services.paypal.mode') === 'live';
    }

    /**
     * Crea la orden en PayPal y devuelve su id y la URL a la que se envía al comprador.
     *
     * @return array{id: string, url: string}
     */
    public function crearOrden(Orden $orden, string $urlRetorno, string $urlCancelacion): array
    {
        $orden->loadMissing('funcion.obra', 'entradas');
        $titulo = $orden->funcion->obra->titulo;
        $cantidad = $orden->entradas->count();

        $respuesta = $this->enviar('post', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $orden->uuid,
                'custom_id' => $orden->uuid,
                'invoice_id' => $orden->uuid,
                'description' => Str::limit('Entradas: '.$titulo, 120, ''),
                'amount' => [
                    'currency_code' => $orden->moneda,
                    'value' => (string) $orden->total,
                    'breakdown' => [
                        'item_total' => ['currency_code' => $orden->moneda, 'value' => (string) $orden->total],
                    ],
                ],
                'items' => [[
                    'name' => Str::limit($titulo, 120, ''),
                    'quantity' => (string) $cantidad,
                    'unit_amount' => ['currency_code' => $orden->moneda, 'value' => (string) $orden->funcion->precio],
                    'category' => 'DIGITAL_GOODS',
                ]],
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'brand_name' => Str::limit((string) config('app.name'), 120, ''),
                        'locale' => 'es-ES',
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'PAY_NOW',
                        'return_url' => $urlRetorno,
                        'cancel_url' => $urlCancelacion,
                    ],
                ],
            ],
        ], 'crear-'.$orden->uuid);

        $id = $respuesta['id'] ?? null;
        $url = collect($respuesta['links'] ?? [])
            ->first(fn ($l) => in_array($l['rel'] ?? null, ['payer-action', 'approve'], true))['href'] ?? null;

        if (! is_string($id) || ! is_string($url) || ! $this->esUrlDePayPal($url)) {
            throw new PayPalException('Respuesta inesperada de PayPal al crear la orden.');
        }

        return ['id' => $id, 'url' => $url];
    }

    /**
     * Captura (cobra) una orden aprobada por el comprador. Si ya estaba
     * capturada, devuelve la orden tal como está en PayPal.
     */
    public function capturar(string $paypalOrderId, string $uuidOrden): array
    {
        $this->validarId($paypalOrderId);

        try {
            return $this->enviar('post', '/v2/checkout/orders/'.$paypalOrderId.'/capture', null, 'capturar-'.$uuidOrden);
        } catch (PayPalException $e) {
            if ($e->problema === 'ORDER_ALREADY_CAPTURED') {
                return $this->obtenerOrden($paypalOrderId);
            }
            throw $e;
        }
    }

    public function obtenerOrden(string $paypalOrderId): array
    {
        $this->validarId($paypalOrderId);

        return $this->enviar('get', '/v2/checkout/orders/'.$paypalOrderId);
    }

    public function reembolsar(string $captureId, string $monto, string $moneda, string $uuidOrden): array
    {
        $this->validarId($captureId);

        return $this->enviar('post', '/v2/payments/captures/'.$captureId.'/refund', [
            'amount' => ['value' => $monto, 'currency_code' => $moneda],
            'note_to_payer' => 'Reembolso de entradas de teatro',
        ], 'reembolso-'.$uuidOrden);
    }

    /**
     * Verifica con PayPal que un webhook es auténtico. El cuerpo se envía tal
     * como llegó, sin decodificarlo, porque la firma se calcula sobre él.
     *
     * @param  array<string, string|null>  $cabeceras
     */
    public function verificarWebhook(array $cabeceras, string $cuerpo): bool
    {
        $webhookId = config('services.paypal.webhook_id');
        if (! $this->configurado() || blank($webhookId) || json_decode($cuerpo) === null) {
            return false;
        }

        foreach (['auth_algo', 'cert_url', 'transmission_id', 'transmission_sig', 'transmission_time'] as $campo) {
            if (blank($cabeceras[$campo] ?? null)) {
                return false;
            }
        }

        $carga = '{'
            .'"auth_algo":'.json_encode($cabeceras['auth_algo']).','
            .'"cert_url":'.json_encode($cabeceras['cert_url']).','
            .'"transmission_id":'.json_encode($cabeceras['transmission_id']).','
            .'"transmission_sig":'.json_encode($cabeceras['transmission_sig']).','
            .'"transmission_time":'.json_encode($cabeceras['transmission_time']).','
            .'"webhook_id":'.json_encode($webhookId).','
            .'"webhook_event":'.$cuerpo
            .'}';

        try {
            $respuesta = $this->cliente()
                ->withBody($carga, 'application/json')
                ->post('/v1/notifications/verify-webhook-signature');
        } catch (ConnectionException) {
            return false;
        }

        return $respuesta->successful() && $respuesta->json('verification_status') === 'SUCCESS';
    }

    public function esUrlDePayPal(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return str_starts_with($url, 'https://')
            && in_array($host, ['www.paypal.com', 'www.sandbox.paypal.com'], true);
    }

    private function enviar(string $metodo, string $ruta, ?array $datos = null, ?string $idPeticion = null): array
    {
        $peticion = $this->cliente();
        if ($idPeticion !== null) {
            $peticion = $peticion->withHeaders(['PayPal-Request-Id' => $idPeticion, 'Prefer' => 'return=representation']);
        }

        try {
            $respuesta = $metodo === 'get'
                ? $peticion->get($ruta)
                : ($datos === null ? $peticion->withBody('{}', 'application/json')->post($ruta) : $peticion->post($ruta, $datos));
        } catch (ConnectionException $e) {
            throw new PayPalException('No se pudo conectar con PayPal.', null, null, $e);
        }

        if ($respuesta->failed()) {
            throw $this->error($respuesta);
        }

        return $respuesta->json() ?? [];
    }

    private function error(Response $respuesta): PayPalException
    {
        $problema = $respuesta->json('details.0.issue') ?? $respuesta->json('name');

        return new PayPalException(
            sprintf('PayPal respondió %d (%s, debug_id %s).', $respuesta->status(), $problema ?? 'sin detalle', $respuesta->json('debug_id') ?? '-'),
            $respuesta->status(),
            is_string($problema) ? $problema : null,
        );
    }

    private function cliente(): PendingRequest
    {
        return Http::baseUrl($this->urlBase())
            ->withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30);
    }

    private function token(): string
    {
        $id = (string) config('services.paypal.client_id');
        $secreto = (string) config('services.paypal.secret');
        $modo = (string) config('services.paypal.mode');
        $clave = 'paypal.token.'.hash('sha256', $id.'|'.$secreto.'|'.$modo);

        $token = Cache::get($clave);
        if (is_string($token)) {
            return $token;
        }

        [$token, $segundos] = $this->solicitarToken($id, $secreto, $modo);
        Cache::put($clave, $token, $segundos);

        return $token;
    }

    /**
     * Comprueba con PayPal que unas credenciales son válidas, sin guardarlas.
     *
     * @throws PayPalException
     */
    public function verificarCredenciales(string $id, string $secreto, string $modo): void
    {
        $this->solicitarToken($id, $secreto, $modo);
    }

    /**
     * @return array{0: string, 1: int} Token y segundos de validez a usar en caché.
     */
    private function solicitarToken(string $id, string $secreto, string $modo): array
    {
        try {
            $respuesta = Http::baseUrl($this->urlBase($modo))
                ->withBasicAuth($id, $secreto)
                ->asForm()
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(20)
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException $e) {
            throw new PayPalException('No se pudo conectar con PayPal.', null, null, $e);
        }

        $token = $respuesta->json('access_token');
        if ($respuesta->failed() || ! is_string($token)) {
            throw new PayPalException('PayPal rechazó las credenciales: revise el Client ID, el Secret y el entorno (Sandbox o Live).', $respuesta->status());
        }

        return [$token, max(60, (int) $respuesta->json('expires_in', 3600) - 300)];
    }

    private function urlBase(?string $modo = null): string
    {
        return ($modo ?? config('services.paypal.mode')) === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function validarId(string $id): void
    {
        if (preg_match('/^[A-Z0-9-]{5,64}$/', $id) !== 1) {
            throw new PayPalException('Identificador de PayPal inválido.');
        }
    }
}
