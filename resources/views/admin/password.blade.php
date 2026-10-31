@extends('layouts.admin')

@section('titulo', 'Cambiar contraseña')

@section('contenido')
<section class="tarjeta consulta">
    <h1>Cambiar contraseña</h1>
    <p class="campo__ayuda">Al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos. No se aceptan contraseñas que aparezcan en filtraciones conocidas.</p>
    <form method="post" action="{{ route('admin.password.update') }}" novalidate>
        @csrf
        @method('put')
        <div class="campo">
            <label for="password_actual">Contraseña actual</label>
            <input type="password" id="password_actual" name="password_actual" maxlength="200" required autocomplete="current-password">
            @include('partials.campo-error', ['campo' => 'password_actual'])
        </div>
        <div class="campo">
            <label for="password">Nueva contraseña</label>
            <input type="password" id="password" name="password" maxlength="200" required autocomplete="new-password">
            @include('partials.campo-error', ['campo' => 'password'])
        </div>
        <div class="campo">
            <label for="password_confirmation">Repita la nueva contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation" maxlength="200" required autocomplete="new-password">
        </div>
        <button type="submit" class="boton boton--bloque">Guardar</button>
    </form>
</section>
@endsection
