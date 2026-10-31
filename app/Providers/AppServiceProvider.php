<?php

namespace App\Providers;

use App\Support\Ajustes;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('es');

        // Ajustes del panel (PayPal y correo). No se aplican al generar la caché
        // de configuración, para que esa caché no guarde secretos ni valores viejos.
        if (! $this->app->runningConsoleCommand('config:cache')) {
            Ajustes::aplicar();
        }

        // Todas las URL (incluidas las de retorno de PayPal y las de los correos)
        // se generan a partir de APP_URL, nunca de la cabecera Host de la petición.
        $urlBase = (string) config('app.url');
        if ($urlBase !== '' && ! $this->app->runningUnitTests()) {
            URL::forceRootUrl($urlBase);
            if (str_starts_with($urlBase, 'https://')) {
                URL::forceScheme('https');
            }
        }

        // En desarrollo, detiene consultas N+1 y asignaciones de atributos inexistentes.
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());

        $this->configurarLimites();
    }

    private function configurarLimites(): void
    {
        RateLimiter::for('global', fn (Request $r) => Limit::perMinute(240)->by($r->ip()));

        RateLimiter::for('reservas', fn (Request $r) => [
            Limit::perMinute(10)->by('min:'.$r->ip()),
            Limit::perHour(60)->by('hora:'.$r->ip()),
        ]);

        RateLimiter::for('pagos', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(10)->by('ip:'.$r->ip()),
            Limit::perHour(100)->by('ip-hora:'.$r->ip()),
        ]);

        RateLimiter::for('recuperar', fn (Request $r) => [
            Limit::perMinute(3)->by('ip:'.$r->ip()),
            Limit::perHour(10)->by('email:'.strtolower((string) $r->input('email'))),
        ]);

        // Las pruebas de PayPal y SMTP conectan con servidores externos.
        RateLimiter::for('configuracion', fn (Request $r) => Limit::perMinute(30)->by('config:'.($r->user()?->id ?? $r->ip())));

        RateLimiter::for('webhooks', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
    }
}
