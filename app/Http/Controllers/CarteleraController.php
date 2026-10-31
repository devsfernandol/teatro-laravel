<?php

namespace App\Http\Controllers;

use App\Models\Funcion;
use App\Models\Obra;
use Illuminate\View\View;

class CarteleraController extends Controller
{
    public function index(): View
    {
        // Una obra aparece en cartelera si tiene alguna función futura con asientos libres.
        $obras = Obra::publicadas()
            ->whereHas('funciones', fn ($q) => $q->vendibles())
            ->with(['funciones' => fn ($q) => $q->vendibles()->conDisponibilidad()->orderBy('inicia_en')])
            ->get()
            ->each(fn (Obra $o) => $o->setRelation('funciones', $o->funciones->filter(fn (Funcion $f) => $f->asientosLibres() > 0)->values()))
            ->filter(fn (Obra $o) => $o->funciones->isNotEmpty())
            ->sortBy(fn (Obra $o) => $o->funciones->first()->inicia_en)
            ->values();

        return view('publico.cartelera', ['obras' => $obras]);
    }

    public function obra(Obra $obra): View
    {
        abort_unless($obra->publicada, 404);

        $funciones = $obra->funciones()
            ->vendibles()
            ->conDisponibilidad()
            ->orderBy('inicia_en')
            ->get();

        return view('publico.obra', ['obra' => $obra, 'funciones' => $funciones]);
    }
}
