<?php

namespace App\Http\Controllers;

use App\Models\Orden;
use App\Services\PagoService;
use App\Services\PayPal\PayPalClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Notificaciones de PayPal. Complementan el flujo principal: registran los
 * reembolsos y contracargos hechos desde el panel de PayPal y confirman pagos
 * que quedaron en "procesando". Cada notificación se verifica con PayPal antes
 * de usarla.
 */
class WebhookPayPalController extends Controller
{
    public function __invoke(Request $request, PayPalClient $paypal, PagoService $pagos): Response
    {
        $cuerpo = $request->getContent();
        if (strlen($cuerpo) > 100_000) {
            return response('', 413);
        }

        $verificado = $paypal->verificarWebhook([
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
        ], $cuerpo);

        if (! $verificado) {
            Log::warning('Webhook de PayPal rechazado: firma no válida', ['ip' => $request->ip()]);

            return response('', 400);
        }

        $evento = json_decode($cuerpo, true);
        $tipo = $evento['event_type'] ?? '';
        $recurso = $evento['resource'] ?? [];

        switch ($tipo) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                $orderId = $recurso['supplementary_data']['related_ids']['order_id'] ?? null;
                $orden = is_string($orderId) ? Orden::where('paypal_order_id', $orderId)->first() : null;
                if ($orden !== null && $orden->estado === Orden::PROCESANDO) {
                    $pagos->capturar($orden);
                }
                break;

            case 'PAYMENT.CAPTURE.REFUNDED':
            case 'PAYMENT.CAPTURE.REVERSED':
                $captureId = $this->idCapturaDelReembolso($recurso, $tipo);
                $orden = $captureId !== null ? Orden::where('paypal_capture_id', $captureId)->first() : null;
                if ($orden !== null) {
                    $pagos->marcarReembolsada($orden);
                }
                break;
        }

        return response('', 200);
    }

    private function idCapturaDelReembolso(array $recurso, string $tipo): ?string
    {
        if ($tipo === 'PAYMENT.CAPTURE.REVERSED') {
            return is_string($recurso['id'] ?? null) ? $recurso['id'] : null;
        }

        foreach ($recurso['links'] ?? [] as $enlace) {
            if (($enlace['rel'] ?? null) === 'up' && preg_match('#/captures/([A-Z0-9]+)$#', (string) ($enlace['href'] ?? ''), $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }
}
