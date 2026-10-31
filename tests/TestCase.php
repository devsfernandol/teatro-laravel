<?php

namespace Tests;

use App\Models\Asiento;
use App\Models\Funcion;
use App\Models\Obra;
use App\Models\Sala;
use App\Models\User;
use App\Support\OrdenesSesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ninguna prueba puede salir a Internet por accidente.
        Http::preventStrayRequests();
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
    }

    protected function crearSala(int $filas = 3, int $columnas = 4, ?string $nombre = null): Sala
    {
        $sala = Sala::create(['nombre' => $nombre ?? 'Sala '.uniqid(), 'filas' => $filas, 'columnas' => $columnas]);
        $sala->sincronizarAsientos();

        return $sala;
    }

    protected function crearFuncion(array $atributos = [], ?Sala $sala = null, array $obra = []): Funcion
    {
        $sala ??= $this->crearSala();
        $obra = Obra::create($obra + [
            'titulo' => 'Obra '.uniqid(),
            'slug' => 'obra-'.uniqid(),
            'sinopsis' => 'Una sinopsis de prueba suficientemente larga.',
            'duracion_minutos' => 90,
            'publicada' => true,
        ]);

        return Funcion::create($atributos + [
            'obra_id' => $obra->id,
            'sala_id' => $sala->id,
            'inicia_en' => now()->addDays(3),
            'precio' => '12.50',
            'estado' => Funcion::PROGRAMADA,
        ]);
    }

    /**
     * @return list<int>
     */
    protected function asientos(Funcion $funcion, int $cantidad): array
    {
        return Asiento::where('sala_id', $funcion->sala_id)->orderBy('id')->limit($cantidad)->pluck('id')->all();
    }

    protected function datosComprador(array $extra = []): array
    {
        return $extra + ['nombre' => 'Ana Rojas', 'documento' => '1-2345-6789', 'email' => 'ana@ejemplo.com'];
    }

    protected function admin(array $atributos = []): User
    {
        $existente = User::where('email', $atributos['email'] ?? 'admin@ejemplo.com')->first();
        if ($existente !== null) {
            return $existente;
        }

        return User::create($atributos + [
            'name' => 'Admin',
            'email' => 'admin@ejemplo.com',
            'password' => 'Clave-Segura-123!',
            'activo' => true,
            'debe_cambiar_password' => false,
        ])->fresh();
    }

    protected function conOrdenEnSesion(string $uuid): static
    {
        return $this->withSession(['ordenes' => [$uuid]]);
    }
}
