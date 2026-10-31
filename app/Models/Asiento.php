<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['fila', 'numero', 'activo'])]
class Asiento extends Model
{
    protected $table = 'asientos';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    public function etiqueta(): string
    {
        return $this->fila.$this->numero;
    }
}
