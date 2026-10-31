<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PruebaCorreo;
use App\Models\Auditoria;
use App\Services\PayPal\PayPalClient;
use App\Services\PayPal\PayPalException;
use App\Support\Ajustes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Pasarela de pago y correo, editables desde el panel. Los secretos nunca se
 * devuelven al navegador: si el campo se deja vacío, se conserva el guardado.
 */
class ConfiguracionController extends Controller
{
    public function edit(PayPalClient $paypal): View
    {
        return view('admin.configuracion', [
            'modoPagos' => Ajustes::modoPagos(),
            'paypalConfigurado' => $paypal->configurado(),
            'paypal' => [
                'client_id' => config('services.paypal.client_id'),
                'mode' => config('services.paypal.mode') === 'live' ? 'live' : 'sandbox',
                'webhook_id' => config('services.paypal.webhook_id'),
                'tiene_secret' => filled(config('services.paypal.secret')),
            ],
            'correo' => [
                'mailer' => config('mail.default') === 'smtp' ? 'smtp' : 'log',
                'host' => config('mail.mailers.smtp.host'),
                'port' => (int) (config('mail.mailers.smtp.port') ?: 587),
                'cifrado' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls',
                'usuario' => config('mail.mailers.smtp.username'),
                'tiene_password' => filled(config('mail.mailers.smtp.password')),
                'remitente' => config('mail.from.address'),
                'remitente_nombre' => config('mail.from.name'),
            ],
            'urlWebhook' => route('webhooks.paypal'),
        ]);
    }

    public function actualizarPagos(Request $request, PayPalClient $paypal): RedirectResponse
    {
        $datos = $request->validate([
            'modo' => ['required', Rule::in(['demo', 'paypal'])],
            'paypal_mode' => ['required', Rule::in(['sandbox', 'live'])],
            'paypal_client_id' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/'],
            'paypal_secret' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_-]+$/'],
            'paypal_webhook_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
            'borrar_credenciales' => ['nullable', 'boolean'],
        ], [
            'paypal_client_id.regex' => 'El Client ID solo contiene letras, números, guiones y guiones bajos.',
            'paypal_secret.regex' => 'El Secret solo contiene letras, números, guiones y guiones bajos.',
            'paypal_webhook_id.regex' => 'El ID del webhook solo contiene letras, números y guiones.',
        ]);

        if ($request->boolean('borrar_credenciales')) {
            Ajustes::guardar([
                'pagos.modo' => 'demo',
                'paypal.client_id' => null,
                'paypal.secret' => null,
                'paypal.webhook_id' => null,
            ]);
            Auditoria::registrar('config_pagos', 'Credenciales de PayPal borradas; modo demostración');
            $this->reiniciarCola();

            return redirect()->route('admin.configuracion.edit')->with('exito', 'Credenciales de PayPal borradas. La tienda quedó en modo demostración.');
        }

        // Si el Secret se deja vacío se conserva el que ya estaba guardado.
        $clientId = $datos['paypal_client_id'] ?? null;
        $secret = filled($datos['paypal_secret'] ?? null) ? $datos['paypal_secret'] : config('services.paypal.secret');

        if ($datos['modo'] === 'paypal') {
            if (blank($clientId) || blank($secret)) {
                throw ValidationException::withMessages(['modo' => 'Para activar PayPal complete el Client ID y el Secret.']);
            }
            try {
                $paypal->verificarCredenciales($clientId, $secret, $datos['paypal_mode']);
            } catch (PayPalException $e) {
                throw ValidationException::withMessages(['paypal_client_id' => $e->getMessage()]);
            }
        }

        $cambios = [
            'pagos.modo' => $datos['modo'],
            'paypal.mode' => $datos['paypal_mode'],
            'paypal.client_id' => $clientId,
            'paypal.webhook_id' => $datos['paypal_webhook_id'] ?? null,
        ];
        if (filled($datos['paypal_secret'] ?? null)) {
            $cambios['paypal.secret'] = $datos['paypal_secret'];
        }
        Ajustes::guardar($cambios);

        $descripcion = $datos['modo'] === 'paypal'
            ? 'Pagos con PayPal activados ('.($datos['paypal_mode'] === 'live' ? 'Live: cobros reales' : 'Sandbox: pruebas').')'
            : 'Pagos en modo demostración';
        Auditoria::registrar('config_pagos', $descripcion);
        $this->reiniciarCola();

        return redirect()->route('admin.configuracion.edit')->with('exito', $descripcion.'.');
    }

    public function probarPayPal(PayPalClient $paypal): RedirectResponse
    {
        if (! $paypal->configurado()) {
            return back()->withErrors(['paypal_client_id' => 'Primero guarde el Client ID y el Secret.']);
        }

        try {
            $paypal->verificarCredenciales(
                (string) config('services.paypal.client_id'),
                (string) config('services.paypal.secret'),
                config('services.paypal.mode') === 'live' ? 'live' : 'sandbox',
            );
        } catch (PayPalException $e) {
            return back()->withErrors(['paypal_client_id' => $e->getMessage()]);
        }

        return back()->with('exito', 'Conexión con PayPal correcta.');
    }

    public function actualizarCorreo(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'mailer' => ['required', Rule::in(['log', 'smtp'])],
            'host' => ['nullable', 'required_if:mailer,smtp', 'string', 'max:190', 'regex:/^[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?)+$/'],
            'port' => ['nullable', 'required_if:mailer,smtp', 'integer', Rule::in([25, 465, 587, 2525])],
            'cifrado' => ['required', Rule::in(['tls', 'ssl'])],
            'usuario' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:200'],
            'remitente' => ['required', 'string', 'email:rfc', 'max:190'],
            'remitente_nombre' => ['nullable', 'string', 'max:100', "regex:/^[\\pL\\pM\\pN' .,&-]+$/u"],
        ], [
            'host.required_if' => 'Escriba el servidor SMTP, por ejemplo smtp.gmail.com.',
            'port.required_if' => 'Elija el puerto del servidor SMTP.',
            'host.regex' => 'Escriba el nombre del servidor SMTP, por ejemplo smtp.gmail.com.',
            'port.in' => 'El puerto debe ser 587 (STARTTLS), 465 (SSL), 25 o 2525.',
        ]);

        $cambios = [
            'correo.mailer' => $datos['mailer'],
            'correo.host' => $datos['host'] ?? null,
            'correo.port' => isset($datos['port']) ? (string) $datos['port'] : null,
            'correo.cifrado' => $datos['cifrado'],
            'correo.usuario' => $datos['usuario'] ?? null,
            'correo.remitente' => strtolower($datos['remitente']),
            'correo.remitente_nombre' => $datos['remitente_nombre'] ?? null,
        ];
        if (filled($datos['password'] ?? null)) {
            $cambios['correo.password'] = $datos['password'];
        }
        if ($request->boolean('borrar_password')) {
            $cambios['correo.password'] = null;
        }
        Ajustes::guardar($cambios);

        Auditoria::registrar('config_correo', $datos['mailer'] === 'smtp' ? "Correo SMTP: {$datos['host']}:{$datos['port']}" : 'Correo desactivado (solo registro)');
        $this->reiniciarCola();

        return redirect()->route('admin.configuracion.edit')
            ->with('exito', 'Configuración de correo guardada. Envíe un correo de prueba para comprobarla.');
    }

    public function probarCorreo(Request $request): RedirectResponse
    {
        $datos = $request->validate(['destino' => ['required', 'string', 'email:rfc', 'max:190']]);

        if (config('mail.default') !== 'smtp') {
            return back()->withErrors(['destino' => 'El envío de correos está desactivado. Elija SMTP y guarde la configuración.']);
        }

        try {
            Mail::to($datos['destino'])->send(new PruebaCorreo);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['destino' => 'No se pudo enviar: '.Str::limit($e->getMessage(), 300)]);
        }

        Auditoria::registrar('config_correo', 'Correo de prueba enviado a '.$datos['destino']);

        return back()->with('exito', "Correo de prueba enviado a {$datos['destino']}. Revise la bandeja de entrada (y la de spam).");
    }

    /**
     * El proceso de la cola (que envía los correos) se reinicia para que tome
     * la configuración nueva.
     */
    private function reiniciarCola(): void
    {
        Artisan::call('queue:restart');
    }
}
