@extends('layouts.admin')

@section('titulo', 'Obras')

@section('contenido')
<div class="barra-titulo">
    <h1>Obras</h1>
    <a class="boton" href="{{ route('admin.obras.create') }}">Nueva obra</a>
</div>

<div class="tarjeta tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>Título</th><th>Duración</th><th>Funciones</th><th>Estado</th><th><span class="solo-lectores">Acciones</span></th></tr></thead>
        <tbody>
            @forelse ($obras as $obra)
                <tr>
                    <td>{{ $obra->titulo }}</td>
                    <td>{{ $obra->duracion_minutos }} min</td>
                    <td>{{ $obra->funciones_count }}</td>
                    <td>{{ $obra->publicada ? 'Publicada' : 'Oculta' }}</td>
                    <td class="tabla__acciones">
                        <a href="{{ route('admin.obras.edit', $obra) }}">Editar</a>
                        <a href="{{ route('admin.funciones.create', ['obra' => $obra->id]) }}">Programar función</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No hay obras.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $obras->links('partials.paginacion') }}
@endsection
