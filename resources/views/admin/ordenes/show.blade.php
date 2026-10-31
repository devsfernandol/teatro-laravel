@extends('layouts.admin')

@section('titulo', 'Orden #'.$orden->id)

@section('contenido')
<p class="volver"><a href="{{ route('admin.ordenes.index') }}">← Ventas</a></p>
<div class="barra-titulo">
    <h1>Orden #{{ $orden->id }}</h1>
    <span class="estado estado--{{ $orden->estado }}">{{ $orden->etiquetaEstado() }}</span>
</div>

<div class="dos-columnas">
    <section class="tarjeta">
        <h2>Detalle</h2>
        <dl class="obra__datos">
            <div><dt>Obra</dt><dd>{{ $orden->funcion->obra->titulo }}</dd></div>
            <div><dt>Función</dt><dd><a href="{{ route('admin.funciones.show', $orden->funcion) }}">{{ fecha_larga($orden->funcion->inicia_en) }} · {{ $orden->funcion->sala->nombre }}</a></dd></div>
            <div><dt>Comprador</dt><dd>{{ $orden->nombre }}</dd></div>
            <div><dt>Documento</dt><dd>{{ $orden->documento }}</dd></div>
            <div><dt>Correo</dt><dd>{{ $orden->email }}</dd></div>
            <div><dt>Total</dt><dd>{{ dinero($orden->total, $orden->moneda) }}</dd></div>
            <div><dt>Creada</dt><dd>{{ fecha_corta($orden->created_at) }}</dd></div>
            @if ($orden->pagada_en)<div><dt>Pagada</dt><dd>{{ fecha_corta($orden->pagada_en) }}</dd></div>@endif
            @if ($orden->reembolsada_en)<div><dt>Reembolsada</dt><dd>{{ fecha_corta($orden->reembolsada_en) }}</dd></div>@endif
        </dl>
    </section>

    <section class="tarjeta">
        <h2>Pago</h2>
        <dl class="obra__datos">
            <div><dt>Método</dt><dd>{{ match ($orden->metodo_pago) { 'paypal' => 'PayPal', 'demo' => 'Simulado (demostración)', default => '—' } }}</dd></div>
            @if ($orden->paypal_order_id)<div><dt>Orden PayPal</dt><dd><code>{{ $orden->paypal_order_id }}</code></dd></div>@endif
            @if ($orden->paypal_capture_id)<div><dt>Captura PayPal</dt><dd><code>{{ $orden->paypal_capture_id }}</code></dd></div>@endif
            @if ($orden->codigo)<div><dt>Código de entradas</dt><dd><code>{{ $orden->codigo }}</code></dd></div>@endif
        </dl>

        <h2>Entradas</h2>
        @if ($orden->entradas->isEmpty())
            <p>Sin entradas (la orden no se pagó o fue reembolsada).</p>
        @else
            <ul>
                @foreach ($orden->entradas as $e)
                    <li>Entrada n.º {{ $e->id }} · asiento {{ $e->asiento->etiqueta() }} · {{ dinero($e->precio, $orden->moneda) }}</li>
                @endforeach
            </ul>
        @endif

        @if ($orden->estaPagada())
            <form method="post" action="{{ route('admin.ordenes.reembolsar', $orden) }}" class="zona-peligro">
                @csrf
                <p>El reembolso devuelve {{ dinero($orden->total, $orden->moneda) }} al comprador{{ $orden->metodo_pago === 'paypal' ? ' a través de PayPal' : '' }} y libera los asientos. No se puede deshacer.</p>
                <label class="casilla"><input type="checkbox" name="confirmar" value="1" required> Confirmo el reembolso</label>
                <button type="submit" class="boton boton--peligro">Reembolsar</button>
            </form>
        @endif
    </section>
</div>
@endsection
