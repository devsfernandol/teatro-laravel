@extends('layouts.admin')

@section('titulo', $funcion->exists ? 'Editar función' : 'Programar función')

@section('contenido')
<p class="volver"><a href="{{ route('admin.funciones.index') }}">← Funciones</a></p>
<h1>{{ $funcion->exists ? 'Editar función' : 'Programar función' }}</h1>

@if ($obras->isEmpty() || $salas->isEmpty())
    <div class="alerta alerta--aviso">Antes de programar funciones necesita al menos una <a href="{{ route('admin.obras.create') }}">obra</a> y una <a href="{{ route('admin.salas.create') }}">sala</a>.</div>
@endif

<form method="post" class="tarjeta formulario-admin" action="{{ $funcion->exists ? route('admin.funciones.update', $funcion) : route('admin.funciones.store') }}">
    @csrf
    @if ($funcion->exists) @method('put') @endif

    <div class="campo">
        <label for="obra_id">Obra</label>
        <select id="obra_id" name="obra_id" required @disabled($tieneVentas)>
            <option value="">Elija una obra</option>
            @foreach ($obras as $o)
                <option value="{{ $o->id }}" @selected((int) old('obra_id', $funcion->obra_id) === $o->id)>{{ $o->titulo }}</option>
            @endforeach
        </select>
        @if ($tieneVentas)<input type="hidden" name="obra_id" value="{{ $funcion->obra_id }}">@endif
        @include('partials.campo-error', ['campo' => 'obra_id'])
    </div>
    <div class="campo">
        <label for="sala_id">Sala</label>
        <select id="sala_id" name="sala_id" required @disabled($tieneVentas)>
            <option value="">Elija una sala</option>
            @foreach ($salas as $s)
                <option value="{{ $s->id }}" @selected((int) old('sala_id', $funcion->sala_id) === $s->id)>{{ $s->nombre }}</option>
            @endforeach
        </select>
        @if ($tieneVentas)<input type="hidden" name="sala_id" value="{{ $funcion->sala_id }}">@endif
        @include('partials.campo-error', ['campo' => 'sala_id'])
        @if ($tieneVentas)<p class="campo__ayuda">La función ya tiene entradas: la obra y la sala no se pueden cambiar.</p>@endif
    </div>
    <div class="campos-linea">
        <div class="campo">
            <label for="inicia_en">Fecha y hora</label>
            <input type="datetime-local" id="inicia_en" name="inicia_en" value="{{ old('inicia_en', $funcion->inicia_en?->format('Y-m-d\TH:i')) }}" required>
            @include('partials.campo-error', ['campo' => 'inicia_en'])
        </div>
        <div class="campo">
            <label for="precio">Precio por entrada ({{ config('teatro.moneda') }})</label>
            <input type="number" id="precio" name="precio" value="{{ old('precio', $funcion->precio) }}" min="0.5" max="10000" step="0.01" required>
            @include('partials.campo-error', ['campo' => 'precio'])
        </div>
    </div>

    <div class="formulario-admin__acciones">
        <button type="submit" class="boton">Guardar</button>
    </div>
</form>

@if ($funcion->exists && $funcion->estado === \App\Models\Funcion::PROGRAMADA)
    <form method="post" action="{{ route('admin.funciones.cancelar', $funcion) }}" class="zona-peligro">
        @csrf
        <p>Cancelar la función detiene la venta. Las órdenes ya pagadas deben reembolsarse desde Ventas.</p>
        <button type="submit" class="boton boton--peligro">Cancelar función</button>
    </form>
@endif
@if ($funcion->exists && ! $tieneVentas)
    <form method="post" action="{{ route('admin.funciones.destroy', $funcion) }}" class="zona-peligro">
        @csrf
        @method('delete')
        <button type="submit" class="boton boton--peligro">Eliminar función</button>
    </form>
@endif
@endsection
