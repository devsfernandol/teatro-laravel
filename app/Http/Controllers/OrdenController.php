<?php

namespace App\Http\Controllers;

use App\Exceptions\VentaException;
use App\Models\Orden;
use App\Services\PagoService;
use App\Services\ReservaService;
use App\Support\OrdenesSesion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Pago de una reserva. Solo el navegador que hizo la reserva puede verla o
 * pagarla; una vez pagada, las entradas se consultan con su código.
 */
class OrdenController extends Controller
{
    public function __construct(
        private readonly PagoService $pagos,
        private readonly ReservaService $reservas,
    ) {
    }

    public function show(Orden $orden): Response|RedirectResponse
    {
        $this->autorizar($orden);

        if ($orden->estaPagada()) {
            return redirect()->route('entradas.show', $orden->codigo);
        }

        if ($orden->estado === Orden::PENDIENTE && $orden->expira_en->isPast()) {
            $this->reservas->liberarVencidas($orden->funcion_id);
            $orden->refresh();
        }

        $orden->load('funcion.obra', 'funcion.sala', 'entradas.asiento');

        return response()->view('publico.orden', [
            'orden' => $orden,
            'modoDemo' => $this->pagos->modoDemo(),
        ]);
    }

    public function pagarDemo(Orden $orden): RedirectResponse
    {
        $this->autorizar($orden);

        try {
            $orden = $this->pagos->pagarDemo($orden);
        } catch (VentaException $e) {
            return redirect()->route('ordenes.show', $orden)->withErrors(['pago' => $e->getMessage()]);
        }

        return redirect()->route('entradas.show', $orden->codigo);
    }

    public function pagarPayPal(Orden $orden): RedirectResponse
    {
        $this->autorizar($orden);
        abort_if($this->pagos->modoDemo(), 404);

        try {
            $url = $this->pagos->iniciarPayPal(
                $orden,
                route('ordenes.paypal.retorno', $orden),
                route('ordenes.paypal.cancelado', $orden),
            );
        } catch (VentaException $e) {
            return redirect()->route('ordenes.show', $orden)->withErrors(['pago' => $e->getMessage()]);
        }

        return redirect()->away($url, 303);
    }

    public function retornoPayPal(Request $request, Orden $orden): Response|RedirectResponse
    {
        $this->autorizar($orden);
        abort_unless($this->pagos->paypalDisponible(), 404);

        $token = $request->query('token');
        if (! is_string($token) || preg_match('/^[A-Z0-9-]{5,64}$/', $token) !== 1) {
            return redirect()->route('ordenes.show', $orden)->withErrors(['pago' => 'No recibimos la confirmación de PayPal.']);
        }

        try {
            $resultado = $this->pagos->confirmarPayPal($orden, $token);
        } catch (VentaException $e) {
            return redirect()->route('ordenes.show', $orden)->withErrors(['pago' => $e->getMessage()]);
        }

        return match ($resultado) {
            PagoService::RESULTADO_PAGADA => redirect()->route('entradas.show', $orden->fresh()->codigo),
            PagoService::RESULTADO_RECHAZADA => redirect()->route('ordenes.show', $orden)
                ->withErrors(['pago' => 'PayPal rechazó el pago. Puede intentarlo de nuevo con otro medio de pago.']),
            PagoService::RESULTADO_EXPIRADA => redirect()->route('ordenes.show', $orden),
            default => redirect()->route('ordenes.show', $orden),
        };
    }

    public function canceladoPayPal(Orden $orden): RedirectResponse
    {
        $this->autorizar($orden);

        return redirect()->route('ordenes.show', $orden)
            ->with('aviso', 'Canceló el pago en PayPal. Sus asientos siguen reservados hasta que venza el plazo.');
    }

    public function cancelar(Orden $orden): RedirectResponse
    {
        $this->autorizar($orden);
        $this->reservas->cancelar($orden);

        return redirect()->route('obras.show', $orden->funcion->obra)
            ->with('aviso', 'Reserva cancelada. Los asientos quedaron libres.');
    }

    private function autorizar(Orden $orden): void
    {
        // 404 y no 403: no se revela si la orden existe.
        abort_unless(OrdenesSesion::pertenece($orden->uuid), 404);
    }
}
