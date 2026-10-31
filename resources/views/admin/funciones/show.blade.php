@extends('layouts.admin')

@section('titulo', 'Plano de la función')

@section('contenido')
<p class="volver"><a href="{{ route('admin.funciones.index') }}">← Funciones</a></p>
<div class="barra-titulo">
    <div>
        <h1>{{ $funcion->obra->titulo }}</h1>
        <p>{{ fecha_larga($funcion->inicia_en) }} · {{ $funcion->sala->nombre }} · {{ dinero($funcion->precio) }}
            · <span class="estado estado--{{ $funcion->estado }}">{{ ucfirst($funcion->estado) }}</span></p>
    </div>
    <div>
        <a href="{{ route('admin.ordenes.index', ['funcion' => $funcion->id]) }}">Ver ventas</a>
        <a class="boton" href="{{ route('admin.funciones.edit', $funcion) }}">Editar</a>
    </div>
</div>

<div class="indicadores">
    <div class="tarjeta indicador"><span class="indicador__etiqueta">Vendidas</span><strong class="indicador__valor">{{ $resumen['vendidas'] }}</strong></div>
    <div class="tarjeta indicador"><span class="indicador__etiqueta">Retenidas (pago en curso)</span><strong class="indicador__valor">{{ $resumen['retenidas'] }}</strong></div>
    <div class="tarjeta indicador"><span class="indicador__etiqueta">Ingresos</span><strong class="indicador__valor">{{ dinero($resumen['ingresos']) }}</strong></div>
</div>

<section class="tarjeta">
    <ul class="leyenda" aria-label="Leyenda">
        <li><span class="asiento asiento--muestra asiento--libre" aria-hidden="true"></span> Libre</li>
        <li><span class="asiento asiento--muestra asiento--retenido" aria-hidden="true"></span> Retenido</li>
        <li><span class="asiento asiento--muestra asiento--vendido" aria-hidden="true"></span> Vendido</li>
    </ul>
    @include('partials.mapa-asientos', ['sala' => $funcion->sala, 'modo' => 'consulta'])
</section>
@endsection
