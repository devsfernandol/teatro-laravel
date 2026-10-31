<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['titulo', 'slug', 'sinopsis', 'duracion_minutos', 'clasificacion', 'imagen', 'publicada'])]
class Obra extends Model
{
    protected $table = 'obras';

    protected function casts(): array
    {
        return [
            'duracion_minutos' => 'integer',
            'publicada' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function funciones(): HasMany
    {
        return $this->hasMany(Funcion::class)->orderBy('inicia_en');
    }

    public function scopePublicadas(Builder $query): Builder
    {
        return $query->where('publicada', true);
    }

    public function imagenUrl(): ?string
    {
        return $this->imagen ? Storage::disk('public')->url('obras/'.$this->imagen) : null;
    }
}
