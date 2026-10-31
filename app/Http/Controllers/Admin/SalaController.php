<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Sala;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalaController extends Controller
{
    public function index(): View
    {
        return view('admin.salas.index', [
            'salas' => Sala::withCount(['asientos as asientos_activos_count' => fn ($q) => $q->where('activo', true), 'funciones'])
                ->orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.salas.form', ['sala' => new Sala(['filas' => 8, 'columnas' => 12]), 'filas' => collect()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $sala = DB::transaction(function () use ($datos) {
            $sala = Sala::create($datos);
            $sala->sincronizarAsientos();

            return $sala;
        });
        Auditoria::registrar('sala_creada', "Sala #{$sala->id}: {$sala->nombre} ({$sala->filas}x{$sala->columnas})");

        return redirect()->route('admin.salas.edit', $sala)->with('exito', 'Sala creada. Puede desactivar los asientos que no existan (pasillos, columnas).');
    }

    public function edit(Sala $sala): View
    {
        return view('admin.salas.form', [
            'sala' => $sala,
            'filas' => $sala->asientos()->get()->groupBy('fila'),
        ]);
    }

    public function update(Request $request, Sala $sala): RedirectResponse
    {
        $datos = $this->validar($request, $sala);
        $request->validate([
            'activos' => ['nullable', 'array'],
            'activos.*' => ['integer'],
        ]);

        DB::transaction(function () use ($sala, $datos, $request) {
            $dimensionesCambiaron = $sala->filas !== (int) $datos['filas'] || $sala->columnas !== (int) $datos['columnas'];
            $sala->update($datos);
            if ($dimensionesCambiaron) {
                $sala->sincronizarAsientos();
            } elseif ($request->has('mapa_enviado')) {
                $activos = array_map('intval', $request->input('activos', []));
                $ultimaFila = Sala::letraFila($sala->filas);
                $sala->asientos()->update(['activo' => false]);
                $sala->asientos()
                    ->whereIn('id', $activos)
                    ->where('fila', '<=', $ultimaFila)
                    ->where('numero', '<=', $sala->columnas)
                    ->update(['activo' => true]);
            }
        });
        Auditoria::registrar('sala_editada', "Sala #{$sala->id}: {$sala->nombre}");

        return redirect()->route('admin.salas.edit', $sala)->with('exito', 'Sala actualizada.');
    }

    public function destroy(Sala $sala): RedirectResponse
    {
        if ($sala->funciones()->exists()) {
            return back()->withErrors(['sala' => 'La sala tiene funciones programadas o con historial y no puede eliminarse.']);
        }

        $sala->delete();
        Auditoria::registrar('sala_eliminada', "Sala #{$sala->id}: {$sala->nombre}");

        return redirect()->route('admin.salas.index')->with('exito', 'Sala eliminada.');
    }

    private function validar(Request $request, ?Sala $sala = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:80', Rule::unique('salas', 'nombre')->ignore($sala?->id)],
            'filas' => ['required', 'integer', 'between:1,26'],
            'columnas' => ['required', 'integer', 'between:1,40'],
        ]);
    }
}
