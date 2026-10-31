<?php

namespace Tests\Feature;

use App\Mail\PruebaCorreo;
use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\Orden;
use App\Services\PagoService;
use App\Services\ReservaService;
use App\Support\Ajustes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ConfiguracionTest extends TestCase
{
    private function tokenValido(): void
    {
        Http::fake(['api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'TOKEN', 'expires_in' => 32000])]);
    }

    private function activarPayPal(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->put(route('admin.configuracion.pagos'), $extra + [
            'modo' => 'paypal',
            'paypal_mode' => 'sandbox',
            'paypal_client_id' => 'AbC123-cliente',
            'paypal_secret' => 'SeCrEt-456',
        ]);
    }

    public function test_solo_administradores(): void
    {
        $this->get(route('admin.configuracion.edit'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.configuracion.pagos'), ['modo' => 'paypal'])->assertRedirect(route('admin.login'));
        $this->put(route('admin.configuracion.correo'), ['mailer' => 'smtp'])->assertRedirect(route('admin.login'));
    }

    public function test_la_pagina_muestra_el_modo_actual(): void
    {
        $this->actingAs($this->admin())->get(route('admin.configuracion.edit'))
            ->assertOk()
            ->assertSee('Demostración · pagos simulados')
            ->assertSee(route('webhooks.paypal'));
    }

    public function test_no_activa_paypal_sin_credenciales(): void
    {
        $this->activarPayPal(['paypal_client_id' => '', 'paypal_secret' => ''])->assertSessionHasErrors('modo');

        $this->assertTrue(app(PagoService::class)->modoDemo());
    }

    public function test_no_activa_paypal_con_credenciales_rechazadas(): void
    {
        Http::fake(['api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['error' => 'invalid_client'], 401)]);

        $this->activarPayPal()->assertSessionHasErrors('paypal_client_id');

        $this->assertSame(0, Ajuste::count());
        $this->assertTrue(app(PagoService::class)->modoDemo());
    }

    public function test_activa_paypal_y_guarda_el_secreto_cifrado(): void
    {
        $this->tokenValido();

        $this->activarPayPal()->assertRedirect(route('admin.configuracion.edit'))->assertSessionHas('exito');

        $this->assertFalse(app(PagoService::class)->modoDemo());
        $this->assertSame('AbC123-cliente', config('services.paypal.client_id'));
        $this->assertSame('SeCrEt-456', config('services.paypal.secret'));
        $guardado = Ajuste::find('paypal.secret')->valor;
        $this->assertStringNotContainsString('SeCrEt-456', $guardado);
        $this->assertDatabaseHas('auditoria', ['accion' => 'config_pagos']);

        // El secreto nunca vuelve al navegador.
        $this->actingAs($this->admin())->get(route('admin.configuracion.edit'))
            ->assertOk()
            ->assertDontSee('SeCrEt-456')
            ->assertSee('PayPal Sandbox · pruebas');
        $this->get('/')->assertDontSee('Modo demostración');
    }

    public function test_con_paypal_activo_no_existe_el_pago_simulado(): void
    {
        $this->tokenValido();
        $this->activarPayPal();
        $funcion = $this->crearFuncion();
        $orden = app(ReservaService::class)->reservar($funcion, $this->asientos($funcion, 1), $this->datosComprador(), null);

        $this->conOrdenEnSesion($orden->uuid)->post(route('ordenes.pagar-demo', $orden))->assertSessionHasErrors('pago');
        $this->assertSame(Orden::PENDIENTE, $orden->fresh()->estado);
    }

    public function test_dejar_el_secreto_vacio_conserva_el_guardado(): void
    {
        $this->tokenValido();
        $this->activarPayPal();

        $this->activarPayPal(['paypal_secret' => '', 'paypal_webhook_id' => 'WH-123ABC'])->assertSessionHasNoErrors();

        Ajustes::olvidar();
        $this->assertSame('SeCrEt-456', Ajustes::obtener('paypal.secret'));
        $this->assertSame('WH-123ABC', Ajustes::obtener('paypal.webhook_id'));
    }

    public function test_volver_a_demo_conserva_las_credenciales(): void
    {
        $this->tokenValido();
        $this->activarPayPal();

        $this->activarPayPal(['modo' => 'demo', 'paypal_secret' => ''])->assertSessionHasNoErrors();

        $pagos = app(PagoService::class);
        $this->assertTrue($pagos->modoDemo());
        // Los pagos ya iniciados en PayPal pueden terminar.
        $this->assertTrue($pagos->paypalDisponible());
        $this->get('/')->assertSee('Modo demostración');
    }

    public function test_borrar_credenciales(): void
    {
        $this->tokenValido();
        $this->activarPayPal();

        $this->activarPayPal(['modo' => 'demo', 'borrar_credenciales' => '1'])->assertSessionHasNoErrors();

        $this->assertNull(Ajuste::find('paypal.secret'));
        $this->assertTrue(app(PagoService::class)->modoDemo());
    }

    public function test_probar_conexion_con_paypal(): void
    {
        $this->tokenValido();
        $this->activarPayPal();

        $this->actingAs($this->admin())->post(route('admin.configuracion.pagos.probar'))->assertSessionHas('exito');
    }

    public function test_configurar_smtp_y_enviar_prueba(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.configuracion.correo'), [
            'mailer' => 'smtp',
            'host' => 'smtp.ejemplo.com',
            'port' => '587',
            'cifrado' => 'tls',
            'usuario' => 'ventas@ejemplo.com',
            'password' => 'clave-smtp-secreta',
            'remitente' => 'Ventas@Ejemplo.com',
            'remitente_nombre' => 'Teatro Nacional',
        ])->assertRedirect(route('admin.configuracion.edit'))->assertSessionHasNoErrors();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.ejemplo.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('clave-smtp-secreta', config('mail.mailers.smtp.password'));
        $this->assertSame('ventas@ejemplo.com', config('mail.from.address'));
        $this->assertStringNotContainsString('clave-smtp-secreta', Ajuste::find('correo.password')->valor);

        $this->actingAs($admin)->get(route('admin.configuracion.edit'))->assertDontSee('clave-smtp-secreta');

        $this->actingAs($admin)->post(route('admin.configuracion.correo.probar'), ['destino' => 'yo@ejemplo.com'])->assertSessionHas('exito');
        Mail::assertSent(PruebaCorreo::class, fn ($m) => $m->hasTo('yo@ejemplo.com'));
        $this->assertSame(2, Auditoria::where('accion', 'config_correo')->count());
    }

    public function test_valida_el_servidor_smtp(): void
    {
        $this->actingAs($this->admin())->put(route('admin.configuracion.correo'), [
            'mailer' => 'smtp',
            'host' => 'http://malo/;rm',
            'port' => '3306',
            'cifrado' => 'tls',
            'remitente' => 'no-es-correo',
        ])->assertSessionHasErrors(['host', 'port', 'remitente']);

        $this->assertSame(0, Ajuste::count());
    }

    public function test_prueba_de_correo_con_envio_desactivado(): void
    {
        $this->actingAs($this->admin())->post(route('admin.configuracion.correo.probar'), ['destino' => 'yo@ejemplo.com'])
            ->assertSessionHasErrors('destino');
    }
}
