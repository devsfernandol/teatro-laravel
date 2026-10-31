<?php

namespace Tests\Feature;

use App\Mail\EntradasCompradas;
use App\Models\Orden;
use App\Services\ReservaService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PagoDemoTest extends TestCase
{
    public function test_pago_simulado_emite_las_entradas(): void
    {
        Mail::fake();
        $funcion = $this->crearFuncion();
        $orden = app(ReservaService::class)->reservar($funcion, $this->asientos($funcion, 2), $this->datosComprador(), null);

        $respuesta = $this->conOrdenEnSesion($orden->uuid)->post(route('ordenes.pagar-demo', $orden));

        $orden->refresh();
        $this->assertSame(Orden::PAGADA, $orden->estado);
        $this->assertSame('demo', $orden->metodo_pago);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{16}$/', $orden->codigo);
        $respuesta->assertRedirect(route('entradas.show', $orden->codigo));
        Mail::assertQueued(EntradasCompradas::class, fn ($m) => $m->hasTo('ana@ejemplo.com'));

        $this->get(route('entradas.show', $orden->codigo))
            ->assertOk()
            ->assertSee('Compra realizada con éxito')
            ->assertSee('<svg', false)
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_pagar_dos_veces_no_duplica_nada(): void
    {
        Mail::fake();
        $funcion = $this->crearFuncion();
        $orden = app(ReservaService::class)->reservar($funcion, $this->asientos($funcion, 1), $this->datosComprador(), null);

        $this->conOrdenEnSesion($orden->uuid)->post(route('ordenes.pagar-demo', $orden));
        $codigo = $orden->fresh()->codigo;
        $this->conOrdenEnSesion($orden->uuid)->post(route('ordenes.pagar-demo', $orden))->assertRedirect(route('entradas.show', $codigo));

        $this->assertSame($codigo, $orden->fresh()->codigo);
        Mail::assertQueuedCount(1);
    }

    public function test_no_se_puede_pagar_una_reserva_vencida(): void
    {
        $funcion = $this->crearFuncion();
        $orden = app(ReservaService::class)->reservar($funcion, $this->asientos($funcion, 1), $this->datosComprador(), null);
        $this->travel(11)->minutes();

        $this->conOrdenEnSesion($orden->uuid)->post(route('ordenes.pagar-demo', $orden))->assertSessionHasErrors('pago');
        $this->assertNotSame(Orden::PAGADA, $orden->fresh()->estado);
    }

    public function test_codigo_de_entrada_invalido_o_inexistente_da_404(): void
    {
        $this->get('/entradas/AAAAAAAAAAAAAAAA')->assertNotFound();
        $this->get('/entradas/corto')->assertNotFound();
    }

    public function test_recuperar_entradas_responde_igual_exista_o_no(): void
    {
        Mail::fake();

        $this->post(route('entradas.recuperar.enviar'), ['email' => 'nadie@ejemplo.com', 'documento' => 'X12345'])
            ->assertRedirect(route('entradas.recuperar'))
            ->assertSessionHas('aviso');
        Mail::assertNothingQueued();
    }
}
