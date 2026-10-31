@extends('layouts.admin')

@section('titulo', $obra->exists ? 'Editar obra' : 'Nueva obra')

@section('contenido')
<p class="volver"><a href="{{ route('admin.obras.index') }}">← Obras</a></p>
<h1>{{ $obra->exists ? 'Editar «'.$obra->titulo.'»' : 'Nueva obra' }}</h1>

<form method="post" enctype="multipart/form-data" class="tarjeta formulario-admin"
      action="{{ $obra->exists ? route('admin.obras.update', $obra) : route('admin.obras.store') }}">
    @csrf
    @if ($obra->exists) @method('put') @endif

    <div class="campo">
        <label for="titulo">Título</label>
        <input type="text" id="titulo" name="titulo" value="{{ old('titulo', $obra->titulo) }}" maxlength="150" required>
        @include('partials.campo-error', ['campo' => 'titulo'])
    </div>
    <div class="campo">
        <label for="sinopsis">Sinopsis</label>
        <textarea id="sinopsis" name="sinopsis" rows="6" maxlength="5000" required>{{ old('sinopsis', $obra->sinopsis) }}</textarea>
        @include('partials.campo-error', ['campo' => 'sinopsis'])
    </div>
    <div class="campos-linea">
        <div class="campo">
            <label for="duracion_minutos">Duración (minutos)</label>
            <input type="number" id="duracion_minutos" name="duracion_minutos" value="{{ old('duracion_minutos', $obra->duracion_minutos) }}" min="1" max="600" required>
            @include('partials.campo-error', ['campo' => 'duracion_minutos'])
        </div>
        <div class="campo">
            <label for="clasificacion">Clasificación (opcional)</label>
            <input type="text" id="clasificacion" name="clasificacion" value="{{ old('clasificacion', $obra->clasificacion) }}" maxlength="20" placeholder="ATP, +12…">
            @include('partials.campo-error', ['campo' => 'clasificacion'])
        </div>
    </div>
    <div class="campo">
        <label for="imagen">Imagen de portada (JPG, PNG o WebP, máximo 4 MB)</label>
        @if ($obra->imagenUrl())
            <img class="miniatura" src="{{ $obra->imagenUrl() }}" alt="Portada actual">
            <label class="casilla"><input type="checkbox" name="quitar_imagen" value="1"> Quitar la imagen actual</label>
        @endif
        <input type="file" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp">
        @include('partials.campo-error', ['campo' => 'imagen'])
    </div>
    <label class="casilla">
        <input type="hidden" name="publicada" value="0">
        <input type="checkbox" name="publicada" value="1" @checked(old('publicada', $obra->publicada))> Publicada (visible en la cartelera)
    </label>

    <div class="formulario-admin__acciones">
        <button type="submit" class="boton">Guardar</button>
    </div>
</form>

@if ($obra->exists)
    <form method="post" action="{{ route('admin.obras.destroy', $obra) }}" class="zona-peligro">
        @csrf
        @method('delete')
        <p>Solo se pueden eliminar obras sin funciones. Si ya tiene funciones, despublíquela.</p>
        <button type="submit" class="boton boton--peligro">Eliminar obra</button>
    </form>
@endif
@endsection
