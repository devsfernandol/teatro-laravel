@extends('layouts.app')

@section('titulo', 'Pago de su reserva')

@if ($orden->estado === \App\Models\Orden::PROCESANDO)
    @push('head')
        <meta http-equiv="refresh" content="5">
    @endpush
@endif

@section('contenido')
<div class="pago">
    @if ($orden->puedePagarse())
        <section class="tarjeta" aria-labelledby="titulo-pago">
            <h1 id="titulo-pago">Confirme y pague</h1>
            <p class="cuenta-regresiva">
                Sus asientos están reservados hasta las
                <strong><time datetime="{{ $orden->expira_en->toIso8601String() }}">{{ $orden->expira_en->format('H:i') }}</time></strong>.
                Si no completa el pago antes, se liberarán.
            </p>
            @include('partials.resumen-orden')

            <div class="pago__acciones">
                @if ($modoDemo)
                    <form method="post" action="{{ route('ordenes.pagar-demo', $orden) }}">
                        @csrf
                        <button type="submit" class="boton boton--bloque">Pagar {{ dinero($orden->total, $orden->moneda) }} (simulado)</button>
                    </form>
                    <p class="campo__ayuda">Modo demostración: no se realiza ningún cobro.</p>
                @else
                    <form method="post" action="{{ route('ordenes.pagar-paypal', $orden) }}">
                        @csrf
                        <button type="submit" class="boton boton--bloque boton--paypal">Pagar con PayPal</button>
                    </form>
                    <p class="campo__ayuda">Será redirigido a PayPal para completar el pago de forma segura. Puede pagar con su cuenta PayPal o con tarjeta.</p>
                @endif

                <form method="post" action="{{ route('ordenes.cancelar', $orden) }}">
                    @csrf
                    <button type="submit" class="boton boton--secundario boton--bloque">Cancelar reserva</button>
                </form>
            </div>
        </section>
    @elseif ($orden->estado === \App\Models\Orden::PROCESANDO)
        <section class="tarjeta mensaje" aria-live="polite">
            <h1>Estamos confirmando su pago</h1>
            <p>PayPal está procesando el pago. Esta página se actualizará sola en unos segundos; no la cierre ni vuelva a pagar.</p>
            @include('partials.resumen-orden')
        </section>
    @else
        <section class="tarjeta mensaje">
            <h1>Reserva {{ mb_strtolower($orden->etiquetaEstado()) }}</h1>
            <p>
                @if ($orden->estado === \App\Models\Orden::EXPIRADA)
                    El plazo para pagar terminó y los asientos se liberaron. No se realizó ningún cobro.
                @elseif ($orden->estado === \App\Models\Orden::CANCELADA)
                    La reserva fue cancelada y los asientos quedaron libres.
                @else
                    Esta reserva ya no puede pagarse.
                @endif
            </p>
            @if ($orden->funcion->esVendible())
                <a class="boton" href="{{ route('funciones.asientos', $orden->funcion) }}">Elegir asientos de nuevo</a>
            @else
                <a class="boton" href="{{ route('cartelera') }}">Volver a la cartelera</a>
            @endif
        </section>
    @endif
</div>
@endsection
