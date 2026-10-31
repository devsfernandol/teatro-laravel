<dl class="obra__datos resumen-orden">
    <div>
        <dt>Obra</dt>
        <dd>{{ $orden->funcion->obra->titulo }}</dd>
    </div>
    <div>
        <dt>Función</dt>
        <dd><time datetime="{{ $orden->funcion->inicia_en->toIso8601String() }}">{{ fecha_larga($orden->funcion->inicia_en) }}</time></dd>
    </div>
    <div>
        <dt>Sala</dt>
        <dd>{{ $orden->funcion->sala->nombre }}</dd>
    </div>
    <div>
        <dt>Asientos</dt>
        <dd>{{ $orden->entradas->map(fn ($e) => $e->asiento->etiqueta())->implode(', ') ?: '—' }}</dd>
    </div>
    <div>
        <dt>Comprador</dt>
        <dd>{{ $orden->nombre }} · {{ $orden->documento }}</dd>
    </div>
    <div>
        <dt>Total</dt>
        <dd><strong>{{ dinero($orden->total, $orden->moneda) }}</strong></dd>
    </div>
</dl>
