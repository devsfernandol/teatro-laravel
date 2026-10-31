<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Crea el primer administrador si todavía no hay ninguno. Si no se indica
 * contraseña, genera una aleatoria y la muestra una sola vez. En ambos casos
 * se exige cambiarla en el primer inicio de sesión.
 */
class CrearAdmin extends Command
{
    protected $signature = 'teatro:crear-admin
        {--email= : Correo del administrador}
        {--password= : Contraseña inicial (si se omite, se genera una)}
        {--si-no-existe : No hacer nada si ya hay administradores}';

    protected $description = 'Crea un administrador del panel';

    public function handle(): int
    {
        if ($this->option('si-no-existe') && User::exists()) {
            return self::SUCCESS;
        }

        $email = strtolower(trim((string) ($this->option('email') ?: 'admin@teatro.local')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Correo inválido.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error("Ya existe un administrador con el correo {$email}.");

            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        $generada = $password === '';
        if ($generada) {
            $password = Str::password(20, symbols: false);
        }

        $usuario = User::create([
            'name' => 'Administrador',
            'email' => $email,
            'password' => $password,
            'activo' => true,
            'debe_cambiar_password' => true,
        ]);
        Auditoria::registrar('usuario_creado', "Administrador inicial #{$usuario->id}: {$email}", null);

        $this->newLine();
        $this->line('  Administrador creado');
        $this->line("  Correo:     {$email}");
        if ($generada) {
            $this->line("  Contraseña: {$password}");
        }
        $this->line('  Deberá cambiar la contraseña en el primer inicio de sesión.');
        $this->newLine();

        return self::SUCCESS;
    }
}
