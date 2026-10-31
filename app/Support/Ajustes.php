<?php

namespace App\Support;

use App\Models\Ajuste;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ajustes guardados desde el panel. Tienen prioridad sobre las variables de
 * entorno (.env), que quedan como valores iniciales opcionales.
 */
final class Ajustes
{
    private const CACHE = 'ajustes.todos';

    /** Claves cuyo valor se guarda cifrado. */
    private const SECRETAS = ['paypal.secret', 'correo.password'];

    /** @var array<string, string|null>|null */
    private static ?array $memoria = null;

    /**
     * @return array<string, string|null>
     */
    public static function todos(): array
    {
        return self::$memoria ??= Cache::rememberForever(self::CACHE, fn () => Ajuste::pluck('valor', 'clave')->all());
    }

    public static function obtener(string $clave, ?string $porDefecto = null): ?string
    {
        $valor = self::todos()[$clave] ?? null;
        if ($valor === null || $valor === '') {
            return $porDefecto;
        }

        if (in_array($clave, self::SECRETAS, true)) {
            try {
                return Crypt::decryptString($valor);
            } catch (DecryptException) {
                // La clave de la aplicación cambió: el secreto ya no se puede leer.
                Log::warning("El ajuste {$clave} no se pudo descifrar; vuelva a guardarlo en el panel.");

                return $porDefecto;
            }
        }

        return $valor;
    }

    public static function tieneValor(string $clave): bool
    {
        return filled(self::todos()[$clave] ?? null);
    }

    /**
     * Guarda varios ajustes. Un valor null borra el ajuste.
     *
     * @param  array<string, string|null>  $valores
     */
    public static function guardar(array $valores): void
    {
        foreach ($valores as $clave => $valor) {
            if ($valor === null || $valor === '') {
                Ajuste::whereKey($clave)->delete();

                continue;
            }
            if (in_array($clave, self::SECRETAS, true)) {
                $valor = Crypt::encryptString($valor);
            }
            Ajuste::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        }

        self::olvidar();
        self::aplicar();
    }

    public static function olvidar(): void
    {
        self::$memoria = null;
        Cache::forget(self::CACHE);
    }

    /**
     * "demo" (pagos simulados) o "paypal". Si nunca se eligió en el panel,
     * se usa PayPal solo cuando hay credenciales.
     */
    public static function modoPagos(): string
    {
        $modo = self::obtener('pagos.modo');
        if ($modo === 'demo' || $modo === 'paypal') {
            return $modo;
        }

        return filled(config('services.paypal.client_id')) && filled(config('services.paypal.secret')) ? 'paypal' : 'demo';
    }

    /**
     * Traslada los ajustes del panel a la configuración de Laravel. Se llama al
     * arrancar cada petición o comando, y después de guardar.
     */
    public static function aplicar(): void
    {
        // Cada arranque de la aplicación vuelve a leer los ajustes.
        self::$memoria = null;

        try {
            self::todos();
        } catch (Throwable) {
            // Base de datos no disponible o sin migrar (instalación, comandos de consola).
            self::$memoria = null;

            return;
        }

        $paypal = array_filter([
            'services.paypal.client_id' => self::obtener('paypal.client_id'),
            'services.paypal.secret' => self::obtener('paypal.secret'),
            'services.paypal.mode' => self::obtener('paypal.mode'),
            'services.paypal.webhook_id' => self::obtener('paypal.webhook_id'),
        ], fn ($v) => $v !== null);
        config($paypal);

        $mailer = self::obtener('correo.mailer');
        if ($mailer === 'smtp') {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => self::obtener('correo.host'),
                'mail.mailers.smtp.port' => (int) self::obtener('correo.port', '587'),
                'mail.mailers.smtp.scheme' => self::obtener('correo.cifrado') === 'ssl' ? 'smtps' : 'smtp',
                'mail.mailers.smtp.username' => self::obtener('correo.usuario'),
                'mail.mailers.smtp.password' => self::obtener('correo.password'),
                'mail.mailers.smtp.timeout' => 15,
            ]);
        } elseif ($mailer === 'log') {
            config(['mail.default' => 'log']);
        }

        if (($remitente = self::obtener('correo.remitente')) !== null) {
            config(['mail.from.address' => $remitente]);
        }
        if (($nombre = self::obtener('correo.remitente_nombre')) !== null) {
            config(['mail.from.name' => $nombre]);
        }
    }
}
