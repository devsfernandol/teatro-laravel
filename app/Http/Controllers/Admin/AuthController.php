<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const INTENTOS = 5;

    private const BLOQUEO_SEGUNDOS = 900;

    public function create(): View
    {
        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
        ]);
        $email = Str::lower($credenciales['email']);

        // Bloqueo por cuenta (5 intentos cada 15 minutos), además del límite por IP de la ruta.
        $clave = 'login-cuenta:'.hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($clave, self::INTENTOS)) {
            $minutos = (int) ceil(RateLimiter::availableIn($clave) / 60);
            throw ValidationException::withMessages([
                'email' => "Demasiados intentos fallidos. Inténtelo de nuevo en {$minutos} minutos.",
            ]);
        }

        // Duración constante: no se puede deducir por el tiempo de respuesta si el correo existe.
        $valido = (new Timebox)->call(function (Timebox $timebox) use ($email, $credenciales) {
            $ok = Auth::attempt(['email' => $email, 'password' => $credenciales['password'], 'activo' => true]);
            if ($ok) {
                $timebox->returnEarly();
            }

            return $ok;
        }, 400_000);

        if (! $valido) {
            RateLimiter::hit($clave, self::BLOQUEO_SEGUNDOS);
            Auditoria::registrar('login_fallido', 'Intento fallido para '.Str::limit($email, 100), null);

            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos.']);
        }

        RateLimiter::clear($clave);
        $request->session()->regenerate();

        $usuario = $request->user();
        $usuario->forceFill(['ultimo_acceso_en' => now(), 'ultimo_acceso_ip' => $request->ip()])->save();
        Auditoria::registrar('login', 'Inicio de sesión');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auditoria::registrar('logout', 'Cierre de sesión');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
