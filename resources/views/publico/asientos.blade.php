@extends('layouts.app')

@section('titulo', 'Elegir asientos · '.$funcion->obra->titulo)

@push('scripts')
    <script src="{{ asset('js/asientos.js') }}?v={{ config('app.version') }}" defer></script>
@endpush

@section('contenido')
<p class="volver"><a href="{{ route('obras.show', $funcion->obra) }}">← Volver a {{ $funcion->obra->titulo }}</a></p>

<section class="encabezado-pagina">
    <p class="sobretitulo">Elija sus asientos para</p>
    <h1>{{ $funcion->obra->titulo }}</h1>
    <p>{{ fecha_larga($funcion->inicia_en) }} · {{ $funcion->sala->nombre }} · {{ dinero($funcion->precio) }} por entrada</p>
</section>

<form method="post" action="{{ route('funciones.reservar', $funcion) }}" class="compra-asientos" id="form-reserva"
      data-precio="{{ $funcion->precio }}" data-moneda="{{ config('teatro.moneda') }}" data-maximo="{{ $maximo }}">
    @csrf

    <section class="tarjeta compra-asientos__mapa" aria-labelledby="titulo-mapa">
        <h2 id="titulo-mapa">1. Asientos <small>(máximo {{ $maximo }})</small></h2>
        <ul class="leyenda" aria-label="Leyenda">
            <li><span class="asiento asiento--muestra" aria-hidden="true"></span> Libre</li>
            <li><span class="asiento asiento--muestra asiento--seleccionado" aria-hidden="true"></span> Elegido</li>
            <li><span class="asiento asiento--muestra asiento--ocupado" aria-hidden="true"></span> Ocupado</li>
        </ul>
        @include('partials.mapa-asientos', ['sala' => $funcion->sala, 'modo' => 'compra'])
        @include('partials.campo-error', ['campo' => 'asientos'])
    </section>

    <section class="tarjeta compra-asientos__datos" aria-labelledby="titulo-datos">
        <h2 id="titulo-datos">2. Sus datos</h2>

        <div class="campo">
            <label for="nombre">Nombre completo</label>
            <input type="text" id="nombre" name="nombre" value="{{ old('nombre') }}" maxlength="120" required autocomplete="name"
                   @error('nombre') aria-invalid="true" aria-describedby="error-nombre" @enderror>
            @include('partials.campo-error', ['campo' => 'nombre'])
        </div>

        <div class="campo">
            <label for="documento">Documento de identidad</label>
            <input type="text" id="documento" name="documento" value="{{ old('documento') }}" maxlength="30" required autocomplete="off"
                   autocapitalize="characters" spellcheck="false" class="mayusculas"
                   aria-describedby="ayuda-documento @error('documento') error-documento @enderror" @error('documento') aria-invalid="true" @enderror>
            <p class="campo__ayuda" id="ayuda-documento">Entre 5 y 20 caracteres: letras, números y guiones. Ejemplo: 1-2345-6789.</p>
            @include('partials.campo-error', ['campo' => 'documento'])
        </div>

        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="190" required autocomplete="email"
                   aria-describedby="ayuda-email @error('email') error-email @enderror" @error('email') aria-invalid="true" @enderror>
            <p class="campo__ayuda" id="ayuda-email">Le enviaremos aquí sus entradas.</p>
            @include('partials.campo-error', ['campo' => 'email'])
        </div>

        <div class="resumen-compra" aria-live="polite" id="resumen">
            <p><span id="resumen-cantidad">Elija sus asientos en el plano.</span></p>
            <p class="resumen-compra__total" hidden id="resumen-total-linea">Total: <strong id="resumen-total"></strong></p>
        </div>

        <button type="submit" class="boton boton--bloque">Reservar y continuar al pago</button>
        <p class="campo__ayuda">Los asientos quedan reservados {{ config('teatro.reserva_minutos') }} minutos mientras paga.</p>
    </section>
</form>
@endsection
