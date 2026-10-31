<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Obra;
use App\Support\Imagenes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class ObraController extends Controller
{
    public function index(): View
    {
        return view('admin.obras.index', [
            'obras' => Obra::withCount('funciones')->orderBy('titulo')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.obras.form', ['obra' => new Obra(['publicada' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $datos['slug'] = $this->slugUnico($datos['titulo']);

        if ($request->hasFile('imagen')) {
            $datos['imagen'] = $this->guardarImagen($request);
        }

        $obra = Obra::create($datos);
        Auditoria::registrar('obra_creada', "Obra #{$obra->id}: {$obra->titulo}");

        return redirect()->route('admin.obras.index')->with('exito', 'Obra creada.');
    }

    public function edit(Obra $obra): View
    {
        return view('admin.obras.form', ['obra' => $obra]);
    }

    public function update(Request $request, Obra $obra): RedirectResponse
    {
        $datos = $this->validar($request, $obra);
        $imagenAnterior = $obra->imagen;

        if ($request->hasFile('imagen')) {
            $datos['imagen'] = $this->guardarImagen($request);
        } elseif ($request->boolean('quitar_imagen')) {
            $datos['imagen'] = null;
        }
        if ($datos['titulo'] !== $obra->titulo) {
            $datos['slug'] = $this->slugUnico($datos['titulo'], $obra->id);
        }

        $obra->update($datos);
        if ($imagenAnterior !== $obra->imagen) {
            Imagenes::borrarPortada($imagenAnterior);
        }
        Auditoria::registrar('obra_editada', "Obra #{$obra->id}: {$obra->titulo}");

        return redirect()->route('admin.obras.index')->with('exito', 'Obra actualizada.');
    }

    public function destroy(Obra $obra): RedirectResponse
    {
        if ($obra->funciones()->exists()) {
            return back()->withErrors(['obra' => 'La obra tiene funciones. Despublíquela en lugar de eliminarla.']);
        }

        $obra->delete();
        Imagenes::borrarPortada($obra->imagen);
        Auditoria::registrar('obra_eliminada', "Obra #{$obra->id}: {$obra->titulo}");

        return redirect()->route('admin.obras.index')->with('exito', 'Obra eliminada.');
    }

    private function validar(Request $request, ?Obra $obra = null): array
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'min:2', 'max:150', Rule::unique('obras', 'titulo')->ignore($obra?->id)],
            'sinopsis' => ['required', 'string', 'min:10', 'max:5000'],
            'duracion_minutos' => ['required', 'integer', 'between:1,600'],
            'clasificacion' => ['nullable', 'string', 'max:20'],
            'imagen' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096', 'dimensions:min_width=200,min_height=200,max_width=6000,max_height=6000'],
            'publicada' => ['nullable', 'boolean'],
        ]);

        unset($datos['imagen']);
        $datos['publicada'] = $request->boolean('publicada');

        return $datos;
    }

    private function guardarImagen(Request $request): string
    {
        try {
            return Imagenes::guardarPortada($request->file('imagen'));
        } catch (RuntimeException) {
            throw \Illuminate\Validation\ValidationException::withMessages(['imagen' => 'No se pudo procesar la imagen. Pruebe con otro archivo JPG, PNG o WebP.']);
        }
    }

    private function slugUnico(string $titulo, ?int $ignorar = null): string
    {
        $base = Str::slug($titulo) ?: 'obra';
        $base = Str::limit($base, 150, '');
        $slug = $base;
        $i = 2;
        while (Obra::where('slug', $slug)->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
