@extends('layouts.app')

@section('titulo', 'Sus entradas')

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('contenido')
<section class="tarjeta entrada" aria-labelledby="titulo-entrada">
    @if ($orden->estado === \App\Models\Orden::REEMBOLSADA)
        <div class="alerta alerta--error" role="status">
            <h1 id="titulo-entrada">Compra reembolsada</h1>
            <p>Esta compra fue reembolsada el {{ fecha_corta($orden->reembolsada_en) }}. Las entradas ya no son válidas.</p>
        </div>
    @else
        <div class="alerta alerta--exito" role="status">
            <h1 id="titulo-entrada">¡Compra realizada con éxito!</h1>
            <p>Presente este código QR o su número de entrada en la boletería. Guarde esta página: la dirección es su comprobante.</p>
        </div>

        <div class="entrada__qr">
            {!! $qr !!}
            <p class="entrada__codigo">Código: <strong>{{ $orden->codigo }}</strong></p>
        </div>

        <h2>Entradas</h2>
        <ul class="lista-entradas">
            @foreach ($orden->entradas as $entrada)
                <li class="entrada__numero">
                    <span class="entrada__numero-etiqueta">Entrada n.º</span>
                    <strong class="entrada__numero-valor">{{ $entrada->id }}</strong>
                    <span class="entrada__asiento">Asiento {{ $entrada->asiento->etiqueta() }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @include('partials.resumen-orden')
    <dl class="obra__datos">
        <div>
            <dt>Fecha de compra</dt>
            <dd>{{ fecha_larga($orden->pagada_en) }}</dd>
        </div>
        <div>
            <dt>Medio de pago</dt>
            <dd>{{ $orden->metodo_pago === 'paypal' ? 'PayPal' : 'Pago simulado (demostración)' }}</dd>
        </div>
    </dl>

    <div class="entrada__acciones no-imprimir">
        <a class="boton" href="{{ route('cartelera') }}">Volver a la cartelera</a>
    </div>
</section>
@endsection
