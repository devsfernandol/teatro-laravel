@extends('layouts.admin')

@section('titulo', $sala->exists ? 'Editar sala' : 'Nueva sala')

@section('contenido')
<p class="volver"><a href="{{ route('admin.salas.index') }}">← Salas</a></p>
<h1>{{ $sala->exists ? 'Sala «'.$sala->nombre.'»' : 'Nueva sala' }}</h1>

<form method="post" class="tarjeta formulario-admin" action="{{ $sala->exists ? route('admin.salas.update', $sala) : route('admin.salas.store') }}">
    @csrf
    @if ($sala->exists) @method('put') @endif

    <div class="campos-linea">
        <div class="campo">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" value="{{ old('nombre', $sala->nombre) }}" maxlength="80" required>
            @include('partials.campo-error', ['campo' => 'nombre'])
        </div>
        <div class="campo">
            <label for="filas">Filas (1 a 26, A–Z)</label>
            <input type="number" id="filas" name="filas" value="{{ old('filas', $sala->filas) }}" min="1" max="26" required>
            @include('partials.campo-error', ['campo' => 'filas'])
        </div>
        <div class="campo">
            <label for="columnas">Asientos por fila (1 a 40)</label>
            <input type="number" id="columnas" name="columnas" value="{{ old('columnas', $sala->columnas) }}" min="1" max="40" required>
            @include('partials.campo-error', ['campo' => 'columnas'])
        </div>
    </div>

    @if ($sala->exists)
        <input type="hidden" name="mapa_enviado" value="1">
        <h2>Plano</h2>
        <p class="campo__ayuda">Desmarque los lugares donde no hay butaca (pasillos, columnas). Los asientos desmarcados no se venden.
            Si cambia las filas o los asientos por fila, el plano se regenera y conviene revisarlo.</p>
        @include('partials.mapa-asientos', ['modo' => 'sala'])
    @endif

    <div class="formulario-admin__acciones">
        <button type="submit" class="boton">Guardar</button>
    </div>
</form>

@if ($sala->exists)
    <form method="post" action="{{ route('admin.salas.destroy', $sala) }}" class="zona-peligro">
        @csrf
        @method('delete')
        <p>Solo se pueden eliminar salas sin funciones.</p>
        <button type="submit" class="boton boton--peligro">Eliminar sala</button>
    </form>
@endif
@endsection
