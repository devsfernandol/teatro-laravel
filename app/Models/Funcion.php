<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['obra_id', 'sala_id', 'inicia_en', 'precio', 'estado'])]
class Funcion extends Model
{
    public const PROGRAMADA = 'programada';

    public const CANCELADA = 'cancelada';

    protected $table = 'funciones';

    protected function casts(): array
    {
        return [
            'inicia_en' => 'datetime',
            'precio' => 'decimal:2',
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    public function entradas(): HasMany
    {
        return $this->hasMany(Entrada::class);
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(Orden::class);
    }

    /**
     * Funciones a la venta: programadas, de una obra publicada y que todavía no
     * comenzaron (la venta cierra unos minutos antes, según la configuración).
     */
    public function scopeVendibles(Builder $query): Builder
    {
        return $query->where('estado', self::PROGRAMADA)
            ->where('inicia_en', '>', now()->addMinutes((int) config('teatro.cierre_venta_minutos')))
            ->whereHas('obra', fn (Builder $q) => $q->where('publicada', true));
    }

    public function esVendible(): bool
    {
        return $this->estado === self::PROGRAMADA
            && $this->inicia_en->isAfter(now()->addMinutes((int) config('teatro.cierre_venta_minutos')))
            && $this->obra->publicada;
    }

    /**
     * Cantidad de asientos activos que no están vendidos ni retenidos.
     * Requiere withCount(['entradas']) y la sala con asientos_activos_count,
     * o bien los calcula con consultas.
     */
    public function asientosLibres(): int
    {
        $activos = $this->sala->asientos_activos_count
            ?? $this->sala->asientos()->where('activo', true)->count();
        $ocupados = $this->entradas_count ?? $this->entradas()->count();

        return max(0, $activos - $ocupados);
    }

    public function scopeConDisponibilidad(Builder $query): Builder
    {
        return $query->withCount('entradas')
            ->with(['sala' => fn ($q) => $q->withCount(['asientos as asientos_activos_count' => fn ($a) => $a->where('activo', true)])]);
    }
}
