@extends('layouts.app')

@section('titulo', $obra->titulo)
@section('descripcion', \Illuminate\Support\Str::limit($obra->sinopsis, 150))

@section('contenido')
<p class="volver"><a href="{{ route('cartelera') }}">← Volver a la cartelera</a></p>

<article class="detalle-obra">
    <div class="detalle-obra__portada">
        @include('partials.portada', ['obra' => $obra])
    </div>
    <div>
        <h1>{{ $obra->titulo }}</h1>
        <p class="etiquetas">
            <span class="etiqueta">{{ $obra->duracion_minutos }} min</span>
            @if ($obra->clasificacion)
                <span class="etiqueta">{{ $obra->clasificacion }}</span>
            @endif
        </p>
        <div class="sinopsis">{!! nl2br(e($obra->sinopsis)) !!}</div>
    </div>
</article>

<section aria-labelledby="titulo-funciones">
    <h2 id="titulo-funciones">Funciones</h2>
    @if ($funciones->isEmpty())
        <p class="tarjeta">No hay funciones a la venta para esta obra.</p>
    @else
        <ul class="funciones">
            @foreach ($funciones as $funcion)
                @php($libres = $funcion->asientosLibres())
                <li class="tarjeta funcion">
                    <div>
                        <p class="funcion__fecha"><time datetime="{{ $funcion->inicia_en->toIso8601String() }}">{{ fecha_larga($funcion->inicia_en) }}</time></p>
                        <p class="funcion__detalle">{{ $funcion->sala->nombre }} · {{ dinero($funcion->precio) }} por entrada</p>
                    </div>
                    @if ($libres > 0)
                        <div class="funcion__accion">
                            <span @class(['disponibilidad', 'disponibilidad--pocas' => $libres <= 10])>{{ $libres }} {{ $libres === 1 ? 'asiento libre' : 'asientos libres' }}</span>
                            <a class="boton" href="{{ route('funciones.asientos', $funcion) }}">Elegir asientos</a>
                        </div>
                    @else
                        <span class="disponibilidad disponibilidad--agotada">Agotada</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
