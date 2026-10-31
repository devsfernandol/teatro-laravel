<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('titulo') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="cabecera">
        <div class="contenedor cabecera__contenido">
            <a class="marca" href="{{ url('/') }}"><span class="marca__icono" aria-hidden="true">🎭</span><span>{{ config('app.name') }}</span></a>
        </div>
    </header>
    <main class="contenedor principal">
        <section class="tarjeta mensaje">
            <h1>@yield('titulo')</h1>
            <p>@yield('mensaje')</p>
            <a class="boton" href="{{ url('/') }}">Volver a la cartelera</a>
        </section>
    </main>
</body>
</html>
