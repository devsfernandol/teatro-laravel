<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uuid', 'funcion_id', 'nombre', 'documento', 'email', 'total', 'moneda', 'estado',
    'metodo_pago', 'paypal_order_id', 'paypal_capture_id', 'codigo', 'expira_en',
    'pagada_en', 'reembolsada_en', 'ip',
])]
#[Hidden(['ip'])]
class Orden extends Model
{
    public const PENDIENTE = 'pendiente';

    /** El pago se está confirmando con PayPal: los asientos siguen retenidos. */
    public const PROCESANDO = 'procesando';

    public const PAGADA = 'pagada';

    public const EXPIRADA = 'expirada';

    public const CANCELADA = 'cancelada';

    public const REEMBOLSADA = 'reembolsada';

    public const ESTADOS = [self::PENDIENTE, self::PROCESANDO, self::PAGADA, self::EXPIRADA, self::CANCELADA, self::REEMBOLSADA];

    protected $table = 'ordenes';

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'expira_en' => 'datetime',
            'pagada_en' => 'datetime',
            'reembolsada_en' => 'datetime',
        ];
    }

    public function funcion(): BelongsTo
    {
        return $this->belongsTo(Funcion::class);
    }

    public function entradas(): HasMany
    {
        return $this->hasMany(Entrada::class)->orderBy('id');
    }

    public function estaPagada(): bool
    {
        return $this->estado === self::PAGADA;
    }

    public function puedePagarse(): bool
    {
        return $this->estado === self::PENDIENTE && $this->expira_en->isFuture();
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            self::PENDIENTE => 'Pendiente de pago',
            self::PROCESANDO => 'Confirmando pago',
            self::PAGADA => 'Pagada',
            self::EXPIRADA => 'Expirada',
            self::CANCELADA => 'Cancelada',
            self::REEMBOLSADA => 'Reembolsada',
            default => $this->estado,
        };
    }
}
