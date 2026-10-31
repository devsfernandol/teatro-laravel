@extends('layouts.admin')

@section('titulo', 'Ventas')

@section('contenido')
<div class="barra-titulo">
    <h1>Ventas</h1>
    <a class="boton boton--secundario" href="{{ route('admin.ordenes.exportar', request()->query()) }}">Exportar CSV</a>
</div>

<form method="get" class="tarjeta filtros">
    <div class="campo">
        <label for="q">Buscar</label>
        <input type="search" id="q" name="q" value="{{ request('q') }}" maxlength="100" placeholder="N.º de orden, documento, correo o código">
    </div>
    <div class="campo">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            @foreach ($estados as $estado)
                <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ ucfirst($estado) }}</option>
            @endforeach
        </select>
    </div>
    @if (request('funcion'))
        <input type="hidden" name="funcion" value="{{ (int) request('funcion') }}">
    @endif
    <button type="submit" class="boton">Filtrar</button>
    @if (request()->hasAny(['q', 'estado', 'funcion']))
        <a href="{{ route('admin.ordenes.index') }}">Quitar filtros</a>
    @endif
</form>

<div class="tarjeta tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>Orden</th><th>Fecha</th><th>Obra</th><th>Comprador</th><th>Total</th><th>Estado</th></tr></thead>
        <tbody>
            @forelse ($ordenes as $o)
                <tr>
                    <td><a href="{{ route('admin.ordenes.show', $o) }}">#{{ $o->id }}</a></td>
                    <td>{{ fecha_corta($o->created_at) }}</td>
                    <td>{{ $o->funcion->obra->titulo }}<br><small>{{ fecha_corta($o->funcion->inicia_en) }}</small></td>
                    <td>{{ $o->nombre }}<br><small>{{ $o->documento }}</small></td>
                    <td>{{ dinero($o->total, $o->moneda) }}</td>
                    <td><span class="estado estado--{{ $o->estado }}">{{ $o->etiquetaEstado() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6">No hay ventas con esos filtros.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $ordenes->links('partials.paginacion') }}
@endsection
