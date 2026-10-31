<?php

namespace App\Services;

use App\Exceptions\VentaException;
use App\Mail\EntradasCompradas;
use App\Models\Orden;
use App\Services\PayPal\PayPalClient;
use App\Services\PayPal\PayPalException;
use App\Support\Ajustes;
use App\Support\Codigo;
use App\Support\Dinero;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Cobro de las órdenes.
 *
 * El modo se elige en el panel (Configuración). En modo demostración el pago
 * se simula; en modo PayPal el pago simulado queda desactivado por completo en
 * el servidor. Sin credenciales de PayPal siempre se trabaja en demostración.
 *
 * Flujo con PayPal (redirección, sin JavaScript de terceros en el sitio):
 *  1. Se crea la orden en PayPal y se envía al comprador a aprobarla.
 *  2. PayPal lo devuelve a la tienda; la orden pasa a "procesando" y se captura.
 *  3. Si la captura se confirma, la orden queda "pagada". Si la conexión falla
 *     a mitad de camino, la tarea programada vuelve a consultar a PayPal.
 */
class PagoService
{
    public const RESULTADO_PAGADA = 'pagada';

    public const RESULTADO_PROCESANDO = 'procesando';

    public const RESULTADO_RECHAZADA = 'rechazada';

    public const RESULTADO_EXPIRADA = 'expirada';

    public function __construct(private readonly PayPalClient $paypal)
    {
    }

    public function modoDemo(): bool
    {
        return ! $this->paypal->configurado() || Ajustes::modoPagos() !== 'paypal';
    }

    /**
     * Hay credenciales de PayPal. Aunque el panel esté en modo demostración,
     * los pagos que ya se iniciaron en PayPal deben poder terminar.
     */
    public function paypalDisponible(): bool
    {
        return $this->paypal->configurado();
    }

    public function pagarDemo(Orden $orden): Orden
    {
        if (! $this->modoDemo()) {
            throw new VentaException('El pago simulado no está disponible.');
        }

        return $this->marcarPagada($orden, 'demo', null, exigirVigente: true);
    }

    /**
     * Crea (o recupera) la orden en PayPal y devuelve la URL de aprobación.
     */
    public function iniciarPayPal(Orden $orden, string $urlRetorno, string $urlCancelacion): string
    {
        $orden = Orden::findOrFail($orden->getKey());
        if (! $orden->puedePagarse()) {
            throw new VentaException('La reserva ya no puede pagarse.');
        }

        try {
            $resultado = $this->paypal->crearOrden($orden, $urlRetorno, $urlCancelacion);
        } catch (PayPalException $e) {
            Log::error('PayPal: no se pudo crear la orden', ['orden' => $orden->uuid, 'error' => $e->getMessage()]);
            throw new VentaException('No pudimos comunicarnos con PayPal. Inténtelo de nuevo en unos minutos.');
        }

        DB::transaction(function () use ($orden, $resultado) {
            $orden = Orden::whereKey($orden->getKey())->lockForUpdate()->firstOrFail();
            if ($orden->estado !== Orden::PENDIENTE) {
                throw new VentaException('La reserva ya no puede pagarse.');
            }
            if ($orden->paypal_order_id === null) {
                // La primera vez que va a PayPal se le da el tiempo completo para pagar.
                $orden->update([
                    'paypal_order_id' => $resultado['id'],
                    'metodo_pago' => 'paypal',
                    'expira_en' => now()->addMinutes((int) config('teatro.reserva_minutos')),
                ]);
            } elseif ($orden->paypal_order_id !== $resultado['id']) {
                throw new VentaException('La reserva ya tiene un pago en curso.');
            }
        });

        return $resultado['url'];
    }

    /**
     * El comprador volvió de PayPal: se captura el pago.
     */
    public function confirmarPayPal(Orden $orden, string $tokenPayPal): string
    {
        $estado = DB::transaction(function () use ($orden, $tokenPayPal) {
            $orden = Orden::whereKey($orden->getKey())->lockForUpdate()->firstOrFail();

            if ($orden->paypal_order_id === null || ! hash_equals($orden->paypal_order_id, $tokenPayPal)) {
                throw new VentaException('El pago no corresponde a esta reserva.');
            }
            if ($orden->estado === Orden::PAGADA) {
                return self::RESULTADO_PAGADA;
            }
            if ($orden->estado === Orden::PENDIENTE) {
                if ($orden->expira_en->isPast()) {
                    // No se captura: PayPal no cobra una orden aprobada que nunca se captura.
                    $orden->entradas()->delete();
                    $orden->update(['estado' => Orden::EXPIRADA]);

                    return self::RESULTADO_EXPIRADA;
                }
                $orden->update(['estado' => Orden::PROCESANDO]);

                return self::RESULTADO_PROCESANDO;
            }
            if ($orden->estado === Orden::PROCESANDO) {
                return self::RESULTADO_PROCESANDO;
            }

            return self::RESULTADO_EXPIRADA;
        });

        if ($estado !== self::RESULTADO_PROCESANDO) {
            return $estado;
        }

        return $this->capturar($orden->fresh());
    }

    /**
     * Captura una orden en "procesando". Es seguro llamarlo varias veces.
     */
    public function capturar(Orden $orden): string
    {
        try {
            $respuesta = $this->paypal->capturar($orden->paypal_order_id, $orden->uuid);
        } catch (PayPalException $e) {
            if ($e->esRechazoDelPago()) {
                // El comprador puede volver a PayPal y usar otro medio de pago.
                DB::transaction(function () use ($orden) {
                    $o = Orden::whereKey($orden->getKey())->lockForUpdate()->firstOrFail();
                    if ($o->estado === Orden::PROCESANDO) {
                        $o->update(['estado' => Orden::PENDIENTE, 'expira_en' => now()->addMinutes(5)]);
                    }
                });

                return self::RESULTADO_RECHAZADA;
            }

            Log::warning('PayPal: captura sin confirmar, se reintentará', ['orden' => $orden->uuid, 'error' => $e->getMessage()]);

            return self::RESULTADO_PROCESANDO;
        }

        return $this->aplicarRespuestaDeCaptura($orden, $respuesta);
    }

    /**
     * Revisa las órdenes que quedaron en "procesando" (por ejemplo, porque la
     * conexión con PayPal se cortó). Lo ejecuta la tarea programada.
     */
    public function reconciliar(): int
    {
        if (! $this->paypal->configurado()) {
            return 0;
        }

        $resueltas = 0;
        Orden::where('estado', Orden::PROCESANDO)
            ->where('updated_at', '<', now()->subMinute())
            ->whereNotNull('paypal_order_id')
            ->limit(50)
            ->get()
            ->each(function (Orden $orden) use (&$resueltas) {
                try {
                    $paypal = $this->paypal->obtenerOrden($orden->paypal_order_id);
                } catch (PayPalException $e) {
                    Log::warning('PayPal: no se pudo consultar la orden', ['orden' => $orden->uuid, 'error' => $e->getMessage()]);

                    return;
                }

                $estadoPayPal = $paypal['status'] ?? null;
                $resultado = match ($estadoPayPal) {
                    'COMPLETED' => $this->aplicarRespuestaDeCaptura($orden, $paypal),
                    'APPROVED' => $this->capturar($orden),
                    'VOIDED' => $this->anularProcesando($orden),
                    default => $orden->updated_at->lt(now()->subHour()) ? $this->anularProcesando($orden) : self::RESULTADO_PROCESANDO,
                };

                if ($resultado !== self::RESULTADO_PROCESANDO) {
                    $resueltas++;
                }
            });

        return $resueltas;
    }

    /**
     * Reembolsa una orden pagada y libera sus asientos.
     */
    public function reembolsar(Orden $orden): void
    {
        $orden = Orden::findOrFail($orden->getKey());
        if ($orden->estado !== Orden::PAGADA) {
            throw new VentaException('Solo se pueden reembolsar órdenes pagadas.');
        }

        if ($orden->metodo_pago === 'paypal') {
            if (! $this->paypal->configurado() || $orden->paypal_capture_id === null) {
                throw new VentaException('PayPal no está configurado; haga el reembolso desde el panel de PayPal.');
            }
            try {
                $this->paypal->reembolsar($orden->paypal_capture_id, (string) $orden->total, $orden->moneda, $orden->uuid);
            } catch (PayPalException $e) {
                Log::error('PayPal: reembolso fallido', ['orden' => $orden->uuid, 'error' => $e->getMessage()]);
                throw new VentaException('PayPal no aceptó el reembolso. Revise el detalle en el panel de PayPal.');
            }
        }

        $this->marcarReembolsada($orden);
    }

    public function marcarReembolsada(Orden $orden): void
    {
        DB::transaction(function () use ($orden) {
            $orden = Orden::whereKey($orden->getKey())->lockForUpdate()->firstOrFail();
            if ($orden->estado === Orden::PAGADA) {
                $orden->entradas()->delete();
                $orden->update(['estado' => Orden::REEMBOLSADA, 'reembolsada_en' => now()]);
            }
        });
    }

    /**
     * Comprueba que la captura es exactamente la esperada antes de dar la orden por pagada.
     */
    public function aplicarRespuestaDeCaptura(Orden $orden, array $respuesta): string
    {
        $unidad = $respuesta['purchase_units'][0] ?? [];
        $captura = $unidad['payments']['captures'][0] ?? null;

        if (($respuesta['status'] ?? null) !== 'COMPLETED' || ! is_array($captura)) {
            return self::RESULTADO_PROCESANDO;
        }

        $estadoCaptura = $captura['status'] ?? null;
        if ($estadoCaptura === 'DECLINED' || $estadoCaptura === 'FAILED') {
            return $this->anularProcesando($orden);
        }
        if ($estadoCaptura !== 'COMPLETED') {
            // Por ejemplo, PENDING (revisión de PayPal): se resolverá más tarde.
            return self::RESULTADO_PROCESANDO;
        }

        $importeCorrecto = Dinero::iguales($captura['amount']['value'] ?? '0', $orden->total)
            && ($captura['amount']['currency_code'] ?? null) === $orden->moneda;
        $ordenCorrecta = ($captura['custom_id'] ?? $unidad['custom_id'] ?? null) === $orden->uuid;

        if (! $importeCorrecto || ! $ordenCorrecta || ! is_string($captura['id'] ?? null)) {
            Log::critical('PayPal: la captura no coincide con la orden, requiere revisión manual', [
                'orden' => $orden->uuid,
                'paypal_order_id' => $orden->paypal_order_id,
            ]);

            return self::RESULTADO_PROCESANDO;
        }

        $this->marcarPagada($orden, 'paypal', $captura['id'], exigirVigente: false);

        return self::RESULTADO_PAGADA;
    }

    private function anularProcesando(Orden $orden): string
    {
        DB::transaction(function () use ($orden) {
            $o = Orden::whereKey($orden->getKey())->lockForUpdate()->firstOrFail();
            if ($o->estado === Orden::PROCESANDO) {
                $o->entradas()->delete();
                $o->update(['estado' => Orden::EXPIRADA]);
            }
        });

        return self::RESULTADO_RECHAZADA;
    }

    private function marcarPagada(Orden $orden, string $metodo, ?string $captureId, bool $exigirVigente): Orden
    {
        [$pagada, $esNueva] = DB::transaction(function () use ($orden, $metodo, $captureId, $exigirVigente) {
            $orden = Orden::whereKey($orden->getKey())->lockForUpdate()->firstOrFail();

            if ($orden->estado === Orden::PAGADA) {
                return [$orden, false];
            }
            $vigente = in_array($orden->estado, [Orden::PENDIENTE, Orden::PROCESANDO], true)
                && (! $exigirVigente || $orden->expira_en->isFuture());
            if (! $vigente) {
                throw new VentaException('La reserva expiró y los asientos se liberaron. Vuelva a elegirlos.');
            }

            $orden->update([
                'estado' => Orden::PAGADA,
                'metodo_pago' => $metodo,
                'paypal_capture_id' => $captureId,
                'codigo' => $this->codigoUnico(),
                'pagada_en' => now(),
            ]);

            return [$orden, true];
        });

        if ($esNueva) {
            $this->enviarEntradas($pagada);
        }

        return $pagada;
    }

    private function codigoUnico(): string
    {
        do {
            $codigo = Codigo::generar();
        } while (Orden::where('codigo', $codigo)->exists());

        return $codigo;
    }

    private function enviarEntradas(Orden $orden): void
    {
        try {
            Mail::to($orden->email)->queue(new EntradasCompradas($orden));
        } catch (Throwable $e) {
            // El correo es un aviso adicional: la compra ya está registrada.
            Log::error('No se pudo encolar el correo de entradas', ['orden' => $orden->uuid, 'error' => $e->getMessage()]);
        }
    }
}
