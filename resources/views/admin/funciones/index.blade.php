@extends('layouts.admin')

@section('titulo', 'Funciones')

@section('contenido')
<div class="barra-titulo">
    <h1>Funciones</h1>
    <div>
        @if ($verPasadas)
            <a href="{{ route('admin.funciones.index') }}">Ver próximas</a>
        @else
            <a href="{{ route('admin.funciones.index', ['pasadas' => 1]) }}">Ver todas (incluidas pasadas)</a>
        @endif
        <a class="boton" href="{{ route('admin.funciones.create') }}">Programar función</a>
    </div>
</div>

<div class="tarjeta tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Obra</th><th>Sala</th><th>Precio</th><th>Ocupación</th><th>Estado</th><th><span class="solo-lectores">Acciones</span></th></tr></thead>
        <tbody>
            @forelse ($funciones as $f)
                <tr>
                    <td>{{ fecha_corta($f->inicia_en) }}</td>
                    <td>{{ $f->obra->titulo }}</td>
                    <td>{{ $f->sala->nombre }}</td>
                    <td>{{ dinero($f->precio) }}</td>
                    <td>{{ $f->entradas_count }} / {{ $f->sala->asientos_activos_count }}</td>
                    <td><span class="estado estado--{{ $f->estado }}">{{ ucfirst($f->estado) }}</span></td>
                    <td class="tabla__acciones">
                        <a href="{{ route('admin.funciones.show', $f) }}">Plano</a>
                        <a href="{{ route('admin.funciones.edit', $f) }}">Editar</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No hay funciones.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $funciones->links('partials.paginacion') }}
@endsection
