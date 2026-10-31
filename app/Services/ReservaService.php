<?php

namespace App\Services;

use App\Exceptions\VentaException;
use App\Models\Asiento;
use App\Models\Entrada;
use App\Models\Funcion;
use App\Models\Orden;
use App\Support\Dinero;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reserva de asientos.
 *
 * Una reserva crea una orden "pendiente" y una entrada por asiento. Las
 * entradas retienen los asientos durante unos minutos; si la orden no se paga
 * a tiempo, se liberan. Dos compradores nunca pueden quedarse con el mismo
 * asiento: lo impide el índice único (funcion_id, asiento_id) de la base.
 */
class ReservaService
{
    /**
     * @param  list<int>  $asientoIds
     * @param  array{nombre: string, documento: string, email: string}  $comprador
     *
     * @throws VentaException
     */
    public function reservar(Funcion $funcion, array $asientoIds, array $comprador, ?string $ip): Orden
    {
        $asientoIds = array_values(array_unique(array_map('intval', $asientoIds)));
        $maximo = (int) config('teatro.max_asientos_por_orden');

        if ($asientoIds === []) {
            throw new VentaException('Elija al menos un asiento.');
        }
        if (count($asientoIds) > $maximo) {
            throw new VentaException("Puede reservar como máximo {$maximo} asientos por compra.");
        }

        try {
            return DB::transaction(function () use ($funcion, $asientoIds, $comprador, $ip) {
                // Bloquear la función serializa las reservas de una misma función:
                // así no hay carreras entre compradores ni bloqueos mutuos.
                $funcion = Funcion::with('obra')->whereKey($funcion->getKey())->lockForUpdate()->firstOrFail();

                if (! $funcion->esVendible()) {
                    throw new VentaException('Esta función ya no está a la venta.');
                }

                $this->liberarVencidas($funcion->id);
                $this->verificarLimiteDeReservas($comprador['documento'], $ip);

                $asientos = Asiento::where('sala_id', $funcion->sala_id)
                    ->where('activo', true)
                    ->whereIn('id', $asientoIds)
                    ->orderBy('fila')->orderBy('numero')
                    ->get();

                if ($asientos->count() !== count($asientoIds)) {
                    throw new VentaException('Alguno de los asientos elegidos no existe en esta sala.');
                }

                $ocupados = Entrada::with('asiento')
                    ->where('funcion_id', $funcion->id)
                    ->whereIn('asiento_id', $asientoIds)
                    ->get();

                if ($ocupados->isNotEmpty()) {
                    $etiquetas = $ocupados->map(fn (Entrada $e) => $e->asiento->etiqueta())->sort()->implode(', ');
                    throw new VentaException("Los siguientes asientos acaban de ser ocupados: {$etiquetas}. Elija otros.");
                }

                $total = Dinero::desdeCentavos(Dinero::aCentavos($funcion->precio) * $asientos->count());

                $orden = Orden::create([
                    'uuid' => (string) Str::uuid(),
                    'funcion_id' => $funcion->id,
                    'nombre' => $comprador['nombre'],
                    'documento' => $comprador['documento'],
                    'email' => $comprador['email'],
                    'total' => $total,
                    'moneda' => config('teatro.moneda'),
                    'estado' => Orden::PENDIENTE,
                    'expira_en' => now()->addMinutes((int) config('teatro.reserva_minutos')),
                    'ip' => $ip,
                ]);

                foreach ($asientos as $asiento) {
                    Entrada::create([
                        'orden_id' => $orden->id,
                        'funcion_id' => $funcion->id,
                        'asiento_id' => $asiento->id,
                        'precio' => $funcion->precio,
                    ]);
                }

                return $orden;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new VentaException('Alguien acaba de reservar uno de esos asientos. Elija otros.');
        }
    }

    /**
     * Cancela una reserva pendiente y libera sus asientos.
     */
    public function cancelar(Orden $orden): void
    {
        DB::transaction(function () use ($orden) {
            $orden = Orden::whereKey($orden->getKey())->lockForUpdate()->firstOrFail();
            if ($orden->estado === Orden::PENDIENTE) {
                $orden->entradas()->delete();
                $orden->update(['estado' => Orden::CANCELADA]);
            }
        });
    }

    /**
     * Libera los asientos de las reservas pendientes que vencieron.
     * Las órdenes en "procesando" no se tocan: su pago se está confirmando.
     */
    public function liberarVencidas(?int $funcionId = null): int
    {
        $ids = Orden::where('estado', Orden::PENDIENTE)
            ->where('expira_en', '<=', now())
            ->when($funcionId !== null, fn ($q) => $q->where('funcion_id', $funcionId))
            ->limit(500)
            ->pluck('id');

        $liberadas = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$liberadas) {
                $orden = Orden::whereKey($id)->lockForUpdate()->first();
                if ($orden !== null && $orden->estado === Orden::PENDIENTE && $orden->expira_en->lte(now())) {
                    $orden->entradas()->delete();
                    $orden->update(['estado' => Orden::EXPIRADA]);
                    $liberadas++;
                }
            });
        }

        return $liberadas;
    }

    private function verificarLimiteDeReservas(string $documento, ?string $ip): void
    {
        $pendientes = Orden::where('estado', Orden::PENDIENTE)
            ->where('expira_en', '>', now())
            ->where(function ($q) use ($documento, $ip) {
                $q->where('documento', $documento);
                if ($ip !== null) {
                    $q->orWhere('ip', $ip);
                }
            })
            ->count();

        if ($pendientes >= (int) config('teatro.max_reservas_pendientes')) {
            throw new VentaException('Tiene demasiadas reservas sin pagar. Complete o cancele alguna antes de hacer otra.');
        }
    }
}
