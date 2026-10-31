@extends('layouts.admin')

@section('titulo', 'Inicio')

@section('contenido')
<h1>Resumen</h1>

<div class="indicadores">
    <div class="tarjeta indicador"><span class="indicador__etiqueta">Ventas de hoy</span><strong class="indicador__valor">{{ $ventasHoy }}</strong></div>
    <div class="tarjeta indicador"><span class="indicador__etiqueta">Ingresos de hoy</span><strong class="indicador__valor">{{ dinero($ingresosHoy) }}</strong></div>
    <div class="tarjeta indicador"><span class="indicador__etiqueta">Ingresos totales</span><strong class="indicador__valor">{{ dinero($ingresosTotales) }}</strong></div>
    <div class="tarjeta indicador"><span class="indicador__etiqueta">Entradas vendidas</span><strong class="indicador__valor">{{ $entradasVendidas }}</strong></div>
</div>

<div class="dos-columnas">
    <section class="tarjeta">
        <h2>Próximas funciones</h2>
        @if ($proximas->isEmpty())
            <p>No hay funciones programadas. <a href="{{ route('admin.funciones.create') }}">Programar una</a>.</p>
        @else
            <table class="tabla">
                <thead><tr><th>Obra</th><th>Fecha</th><th>Ocupación</th></tr></thead>
                <tbody>
                    @foreach ($proximas as $f)
                        @php($total = $f->sala->asientos_activos_count)
                        <tr>
                            <td><a href="{{ route('admin.funciones.show', $f) }}">{{ $f->obra->titulo }}</a></td>
                            <td>{{ fecha_corta($f->inicia_en) }}</td>
                            <td>{{ $f->entradas_count }} / {{ $total }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="tarjeta">
        <h2>Últimas ventas</h2>
        @if ($ultimas->isEmpty())
            <p>Todavía no hay ventas.</p>
        @else
            <table class="tabla">
                <thead><tr><th>Orden</th><th>Obra</th><th>Total</th><th>Estado</th></tr></thead>
                <tbody>
                    @foreach ($ultimas as $o)
                        <tr>
                            <td><a href="{{ route('admin.ordenes.show', $o) }}">#{{ $o->id }}</a></td>
                            <td>{{ $o->funcion->obra->titulo }}</td>
                            <td>{{ dinero($o->total, $o->moneda) }}</td>
                            <td><span class="estado estado--{{ $o->estado }}">{{ $o->etiquetaEstado() }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
@endsection
