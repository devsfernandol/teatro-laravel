<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'filas', 'columnas'])]
class Sala extends Model
{
    protected $table = 'salas';

    protected function casts(): array
    {
        return [
            'filas' => 'integer',
            'columnas' => 'integer',
        ];
    }

    public function asientos(): HasMany
    {
        return $this->hasMany(Asiento::class)->orderBy('fila')->orderBy('numero');
    }

    public function funciones(): HasMany
    {
        return $this->hasMany(Funcion::class);
    }

    /**
     * Letra de una fila a partir de su posición (1 => "A").
     */
    public static function letraFila(int $posicion): string
    {
        return chr(64 + $posicion);
    }

    /**
     * Crea los asientos que falten para cubrir filas x columnas. Los asientos
     * fuera de las nuevas dimensiones se desactivan en lugar de borrarse, porque
     * pueden tener entradas vendidas.
     */
    public function sincronizarAsientos(): void
    {
        $existentes = $this->asientos()->get()->keyBy(fn (Asiento $a) => $a->fila.'-'.$a->numero);

        for ($f = 1; $f <= $this->filas; $f++) {
            $fila = self::letraFila($f);
            for ($n = 1; $n <= $this->columnas; $n++) {
                if (! $existentes->has($fila.'-'.$n)) {
                    $this->asientos()->create(['fila' => $fila, 'numero' => $n, 'activo' => true]);
                }
            }
        }

        $ultimaFila = self::letraFila($this->filas);
        $this->asientos()
            ->where(fn ($q) => $q->where('fila', '>', $ultimaFila)->orWhere('numero', '>', $this->columnas))
            ->update(['activo' => false]);
    }
}
