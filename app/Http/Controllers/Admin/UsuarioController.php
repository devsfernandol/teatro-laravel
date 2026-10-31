<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function index(): View
    {
        return view('admin.usuarios.index', ['usuarios' => User::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.usuarios.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'max:200', 'confirmed', Password::defaults()],
        ]);

        $usuario = User::create([
            'name' => $datos['name'],
            'email' => strtolower($datos['email']),
            'password' => $datos['password'],
            'activo' => true,
            // La contraseña la eligió otra persona: el nuevo administrador debe cambiarla.
            'debe_cambiar_password' => true,
        ]);
        Auditoria::registrar('usuario_creado', "Administrador #{$usuario->id}: {$usuario->email}");

        return redirect()->route('admin.usuarios.index')->with('exito', 'Administrador creado. Deberá cambiar la contraseña al entrar.');
    }

    public function alternarActivo(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->id === $request->user()->id) {
            return back()->withErrors(['usuario' => 'No puede desactivar su propia cuenta.']);
        }

        $usuario->update(['activo' => ! $usuario->activo]);
        Auditoria::registrar($usuario->activo ? 'usuario_activado' : 'usuario_desactivado', "Administrador #{$usuario->id}: {$usuario->email}");

        return back()->with('exito', $usuario->activo ? 'Administrador activado.' : 'Administrador desactivado: sus sesiones quedan cerradas.');
    }
}
