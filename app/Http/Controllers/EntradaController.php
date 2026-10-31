<?php

namespace App\Http\Controllers;

use App\Mail\RecuperarEntradas;
use App\Models\Orden;
use App\Support\Codigo;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Timebox;
use Illuminate\View\View;
use Throwable;

class EntradaController extends Controller
{
    /**
     * Entradas de una orden pagada. El código (80 bits de azar) funciona como
     * llave: quien lo tiene puede ver las entradas, igual que una entrada en papel.
     */
    public function show(string $codigo): Response
    {
        $codigo = strtoupper($codigo);
        abort_unless(Codigo::esValido($codigo), 404);

        $orden = Orden::with('funcion.obra', 'funcion.sala', 'entradas.asiento')
            ->where('codigo', $codigo)
            ->whereIn('estado', [Orden::PAGADA, Orden::REEMBOLSADA])
            ->first();
        abort_if($orden === null, 404);

        return response()
            ->view('publico.entrada', ['orden' => $orden, 'qr' => $this->qr($codigo)])
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function recuperar(): View
    {
        return view('publico.recuperar');
    }

    /**
     * Envía por correo los enlaces de las entradas. La respuesta es la misma
     * exista o no la compra, para no revelar qué correos compraron.
     */
    public function enviarRecuperacion(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'documento' => ['required', 'string', 'max:30'],
        ]);
        $documento = strtoupper(str_replace([' ', '.'], '', trim($datos['documento'])));

        (new Timebox)->call(function () use ($datos, $documento) {
            $ordenes = Orden::where('email', $datos['email'])
                ->where('documento', $documento)
                ->where('estado', Orden::PAGADA)
                ->latest('pagada_en')
                ->limit(20)
                ->get();

            if ($ordenes->isNotEmpty()) {
                try {
                    Mail::to($datos['email'])->queue(new RecuperarEntradas($ordenes));
                } catch (Throwable $e) {
                    Log::error('No se pudo encolar el correo de recuperación', ['error' => $e->getMessage()]);
                }
            }
        }, 300_000);

        return redirect()->route('entradas.recuperar')
            ->with('aviso', 'Si hay compras con esos datos, le enviamos un correo con los enlaces a sus entradas.');
    }

    private function qr(string $codigo): string
    {
        $renderer = new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString(route('entradas.show', $codigo));
    }
}
