<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('admin.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'password_actual' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'max:200', 'confirmed', 'different:password_actual', Password::defaults()],
        ]);

        $usuario = $request->user();
        $usuario->forceFill(['password' => $datos['password'], 'debe_cambiar_password' => false])->save();

        // Cierra las sesiones abiertas en otros dispositivos.
        Auth::logoutOtherDevices($datos['password']);
        $request->session()->regenerate();

        Auditoria::registrar('password', 'Cambio de contraseña');

        return redirect()->route('admin.dashboard')->with('exito', 'Contraseña actualizada.');
    }
}
