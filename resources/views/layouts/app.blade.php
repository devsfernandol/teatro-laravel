<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') · {{ config('app.name') }}</title>
    <meta name="description" content="@yield('descripcion', 'Compre sus entradas de teatro en línea y elija sus asientos.')">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('app.version') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @stack('head')
</head>
<body>
    <a class="saltar" href="#contenido">Saltar al contenido</a>
    <header class="cabecera">
        <div class="contenedor cabecera__contenido">
            <a class="marca" href="{{ route('cartelera') }}">
                <span class="marca__icono" aria-hidden="true">🎭</span>
                <span>{{ config('app.name') }}</span>
            </a>
            <nav aria-label="Principal">
                <a class="cabecera__enlace" href="{{ route('cartelera') }}">Cartelera</a>
                <a class="cabecera__enlace" href="{{ route('entradas.recuperar') }}">Mis entradas</a>
            </nav>
        </div>
    </header>

    @if (app(\App\Services\PagoService::class)->modoDemo())
        <div class="banda-demo" role="note">
            <div class="contenedor">
                <strong>Modo demostración:</strong> los pagos son simulados y no se cobra nada.
            </div>
        </div>
    @endif

    <main class="contenedor principal" id="contenido">
        @include('partials.mensajes')
        @yield('contenido')
    </main>

    <footer class="pie">
        <div class="contenedor">
            <p>{{ config('app.name') }} · Venta de entradas en línea</p>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
