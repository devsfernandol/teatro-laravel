<?php

use App\Http\Middleware\AdminActivo;
use App\Http\Middleware\CabecerasDeSeguridad;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Solo se confía en X-Forwarded-For/Proto cuando hay un proxy con HTTPS
        // delante (Caddy o Traefik), en una red privada de Docker. Sin proxy,
        // TRUSTED_PROXIES queda vacío y un cliente no puede falsear su IP.
        // El host nunca se toma de la petición: las URL salen de APP_URL.
        $proxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))));
        if ($proxies !== []) {
            $middleware->trustProxies(
                at: $proxies,
                headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
            );
        }
        $middleware->append(CabecerasDeSeguridad::class);
        $middleware->authenticateSessions();
        $middleware->alias(['admin' => AdminActivo::class]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
        $middleware->preventRequestForgery(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_actual', 'password_confirmation', 'documento']);
    })->create();
