<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo') · Administración</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.version') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
</head>
<body class="admin">
    <a class="saltar" href="#contenido">Saltar al contenido</a>
    <header class="cabecera cabecera--admin">
        <div class="contenedor contenedor--ancho cabecera__contenido">
            <a class="marca" href="{{ route('admin.dashboard') }}">
                <span class="marca__icono" aria-hidden="true">🎭</span>
                <span>Administración</span>
            </a>
            @auth
                <nav aria-label="Administración" class="admin-nav">
                    <a @class(['cabecera__enlace', 'activo' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}">Inicio</a>
                    <a @class(['cabecera__enlace', 'activo' => request()->routeIs('admin.obras.*')]) href="{{ route('admin.obras.index') }}">Obras</a>
                    <a @class(['cabecera__enlace', 'activo' => request()->routeIs('admin.funciones.*')]) href="{{ route('admin.funciones.index') }}">Funciones</a>
                    <a @class(['cabecera__enlace', 'activo' => request()->routeIs('admin.salas.*')]) href="{{ route('admin.salas.index') }}">Salas</a>
                    <a @class(['cabecera__enlace', 'activo' => request()->routeIs('admin.ordenes.*')]) href="{{ route('admin.ordenes.index') }}">Ventas</a>
                    <a @class(['cabecera__enlace', 'activo' => request()->routeIs('admin.usuarios.*')]) href="{{ route('admin.usuarios.index') }}">Usuarios</a>
                    <a @class(['cabecera__enlace', 'activo' => request()->routeIs('admin.auditoria.*')]) href="{{ route('admin.auditoria.index') }}">Auditoría</a>
                    <a @class(['cabecera__enlace', 'activo' => request()->routeIs('admin.configuracion.*')]) href="{{ route('admin.configuracion.edit') }}">Configuración</a>
                    <a class="cabecera__enlace" href="{{ route('cartelera') }}" target="_blank" rel="noopener">Ver sitio ↗</a>
                </nav>
                <div class="admin-usuario">
                    <a class="cabecera__enlace" href="{{ route('admin.password.edit') }}">{{ auth()->user()->name }}</a>
                    <form method="post" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="boton-enlace">Salir</button>
                    </form>
                </div>
            @endauth
        </div>
    </header>

    @if (app(\App\Services\PagoService::class)->modoDemo())
        <div class="banda-demo" role="note">
            <div class="contenedor contenedor--ancho">
                <strong>Modo demostración:</strong> los pagos se simulan.
                @auth Para cobrar de verdad, configure PayPal en <a href="{{ route('admin.configuracion.edit') }}">Configuración</a>. @endauth
            </div>
        </div>
    @endif

    <main class="contenedor contenedor--ancho principal" id="contenido">
        @include('partials.mensajes')
        @yield('contenido')
    </main>
    @stack('scripts')
</body>
</html>
