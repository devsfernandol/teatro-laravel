<?php

namespace Tests\Feature;

use App\Exceptions\VentaException;
use App\Models\Entrada;
use App\Models\Orden;
use App\Services\ReservaService;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

class ReservaTest extends TestCase
{
    public function test_reserva_asientos_y_redirige_al_pago(): void
    {
        $funcion = $this->crearFuncion();
        $asientos = $this->asientos($funcion, 2);

        $respuesta = $this->post(route('funciones.reservar', $funcion), $this->datosComprador(['asientos' => $asientos, 'documento' => 'v-12.345.678']));

        $orden = Orden::sole();
        $respuesta->assertRedirect(route('ordenes.show', $orden));
        $this->assertSame(Orden::PENDIENTE, $orden->estado);
        $this->assertSame('25.00', $orden->total);
        $this->assertSame('V-12345678', $orden->documento);
        $this->assertSame(2, $orden->entradas()->count());
        $this->assertTrue($orden->expira_en->isFuture());

        $this->get(route('ordenes.show', $orden))->assertOk()->assertSee('Confirme y pague');
    }

    public function test_no_permite_reservar_un_asiento_ocupado(): void
    {
        $funcion = $this->crearFuncion();
        $asientos = $this->asientos($funcion, 2);
        app(ReservaService::class)->reservar($funcion, [$asientos[0]], $this->datosComprador(), '10.0.0.1');

        $this->from(route('funciones.asientos', $funcion))
            ->post(route('funciones.reservar', $funcion), $this->datosComprador(['asientos' => $asientos, 'documento' => 'OTRO-123']))
            ->assertRedirect(route('funciones.asientos', $funcion))
            ->assertSessionHasErrors('asientos');

        $this->assertSame(1, Entrada::count());
    }

    public function test_la_base_impide_vender_dos_veces_el_mismo_asiento(): void
    {
        $funcion = $this->crearFuncion();
        [$asiento] = $this->asientos($funcion, 1);
        $orden = app(ReservaService::class)->reservar($funcion, [$asiento], $this->datosComprador(), null);

        $this->expectException(UniqueConstraintViolationException::class);
        Entrada::create(['orden_id' => $orden->id, 'funcion_id' => $funcion->id, 'asiento_id' => $asiento, 'precio' => '1.00']);
    }

    public function test_rechaza_asientos_de_otra_sala_e_inactivos(): void
    {
        $funcion = $this->crearFuncion();
        $otra = $this->crearFuncion(sala: $this->crearSala(nombre: 'Otra sala'));
        $ajeno = $this->asientos($otra, 1);

        $this->expectException(VentaException::class);
        app(ReservaService::class)->reservar($funcion, $ajeno, $this->datosComprador(), null);
    }

    public function test_respeta_el_maximo_de_asientos(): void
    {
        $funcion = $this->crearFuncion();

        $this->post(route('funciones.reservar', $funcion), $this->datosComprador(['asientos' => $this->asientos($funcion, 9)]))
            ->assertSessionHasErrors('asientos');
        $this->assertSame(0, Orden::count());
    }

    public function test_valida_los_datos_del_comprador(): void
    {
        $funcion = $this->crearFuncion();
        $asientos = $this->asientos($funcion, 1);

        $this->post(route('funciones.reservar', $funcion), [
            'asientos' => $asientos, 'nombre' => '<script>', 'documento' => "1' OR '1'='1", 'email' => 'no-es-correo',
        ])->assertSessionHasErrors(['nombre', 'documento', 'email']);

        $this->post(route('funciones.reservar', $funcion), ['asientos' => 'abc'] + $this->datosComprador())
            ->assertSessionHasErrors('asientos');

        $this->assertSame(0, Orden::count());
    }

    public function test_limita_las_reservas_pendientes_por_comprador(): void
    {
        $funcion = $this->crearFuncion();
        $ids = $this->asientos($funcion, 4);
        $servicio = app(ReservaService::class);

        foreach (array_slice($ids, 0, 3) as $id) {
            $servicio->reservar($funcion, [$id], $this->datosComprador(), '10.0.0.9');
        }

        $this->expectException(VentaException::class);
        $servicio->reservar($funcion, [$ids[3]], $this->datosComprador(['documento' => 'OTRO-999']), '10.0.0.9');
    }

    public function test_las_reservas_vencidas_liberan_los_asientos(): void
    {
        $funcion = $this->crearFuncion();
        $asientos = $this->asientos($funcion, 2);
        $servicio = app(ReservaService::class);
        $vieja = $servicio->reservar($funcion, $asientos, $this->datosComprador(), null);

        $this->travel(11)->minutes();

        $nueva = $servicio->reservar($funcion, $asientos, $this->datosComprador(['documento' => 'NUEVO-1']), null);

        $this->assertSame(Orden::EXPIRADA, $vieja->fresh()->estado);
        $this->assertSame(0, $vieja->entradas()->count());
        $this->assertSame(2, $nueva->entradas()->count());
    }

    public function test_el_comando_de_mantenimiento_libera_reservas(): void
    {
        $funcion = $this->crearFuncion();
        $orden = app(ReservaService::class)->reservar($funcion, $this->asientos($funcion, 1), $this->datosComprador(), null);

        $this->travel(15)->minutes();
        $this->artisan('teatro:mantenimiento')->assertSuccessful();

        $this->assertSame(Orden::EXPIRADA, $orden->fresh()->estado);
        $this->assertSame(0, Entrada::count());
    }

    public function test_cancelar_reserva_libera_asientos(): void
    {
        $funcion = $this->crearFuncion();
        $orden = app(ReservaService::class)->reservar($funcion, $this->asientos($funcion, 2), $this->datosComprador(), null);

        $this->conOrdenEnSesion($orden->uuid)->post(route('ordenes.cancelar', $orden))->assertRedirect();

        $this->assertSame(Orden::CANCELADA, $orden->fresh()->estado);
        $this->assertSame(0, Entrada::count());
    }

    public function test_no_se_vende_una_funcion_que_ya_cerro(): void
    {
        $funcion = $this->crearFuncion(['inicia_en' => now()->addMinutes(10)]);

        $this->get(route('funciones.asientos', $funcion))->assertNotFound();
        $this->post(route('funciones.reservar', $funcion), $this->datosComprador(['asientos' => $this->asientos($funcion, 1)]))
            ->assertSessionHasErrors('asientos');
    }

    public function test_el_plano_marca_los_asientos_ocupados(): void
    {
        $funcion = $this->crearFuncion();
        [$ocupado] = $this->asientos($funcion, 1);
        app(ReservaService::class)->reservar($funcion, [$ocupado], $this->datosComprador(), null);

        $this->get(route('funciones.asientos', $funcion))
            ->assertOk()
            ->assertSee('id="asiento-'.$ocupado.'"', false)
            ->assertSee('A1 (ocupado)');
    }

    public function test_la_pagina_de_pago_solo_la_ve_quien_reservo(): void
    {
        $funcion = $this->crearFuncion();
        $orden = app(ReservaService::class)->reservar($funcion, $this->asientos($funcion, 1), $this->datosComprador(), null);

        $this->get(route('ordenes.show', $orden))->assertNotFound();
        $this->post(route('ordenes.pagar-demo', $orden))->assertNotFound();
        $this->post(route('ordenes.cancelar', $orden))->assertNotFound();
    }
}
