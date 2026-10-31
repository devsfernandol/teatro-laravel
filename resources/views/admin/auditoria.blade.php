@extends('layouts.admin')

@section('titulo', 'Auditoría')

@section('contenido')
<h1>Auditoría</h1>
<p>Registro de las acciones realizadas en el panel.</p>

<div class="tarjeta tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Detalle</th><th>IP</th></tr></thead>
        <tbody>
            @forelse ($registros as $r)
                <tr>
                    <td>{{ fecha_corta($r->created_at) }}</td>
                    <td>{{ $r->usuario?->email ?? '—' }}</td>
                    <td><code>{{ $r->accion }}</code></td>
                    <td>{{ $r->descripcion }}</td>
                    <td>{{ $r->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sin registros.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $registros->links('partials.paginacion') }}
@endsection
