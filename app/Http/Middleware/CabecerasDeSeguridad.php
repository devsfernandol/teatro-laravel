<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad para todas las respuestas.
 *
 * La política de contenido (CSP) no permite scripts ni estilos en línea, ni de
 * otros dominios: el sitio solo carga sus propios archivos. PayPal aparece solo
 * en form-action, porque el botón de pago redirige allí.
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self' https://www.paypal.com https://www.sandbox.paypal.com",
            "frame-ancestors 'none'",
            "base-uri 'none'",
            "object-src 'none'",
        ]);

        $h = $respuesta->headers;
        $h->set('Content-Security-Policy', $csp);
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', $h->get('Referrer-Policy') ?? 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->set('Cross-Origin-Resource-Policy', 'same-origin');
        $h->remove('X-Powered-By');

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Disponibilidad y datos personales: nada se guarda en cachés intermedias.
        if (! $h->has('Cache-Control') || str_contains((string) $h->get('Cache-Control'), 'no-cache')) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $respuesta;
    }
}
