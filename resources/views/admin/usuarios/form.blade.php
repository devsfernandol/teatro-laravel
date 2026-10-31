@extends('layouts.admin')

@section('titulo', 'Nuevo administrador')

@section('contenido')
<p class="volver"><a href="{{ route('admin.usuarios.index') }}">← Administradores</a></p>
<h1>Nuevo administrador</h1>

<form method="post" action="{{ route('admin.usuarios.store') }}" class="tarjeta formulario-admin" novalidate>
    @csrf
    <div class="campo">
        <label for="name">Nombre</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" maxlength="120" required>
        @include('partials.campo-error', ['campo' => 'name'])
    </div>
    <div class="campo">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="190" required autocomplete="off">
        @include('partials.campo-error', ['campo' => 'email'])
    </div>
    <div class="campo">
        <label for="password">Contraseña inicial</label>
        <input type="password" id="password" name="password" maxlength="200" required autocomplete="new-password">
        <p class="campo__ayuda">Al menos 12 caracteres con mayúsculas, minúsculas, números y símbolos. El nuevo administrador deberá cambiarla al entrar.</p>
        @include('partials.campo-error', ['campo' => 'password'])
    </div>
    <div class="campo">
        <label for="password_confirmation">Repita la contraseña</label>
        <input type="password" id="password_confirmation" name="password_confirmation" maxlength="200" required autocomplete="new-password">
    </div>
    <div class="formulario-admin__acciones">
        <button type="submit" class="boton">Crear</button>
    </div>
</form>
@endsection
