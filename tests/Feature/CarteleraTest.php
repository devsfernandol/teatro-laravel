<?php

namespace Tests\Feature;

use App\Models\Entrada;
use App\Models\Funcion;
use App\Models\Orden;
use Illuminate\Support\Str;
use Tests\TestCase;

class CarteleraTest extends TestCase
{
    public function test_muestra_solo_obras_con_funciones_futuras_y_asientos_libres(): void
    {
        $disponible = $this->crearFuncion(obra: ['titulo' => 'Obra Disponible']);
        $this->crearFuncion(['inicia_en' => now()->subDay()], obra: ['titulo' => 'Obra Pasada']);
        $this->crearFuncion(['estado' => Funcion::CANCELADA], obra: ['titulo' => 'Obra Cancelada']);
        $this->crearFuncion(obra: ['titulo' => 'Obra Oculta', 'publicada' => false]);
        $this->crearFuncion(['inicia_en' => now()->addMinutes(5)], obra: ['titulo' => 'Obra Por Empezar']);

        $agotada = $this->crearFuncion(sala: $this->crearSala(1, 2, 'Sala chica'), obra: ['titulo' => 'Obra Agotada']);
        $this->venderTodo($agotada);

        $this->get('/')
            ->assertOk()
            ->assertSee('Obra Disponible')
            ->assertDontSee('Obra Pasada')
            ->assertDontSee('Obra Cancelada')
            ->assertDontSee('Obra Oculta')
            ->assertDontSee('Obra Por Empezar')
            ->assertDontSee('Obra Agotada');

        $this->get(route('obras.show', $disponible->obra))->assertOk()->assertSee('12 asientos libres');
    }

    public function test_obra_no_publicada_da_404(): void
    {
        $funcion = $this->crearFuncion(obra: ['publicada' => false]);

        $this->get(route('obras.show', $funcion->obra))->assertNotFound();
        $this->get(route('funciones.asientos', $funcion))->assertNotFound();
    }

    public function test_cabeceras_de_seguridad(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('Permissions-Policy');

        $csp = $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
    }

    public function test_muestra_aviso_de_modo_demo(): void
    {
        $this->get('/')->assertSee('Modo demostración');
    }

    private function venderTodo(Funcion $funcion): void
    {
        $orden = Orden::create([
            'uuid' => (string) Str::uuid(), 'funcion_id' => $funcion->id, 'nombre' => 'X', 'documento' => 'X1234',
            'email' => 'x@x.com', 'total' => '25.00', 'moneda' => 'USD', 'estado' => Orden::PAGADA, 'expira_en' => now(),
        ]);
        foreach ($funcion->sala->asientos as $asiento) {
            Entrada::create(['orden_id' => $orden->id, 'funcion_id' => $funcion->id, 'asiento_id' => $asiento->id, 'precio' => '12.50']);
        }
    }
}
