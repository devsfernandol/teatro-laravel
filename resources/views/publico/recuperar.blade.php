@extends('layouts.app')

@section('titulo', 'Mis entradas')

@section('contenido')
<section class="tarjeta consulta" aria-labelledby="titulo-recuperar">
    <h1 id="titulo-recuperar">Recuperar mis entradas</h1>
    <p>Escriba el correo y el documento con los que compró. Le enviaremos por correo los enlaces a sus entradas.</p>

    <form method="post" action="{{ route('entradas.recuperar.enviar') }}" novalidate>
        @csrf
        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="190" required autocomplete="email">
            @include('partials.campo-error', ['campo' => 'email'])
        </div>
        <div class="campo">
            <label for="documento">Documento de identidad</label>
            <input type="text" id="documento" name="documento" value="{{ old('documento') }}" maxlength="30" required autocomplete="off" class="mayusculas">
            @include('partials.campo-error', ['campo' => 'documento'])
        </div>
        <button type="submit" class="boton boton--bloque">Enviarme los enlaces</button>
    </form>
</section>
@endsection
