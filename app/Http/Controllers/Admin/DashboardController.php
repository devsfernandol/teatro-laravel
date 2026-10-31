<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entrada;
use App\Models\Funcion;
use App\Models\Orden;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $pagadasHoy = Orden::where('estado', Orden::PAGADA)->where('pagada_en', '>=', today());

        return view('admin.dashboard', [
            'ventasHoy' => (clone $pagadasHoy)->count(),
            'ingresosHoy' => (clone $pagadasHoy)->sum('total'),
            'ingresosTotales' => Orden::where('estado', Orden::PAGADA)->sum('total'),
            'entradasVendidas' => Entrada::whereHas('orden', fn ($q) => $q->where('estado', Orden::PAGADA))->count(),
            'proximas' => Funcion::with('obra')
                ->conDisponibilidad()
                ->where('estado', Funcion::PROGRAMADA)
                ->where('inicia_en', '>', now())
                ->orderBy('inicia_en')
                ->limit(8)
                ->get(),
            'ultimas' => Orden::with('funcion.obra')
                ->whereIn('estado', [Orden::PAGADA, Orden::PROCESANDO, Orden::REEMBOLSADA])
                ->latest('id')
                ->limit(8)
                ->get(),
        ]);
    }
}
