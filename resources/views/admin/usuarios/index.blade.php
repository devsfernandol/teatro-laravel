@extends('layouts.admin')

@section('titulo', 'Usuarios')

@section('contenido')
<div class="barra-titulo">
    <h1>Administradores</h1>
    <a class="boton" href="{{ route('admin.usuarios.create') }}">Nuevo administrador</a>
</div>

<div class="tarjeta tabla-contenedor">
    <table class="tabla">
        <thead><tr><th>Nombre</th><th>Correo</th><th>Último acceso</th><th>Estado</th><th><span class="solo-lectores">Acciones</span></th></tr></thead>
        <tbody>
            @foreach ($usuarios as $u)
                <tr>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->ultimo_acceso_en ? fecha_corta($u->ultimo_acceso_en).' · '.$u->ultimo_acceso_ip : 'Nunca' }}</td>
                    <td>{{ $u->activo ? 'Activo' : 'Desactivado' }}</td>
                    <td class="tabla__acciones">
                        @if ($u->id !== auth()->id())
                            <form method="post" action="{{ route('admin.usuarios.activo', $u) }}">
                                @csrf
                                <button type="submit" class="boton-enlace boton-enlace--oscuro">{{ $u->activo ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
