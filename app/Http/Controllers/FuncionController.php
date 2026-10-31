<?php

namespace App\Http\Controllers;

use App\Exceptions\VentaException;
use App\Http\Requests\ReservarRequest;
use App\Models\Funcion;
use App\Services\ReservaService;
use App\Support\OrdenesSesion;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FuncionController extends Controller
{
    public function __construct(private readonly ReservaService $reservas)
    {
    }

    public function asientos(Funcion $funcion): View
    {
        $funcion->load('obra', 'sala');
        abort_unless($funcion->esVendible(), 404);

        $this->reservas->liberarVencidas($funcion->id);

        return view('publico.asientos', [
            'funcion' => $funcion,
            'filas' => $funcion->sala->asientos()->get()->groupBy('fila'),
            'ocupados' => $funcion->entradas()->pluck('asiento_id')->flip(),
            'maximo' => (int) config('teatro.max_asientos_por_orden'),
        ]);
    }

    public function reservar(ReservarRequest $request, Funcion $funcion): RedirectResponse
    {
        try {
            $orden = $this->reservas->reservar(
                $funcion,
                $request->validated('asientos'),
                $request->safe()->only(['nombre', 'documento', 'email']),
                $request->ip(),
            );
        } catch (VentaException $e) {
            return back()->withInput()->withErrors(['asientos' => $e->getMessage()]);
        }

        OrdenesSesion::agregar($orden->uuid);

        return redirect()->route('ordenes.show', $orden);
    }
}
