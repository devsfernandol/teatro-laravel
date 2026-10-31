<?php

namespace Database\Seeders;

use App\Models\Entrada;
use App\Models\Funcion;
use App\Models\Obra;
use App\Models\Orden;
use App\Models\Sala;
use App\Support\Codigo;
use App\Support\Dinero;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Datos de demostración: salas, obras, funciones futuras y algunas ventas.
 * Solo se cargan si la base no tiene obras.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Obra::exists()) {
            return;
        }

        $principal = $this->sala('Sala Principal', 10, 16, ['A' => [1, 16], 'J' => [1, 2, 15, 16]]);
        $lorca = $this->sala('Sala Lorca', 8, 12, []);
        $calderon = $this->sala('Sala Calderón', 4, 6, []);

        $obras = [
            ['La casa de Bernarda Alba', 'Federico García Lorca', 110, '+12', 'Tras la muerte de su segundo marido, Bernarda Alba impone a sus cinco hijas un luto de ocho años. El deseo y la rebeldía crecen entre las paredes de una casa cerrada al mundo.'],
            ['Bodas de sangre', 'Federico García Lorca', 100, '+14', 'Una boda en el campo andaluz se convierte en tragedia cuando la novia huye con Leonardo, su antiguo amor. Poesía, destino y pasión en una de las obras más intensas de Lorca.'],
            ['La vida es sueño', 'Pedro Calderón de la Barca', 120, '+12', 'Segismundo, encerrado en una torre desde su nacimiento, despierta un día como príncipe. Una reflexión sobre la libertad, el destino y la naturaleza de la realidad.'],
            ['Don Juan Tenorio', 'José Zorrilla', 130, '+12', 'El seductor más famoso de la literatura apuesta que conquistará a Doña Inés. Un drama romántico de amor, desafío y redención.'],
            ['El sí de las niñas', 'Leandro Fernández de Moratín', 95, 'ATP', 'Doña Francisca, de dieciséis años, está prometida a un hombre de cincuenta y nueve. Una comedia sobre la libertad de elegir con quién compartir la vida.'],
            ['Luces de bohemia', 'Ramón María del Valle-Inclán', 105, '+16', 'La última noche de Max Estrella, poeta ciego, por un Madrid absurdo y hambriento. El nacimiento del esperpento.'],
        ];

        $funciones = [
            // obra, sala, días desde hoy, hora, precio
            [0, $principal, 3, '20:00', 25], [0, $principal, 10, '20:00', 25],
            [1, $lorca, 5, '19:30', 18], [1, $lorca, 12, '19:30', 18],
            [2, $calderon, 7, '18:00', 15],
            [3, $principal, 14, '21:00', 30],
            [4, $lorca, 20, '17:00', 12],
            [5, $calderon, 4, '20:30', 20],
        ];

        $modelos = [];
        foreach ($obras as $i => [$titulo, $autor, $duracion, $clasificacion, $sinopsis]) {
            $modelos[$i] = Obra::create([
                'titulo' => $titulo,
                'slug' => Str::slug($titulo),
                'sinopsis' => $sinopsis."\n\nAutor: ".$autor.'.',
                'duracion_minutos' => $duracion,
                'clasificacion' => $clasificacion,
                'publicada' => true,
            ]);
        }

        foreach ($funciones as [$obra, $sala, $dias, $hora, $precio]) {
            $funcion = Funcion::create([
                'obra_id' => $modelos[$obra]->id,
                'sala_id' => $sala->id,
                'inicia_en' => today()->addDays($dias)->setTimeFromTimeString($hora),
                'precio' => $precio,
                'estado' => Funcion::PROGRAMADA,
            ]);

            // Algunas entradas ya vendidas, para que el plano no aparezca vacío.
            $vender = match (true) {
                $sala->is($calderon) && $obra === 5 => 24,    // Luces de bohemia: agotada.
                $sala->is($calderon) => 20,                    // La vida es sueño: quedan pocas.
                default => random_int(8, 30),
            };
            $this->venderAlAzar($funcion, $vender);
        }
    }

    /**
     * @param  array<string, list<int>>  $sinAsiento  Huecos de la sala (pasillos, columnas).
     */
    private function sala(string $nombre, int $filas, int $columnas, array $sinAsiento): Sala
    {
        $sala = Sala::create(['nombre' => $nombre, 'filas' => $filas, 'columnas' => $columnas]);
        $sala->sincronizarAsientos();

        foreach ($sinAsiento as $fila => $numeros) {
            $sala->asientos()->where('fila', $fila)->whereIn('numero', $numeros)->update(['activo' => false]);
        }

        return $sala;
    }

    private function venderAlAzar(Funcion $funcion, int $cantidad): void
    {
        $asientos = $funcion->sala->asientos()->where('activo', true)->get()->shuffle()->take($cantidad)->values();
        $nombres = ['Ana Rojas', 'Luis Mora', 'María Vargas', 'José Jiménez', 'Laura Solís', 'Carlos Castro'];

        foreach ($asientos->chunk(2) as $i => $grupo) {
            $nombre = $nombres[$i % count($nombres)];
            $orden = Orden::create([
                'uuid' => (string) Str::uuid(),
                'funcion_id' => $funcion->id,
                'nombre' => $nombre,
                'documento' => 'DEMO'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
                'email' => Str::slug($nombre, '.').'@ejemplo.com',
                'total' => Dinero::desdeCentavos(Dinero::aCentavos($funcion->precio) * $grupo->count()),
                'moneda' => config('teatro.moneda'),
                'estado' => Orden::PAGADA,
                'metodo_pago' => 'demo',
                'codigo' => Codigo::generar(),
                'expira_en' => now()->subDays(2),
                'pagada_en' => now()->subDays(random_int(1, 5)),
            ]);
            foreach ($grupo as $asiento) {
                Entrada::create([
                    'orden_id' => $orden->id,
                    'funcion_id' => $funcion->id,
                    'asiento_id' => $asiento->id,
                    'precio' => $funcion->precio,
                ]);
            }
        }
    }
}
