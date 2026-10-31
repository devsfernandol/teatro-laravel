<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\VentaException;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Orden;
use App\Services\PagoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrdenController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.ordenes.index', [
            'ordenes' => $this->consulta($request)->with('funcion.obra')->latest('id')->paginate(30)->withQueryString(),
            'estados' => Orden::ESTADOS,
        ]);
    }

    public function show(Orden $orden): View
    {
        $orden->load('funcion.obra', 'funcion.sala', 'entradas.asiento');

        return view('admin.ordenes.show', ['orden' => $orden]);
    }

    public function reembolsar(Request $request, Orden $orden, PagoService $pagos): RedirectResponse
    {
        $request->validate(['confirmar' => ['accepted']], ['confirmar.accepted' => 'Marque la casilla para confirmar el reembolso.']);

        $asientos = $orden->entradas()->with('asiento')->get()->map(fn ($e) => '#'.$e->id.' '.$e->asiento->etiqueta())->implode(', ');

        try {
            $pagos->reembolsar($orden);
        } catch (VentaException $e) {
            return back()->withErrors(['reembolso' => $e->getMessage()]);
        }

        Auditoria::registrar('reembolso', "Orden #{$orden->id} ({$orden->total} {$orden->moneda}) reembolsada. Entradas: {$asientos}");

        return redirect()->route('admin.ordenes.show', $orden)->with('exito', 'Orden reembolsada y asientos liberados.');
    }

    /**
     * Exporta las ventas filtradas a CSV.
     */
    public function exportar(Request $request): StreamedResponse
    {
        $consulta = $this->consulta($request)->with('funcion.obra')->withCount('entradas')->orderBy('id');
        Auditoria::registrar('exportacion', 'Exportación de ventas a CSV');

        return response()->streamDownload(function () use ($consulta) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Orden', 'Estado', 'Obra', 'Función', 'Comprador', 'Documento', 'Correo', 'Entradas', 'Total', 'Moneda', 'Método', 'Pagada en'], ';', '"', '');
            $consulta->chunk(500, function ($ordenes) use ($salida) {
                foreach ($ordenes as $o) {
                    fputcsv($salida, array_map([$this, 'celdaSegura'], [
                        $o->id, $o->estado, $o->funcion->obra->titulo, $o->funcion->inicia_en->format('Y-m-d H:i'),
                        $o->nombre, $o->documento, $o->email, $o->entradas_count, $o->total, $o->moneda,
                        $o->metodo_pago ?? '', $o->pagada_en?->format('Y-m-d H:i') ?? '',
                    ]), ';', '"', '');
                }
            });
            fclose($salida);
        }, 'ventas-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Evita la inyección de fórmulas al abrir el CSV en una hoja de cálculo.
     */
    public function celdaSegura(mixed $valor): string
    {
        $texto = (string) $valor;

        return preg_match('/^[=+\-@\t\r]/', $texto) === 1 ? "'".$texto : $texto;
    }

    private function consulta(Request $request): Builder
    {
        $estado = $request->query('estado');
        $buscar = trim((string) $request->query('q', ''));
        $funcion = $request->integer('funcion');

        return Orden::query()
            ->when(in_array($estado, Orden::ESTADOS, true), fn ($q) => $q->where('estado', $estado))
            ->when($funcion > 0, fn ($q) => $q->where('funcion_id', $funcion))
            ->when($buscar !== '', function ($q) use ($buscar) {
                $buscar = mb_substr($buscar, 0, 100);
                $q->where(function ($w) use ($buscar) {
                    $w->where('documento', strtoupper($buscar))
                        ->orWhere('email', 'like', '%'.addcslashes(strtolower($buscar), '%_\\').'%')
                        ->orWhere('codigo', strtoupper($buscar))
                        ->orWhere('uuid', $buscar)
                        ->orWhere('paypal_order_id', $buscar);
                    if (ctype_digit($buscar)) {
                        $w->orWhere('id', (int) $buscar);
                    }
                });
            });
    }
}
