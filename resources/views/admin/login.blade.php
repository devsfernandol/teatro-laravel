@extends('layouts.admin')

@section('titulo', 'Iniciar sesión')

@section('contenido')
<section class="tarjeta consulta">
    <h1>Iniciar sesión</h1>
    <form method="post" action="{{ route('admin.login.store') }}" novalidate>
        @csrf
        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="190" required autofocus autocomplete="username">
        </div>
        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" maxlength="200" required autocomplete="current-password">
        </div>
        <button type="submit" class="boton boton--bloque">Entrar</button>
    </form>
</section>
@endsection
