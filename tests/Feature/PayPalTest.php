<?php

namespace Tests\Feature;

use App\Models\Entrada;
use App\Models\Orden;
use App\Services\PagoService;
use App\Services\ReservaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PayPalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.paypal.client_id' => 'cliente-prueba',
            'services.paypal.secret' => 'secreto-prueba',
            'services.paypal.mode' => 'sandbox',
            'services.paypal.webhook_id' => 'WH-PRUEBA',
        ]);
        Mail::fake();
    }

    private function ordenPendiente(int $asientos = 2): Orden
    {
        $funcion = $this->crearFuncion();

        return app(ReservaService::class)->reservar($funcion, $this->asientos($funcion, $asientos), $this->datosComprador(), null);
    }

    private function captura(Orden $orden, string $monto, string $estado = 'COMPLETED', ?string $customId = null): array
    {
        return [
            'id' => 'PAYPAL-ORDEN-1',
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'payments' => ['captures' => [[
                    'id' => 'CAPTURA123',
                    'status' => $estado,
                    'custom_id' => $customId ?? $orden->uuid,
                    'amount' => ['currency_code' => 'USD', 'value' => $monto],
                ]]],
            ]],
        ];
    }

    private function fakePayPal(array $captura, int $estadoCaptura = 201): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'TOKEN', 'expires_in' => 32000]),
            'api-m.sandbox.paypal.com/v2/checkout/orders/*/capture' => Http::response($captura, $estadoCaptura),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDEN-1',
                'status' => 'PAYER_ACTION_REQUIRED',
                'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDEN-1']],
            ], 201),
        ]);
    }

    public function test_flujo_completo_de_pago_con_paypal(): void
    {
        $orden = $this->ordenPendiente();
        $this->fakePayPal($this->captura($orden, '25.00'));

        $this->conOrdenEnSesion($orden->uuid)->post(route('ordenes.pagar-paypal', $orden))
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDEN-1');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v2/checkout/orders')
            && $r->hasHeader('PayPal-Request-Id', 'crear-'.$orden->uuid)
            && $r['purchase_units'][0]['amount']['value'] === '25.00'
            && $r['purchase_units'][0]['custom_id'] === $orden->uuid);

        $respuesta = $this->conOrdenEnSesion($orden->uuid)->get(route('ordenes.paypal.retorno', $orden).'?token=PAYPAL-ORDEN-1');

        $orden->refresh();
        $this->assertSame(Orden::PAGADA, $orden->estado);
        $this->assertSame('paypal', $orden->metodo_pago);
        $this->assertSame('CAPTURA123', $orden->paypal_capture_id);
        $respuesta->assertRedirect(route('entradas.show', $orden->codigo));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/capture') && $r->hasHeader('PayPal-Request-Id', 'capturar-'.$orden->uuid));
    }

    public function test_con_paypal_configurado_no_existe_el_pago_simulado(): void
    {
        $orden = $this->ordenPendiente();

        $this->conOrdenEnSesion($orden->uuid)->post(route('ordenes.pagar-demo', $orden))->assertSessionHasErrors('pago');
        $this->assertSame(Orden::PENDIENTE, $orden->fresh()->estado);
        $this->get('/')->assertDontSee('Modo demostración');
    }

    public function test_no_marca_pagada_si_el_monto_no_coincide(): void
    {
        $orden = $this->ordenPendiente();
        $this->fakePayPal($this->captura($orden, '0.01'));
        $orden->update(['paypal_order_id' => 'PAYPAL-ORDEN-1']);

        $this->conOrdenEnSesion($orden->uuid)->get(route('ordenes.paypal.retorno', $orden).'?token=PAYPAL-ORDEN-1');

        $this->assertSame(Orden::PROCESANDO, $orden->fresh()->estado);
        $this->assertNull($orden->fresh()->codigo);
    }

    public function test_no_marca_pagada_si_la_captura_es_de_otra_orden(): void
    {
        $orden = $this->ordenPendiente();
        $this->fakePayPal($this->captura($orden, '25.00', customId: 'otra-orden'));
        $orden->update(['paypal_order_id' => 'PAYPAL-ORDEN-1']);

        $this->conOrdenEnSesion($orden->uuid)->get(route('ordenes.paypal.retorno', $orden).'?token=PAYPAL-ORDEN-1');

        $this->assertNotSame(Orden::PAGADA, $orden->fresh()->estado);
    }

    public function test_token_de_paypal_ajeno_es_rechazado(): void
    {
        $orden = $this->ordenPendiente();
        $orden->update(['paypal_order_id' => 'PAYPAL-ORDEN-1']);
        Http::fake();

        $this->conOrdenEnSesion($orden->uuid)->get(route('ordenes.paypal.retorno', $orden).'?token=OTRA-ORDEN-9')
            ->assertSessionHasErrors('pago');
        Http::assertNothingSent();
        $this->assertSame(Orden::PENDIENTE, $orden->fresh()->estado);
    }

    public function test_pago_rechazado_permite_reintentar(): void
    {
        $orden = $this->ordenPendiente();
        $orden->update(['paypal_order_id' => 'PAYPAL-ORDEN-1']);
        $this->fakePayPal(['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'INSTRUMENT_DECLINED']]], 422);

        $this->conOrdenEnSesion($orden->uuid)->get(route('ordenes.paypal.retorno', $orden).'?token=PAYPAL-ORDEN-1')
            ->assertSessionHasErrors('pago');

        $this->assertSame(Orden::PENDIENTE, $orden->fresh()->estado);
        $this->assertSame(2, Entrada::count());
    }

    public function test_si_paypal_no_responde_queda_procesando_y_se_reconcilia(): void
    {
        $orden = $this->ordenPendiente();
        $orden->update(['paypal_order_id' => 'PAYPAL-ORDEN-1']);
        $this->fakePayPal(['name' => 'INTERNAL_SERVER_ERROR'], 500);

        $this->conOrdenEnSesion($orden->uuid)->get(route('ordenes.paypal.retorno', $orden).'?token=PAYPAL-ORDEN-1');
        $this->assertSame(Orden::PROCESANDO, $orden->fresh()->estado);

        // La primera consulta de la tarea programada también falla; la segunda confirma el cobro.
        Http::fake([
            'api-m.sandbox.paypal.com/v2/checkout/orders/PAYPAL-ORDEN-1' => Http::sequence()
                ->push(['name' => 'INTERNAL_SERVER_ERROR'], 500)
                ->push($this->captura($orden, '25.00')),
        ]);

        // Aunque pase el plazo de la reserva, los asientos no se liberan mientras se confirma.
        $this->travel(20)->minutes();
        $this->artisan('teatro:mantenimiento')->assertSuccessful();
        $this->assertSame(Orden::PROCESANDO, $orden->fresh()->estado);
        $this->assertSame(2, Entrada::count());

        $this->assertSame(1, app(PagoService::class)->reconciliar());
        $this->assertSame(Orden::PAGADA, $orden->fresh()->estado);
    }

    public function test_aprobacion_tras_vencer_la_reserva_no_cobra(): void
    {
        $orden = $this->ordenPendiente();
        $orden->update(['paypal_order_id' => 'PAYPAL-ORDEN-1']);
        Http::fake();
        $this->travel(11)->minutes();

        $this->conOrdenEnSesion($orden->uuid)->get(route('ordenes.paypal.retorno', $orden).'?token=PAYPAL-ORDEN-1');

        Http::assertNothingSent();
        $this->assertSame(Orden::EXPIRADA, $orden->fresh()->estado);
        $this->assertSame(0, Entrada::count());
    }

    public function test_url_de_aprobacion_que_no_es_de_paypal_se_rechaza(): void
    {
        $orden = $this->ordenPendiente();
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'TOKEN', 'expires_in' => 32000]),
            'api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response([
                'id' => 'PAYPAL-ORDEN-1',
                'links' => [['rel' => 'payer-action', 'href' => 'https://atacante.example/pagar']],
            ], 201),
        ]);

        $this->conOrdenEnSesion($orden->uuid)->post(route('ordenes.pagar-paypal', $orden))
            ->assertRedirect(route('ordenes.show', $orden))
            ->assertSessionHasErrors('pago');
    }

    public function test_webhook_sin_firma_valida_es_rechazado(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'TOKEN', 'expires_in' => 32000]),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'FAILURE']),
        ]);

        $this->call('POST', route('webhooks.paypal'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.com/cert',
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'id',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'firma',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(),
        ], json_encode(['event_type' => 'PAYMENT.CAPTURE.REFUNDED']))->assertStatus(400);
    }

    public function test_webhook_de_reembolso_libera_los_asientos(): void
    {
        $orden = $this->ordenPendiente();
        $orden->update(['estado' => Orden::PAGADA, 'paypal_capture_id' => 'CAPTURA123', 'codigo' => 'ABCDEFGHJKMNPQRS']);
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'TOKEN', 'expires_in' => 32000]),
            'api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ]);

        $evento = ['event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => ['id' => 'REEMB1', 'links' => [
            ['rel' => 'up', 'href' => 'https://api.sandbox.paypal.com/v2/payments/captures/CAPTURA123'],
        ]]];
        $this->call('POST', route('webhooks.paypal'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
            'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.com/cert',
            'HTTP_PAYPAL_TRANSMISSION_ID' => 'id',
            'HTTP_PAYPAL_TRANSMISSION_SIG' => 'firma',
            'HTTP_PAYPAL_TRANSMISSION_TIME' => now()->toIso8601String(),
        ], json_encode($evento))->assertOk();

        $this->assertSame(Orden::REEMBOLSADA, $orden->fresh()->estado);
        $this->assertSame(0, Entrada::count());
    }
}
