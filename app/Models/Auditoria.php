<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'accion', 'descripcion', 'ip'])]
class Auditoria extends Model
{
    protected $table = 'auditoria';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Registra una acción del panel de administración.
     */
    public static function registrar(string $accion, string $descripcion, ?int $userId = null): void
    {
        static::create([
            'user_id' => $userId ?? auth()->id(),
            'accion' => $accion,
            'descripcion' => Str::limit($descripcion, 250),
            'ip' => request()?->ip(),
        ]);
    }
}
