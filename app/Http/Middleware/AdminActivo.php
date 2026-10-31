<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de un administrador desactivado y obliga a cambiar la
 * contraseña inicial antes de usar el panel.
 */
class AdminActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario === null || ! $usuario->activo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors(['email' => 'Su cuenta no está activa.']);
        }

        if ($usuario->debe_cambiar_password && ! $request->routeIs('admin.password.*', 'admin.logout')) {
            return redirect()->route('admin.password.edit')
                ->with('aviso', 'Por seguridad, cambie la contraseña inicial antes de continuar.');
        }

        $respuesta = $next($request);
        $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $respuesta;
    }
}
