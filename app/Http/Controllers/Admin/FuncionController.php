<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Funcion;
use App\Models\Obra;
use App\Models\Orden;
use App\Models\Sala;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FuncionController extends Controller
{
    public function index(Request $request): View
    {
        $verPasadas = $request->boolean('pasadas');

        return view('admin.funciones.index', [
            'funciones' => Funcion::with('obra')
                ->conDisponibilidad()
                ->when(! $verPasadas, fn ($q) => $q->where('inicia_en', '>', now()->subDay()))
                ->orderBy('inicia_en', $verPasadas ? 'desc' : 'asc')
                ->paginate(30)
                ->withQueryString(),
            'verPasadas' => $verPasadas,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.funciones.form', [
            'funcion' => new Funcion(['obra_id' => $request->integer('obra') ?: null]),
            'obras' => Obra::orderBy('titulo')->get(['id', 'titulo']),
            'salas' => Sala::orderBy('nombre')->get(['id', 'nombre']),
            'tieneVentas' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $funcion = Funcion::create($datos + ['estado' => Funcion::PROGRAMADA]);
        Auditoria::registrar('funcion_creada', "Función #{$funcion->id} de obra #{$funcion->obra_id} el {$funcion->inicia_en->format('d/m/Y H:i')}");

        return redirect()->route('admin.funciones.index')->with('exito', 'Función programada.');
    }

    public function show(Funcion $funcion): View
    {
        $funcion->load('obra', 'sala');
        $entradas = $funcion->entradas()->with('orden:id,estado,nombre')->get()->keyBy('asiento_id');

        return view('admin.funciones.show', [
            'funcion' => $funcion,
            'filas' => $funcion->sala->asientos()->get()->groupBy('fila'),
            'entradas' => $entradas,
            'resumen' => [
                'vendidas' => $entradas->filter(fn ($e) => $e->orden->estado === Orden::PAGADA)->count(),
                'retenidas' => $entradas->filter(fn ($e) => $e->orden->estado !== Orden::PAGADA)->count(),
                'ingresos' => Orden::where('funcion_id', $funcion->id)->where('estado', Orden::PAGADA)->sum('total'),
            ],
        ]);
    }

    public function edit(Funcion $funcion): View
    {
        return view('admin.funciones.form', [
            'funcion' => $funcion,
            'obras' => Obra::orderBy('titulo')->get(['id', 'titulo']),
            'salas' => Sala::orderBy('nombre')->get(['id', 'nombre']),
            'tieneVentas' => $funcion->entradas()->exists(),
        ]);
    }

    public function update(Request $request, Funcion $funcion): RedirectResponse
    {
        $datos = $this->validar($request, $funcion);

        if ($funcion->entradas()->exists()
            && ((int) $datos['sala_id'] !== $funcion->sala_id || (int) $datos['obra_id'] !== $funcion->obra_id)) {
            throw ValidationException::withMessages(['sala_id' => 'La función ya tiene entradas: no se puede cambiar la obra ni la sala.']);
        }

        $funcion->update($datos);
        Auditoria::registrar('funcion_editada', "Función #{$funcion->id}");

        return redirect()->route('admin.funciones.index')->with('exito', 'Función actualizada.');
    }

    public function cancelar(Funcion $funcion): RedirectResponse
    {
        $funcion->update(['estado' => Funcion::CANCELADA]);
        $pagadas = $funcion->ordenes()->where('estado', Orden::PAGADA)->count();
        Auditoria::registrar('funcion_cancelada', "Función #{$funcion->id} cancelada ({$pagadas} órdenes pagadas)");

        $mensaje = 'Función cancelada: ya no se venden entradas.';
        if ($pagadas > 0) {
            $mensaje .= " Tiene {$pagadas} órdenes pagadas: reembólselas desde Ventas.";
        }

        return redirect()->route('admin.ordenes.index', ['funcion' => $funcion->id, 'estado' => Orden::PAGADA])->with('exito', $mensaje);
    }

    public function destroy(Funcion $funcion): RedirectResponse
    {
        if ($funcion->ordenes()->exists()) {
            return back()->withErrors(['funcion' => 'La función tiene órdenes registradas. Cancélela en lugar de eliminarla.']);
        }

        $funcion->delete();
        Auditoria::registrar('funcion_eliminada', "Función #{$funcion->id}");

        return redirect()->route('admin.funciones.index')->with('exito', 'Función eliminada.');
    }

    private function validar(Request $request, ?Funcion $funcion = null): array
    {
        $datos = $request->validate([
            'obra_id' => ['required', 'integer', 'exists:obras,id'],
            'sala_id' => ['required', 'integer', 'exists:salas,id'],
            'inicia_en' => ['required', 'date_format:Y-m-d\TH:i', 'after:now', 'before:+2 years'],
            'precio' => ['required', 'numeric', 'decimal:0,2', 'min:0.5', 'max:10000'],
        ]);

        $inicio = Carbon::createFromFormat('Y-m-d\TH:i', $datos['inicia_en']);
        $duracion = Obra::findOrFail($datos['obra_id'])->duracion_minutos;
        $fin = $inicio->copy()->addMinutes($duracion);

        // Dos funciones no pueden ocupar la misma sala al mismo tiempo.
        $choque = Funcion::with('obra:id,titulo,duracion_minutos')
            ->where('sala_id', $datos['sala_id'])
            ->where('estado', Funcion::PROGRAMADA)
            ->when($funcion, fn ($q) => $q->whereKeyNot($funcion->id))
            ->whereBetween('inicia_en', [$inicio->copy()->subHours(12), $fin])
            ->get()
            ->first(fn (Funcion $otra) => $otra->inicia_en->lt($fin) && $otra->inicia_en->copy()->addMinutes($otra->obra->duracion_minutos)->gt($inicio));

        if ($choque !== null) {
            throw ValidationException::withMessages([
                'inicia_en' => "La sala está ocupada por «{$choque->obra->titulo}» el {$choque->inicia_en->format('d/m/Y H:i')}.",
            ]);
        }

        $datos['inicia_en'] = $inicio;

        return $datos;
    }
}
