@extends('layouts.admin')

@section('titulo', 'Salas')

@section('contenido')
<div class="barra-titulo">
    <h1>Salas</h1>
    <a class="boton" href="{{ route('admin.salas.create') }}">Nueva sala</a>
</div>

<div class="tarjeta tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>Nombre</th><th>Filas × asientos</th><th>Asientos activos</th><th>Funciones</th><th><span class="solo-lectores">Acciones</span></th></tr></thead>
        <tbody>
            @forelse ($salas as $sala)
                <tr>
                    <td>{{ $sala->nombre }}</td>
                    <td>{{ $sala->filas }} × {{ $sala->columnas }}</td>
                    <td>{{ $sala->asientos_activos_count }}</td>
                    <td>{{ $sala->funciones_count }}</td>
                    <td class="tabla__acciones"><a href="{{ route('admin.salas.edit', $sala) }}">Editar plano</a></td>
                </tr>
            @empty
                <tr><td colspan="5">No hay salas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
