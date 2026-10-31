@extends('layouts.app')

@section('titulo', 'Cartelera')

@section('contenido')
<section class="encabezado-pagina">
    <h1>Cartelera</h1>
    <p>Elija una obra, una función y sus asientos.</p>
</section>

@if ($obras->isEmpty())
    <section class="tarjeta mensaje">
        <h2>No hay obras disponibles</h2>
        <p>En este momento no hay funciones con entradas a la venta. Vuelva a consultar más adelante.</p>
    </section>
@else
    <ul class="cartelera">
        @foreach ($obras as $obra)
            @php($proxima = $obra->funciones->first())
            <li class="tarjeta obra">
                @include('partials.portada', ['obra' => $obra])
                <div class="obra__cuerpo">
                    <h2 class="obra__nombre">{{ $obra->titulo }}</h2>
                    <dl class="obra__datos">
                        <div>
                            <dt>Próxima función</dt>
                            <dd><time datetime="{{ $proxima->inicia_en->toIso8601String() }}">{{ fecha_larga($proxima->inicia_en) }}</time></dd>
                        </div>
                        <div>
                            <dt>Funciones con entradas</dt>
                            <dd>{{ $obra->funciones->count() }}</dd>
                        </div>
                        <div>
                            <dt>Desde</dt>
                            <dd>{{ dinero($obra->funciones->min('precio')) }}</dd>
                        </div>
                    </dl>
                    <a class="boton obra__accion" href="{{ route('obras.show', $obra) }}">
                        Ver funciones<span class="solo-lectores"> de {{ $obra->titulo }}</span>
                    </a>
                </div>
            </li>
        @endforeach
    </ul>
@endif
@endsection
