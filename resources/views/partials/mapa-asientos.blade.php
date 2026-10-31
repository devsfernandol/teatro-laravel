{{--
    Plano de una sala.
    modo: "compra" (checkbox por asiento libre), "sala" (activar/desactivar asientos)
          o "consulta" (solo lectura, con estado de cada asiento).
--}}
<div class="mapa" role="group" aria-label="Plano de la sala {{ $sala->nombre }}">
    <div class="mapa__escenario" aria-hidden="true">Escenario</div>
    <div class="mapa__filas">
        @foreach ($filas as $fila => $asientos)
            <div class="mapa__fila">
                <span class="mapa__letra" aria-hidden="true">{{ $fila }}</span>
                @foreach ($asientos as $asiento)
                    @if ($modo === 'sala')
                        <input type="checkbox" class="asiento__input" name="activos[]" value="{{ $asiento->id }}" id="asiento-{{ $asiento->id }}" @checked($asiento->activo)>
                        <label for="asiento-{{ $asiento->id }}" class="asiento asiento--editable" title="{{ $asiento->etiqueta() }}">
                            <span class="solo-lectores">Fila {{ $asiento->fila }}, asiento </span>{{ $asiento->numero }}
                        </label>
                    @elseif (! $asiento->activo)
                        <span class="asiento asiento--hueco" aria-hidden="true"></span>
                    @elseif ($modo === 'compra')
                        @php($ocupado = $ocupados->has($asiento->id))
                        <input type="checkbox" class="asiento__input" name="asientos[]" value="{{ $asiento->id }}" id="asiento-{{ $asiento->id }}"
                            data-etiqueta="{{ $asiento->etiqueta() }}" @disabled($ocupado) @checked(! $ocupado && in_array($asiento->id, array_map('intval', (array) old('asientos', [])), true))>
                        <label for="asiento-{{ $asiento->id }}" @class(['asiento', 'asiento--ocupado' => $ocupado]) title="{{ $asiento->etiqueta() }}{{ $ocupado ? ' (ocupado)' : '' }}">
                            <span class="solo-lectores">Fila {{ $asiento->fila }}, asiento </span>{{ $asiento->numero }}@if ($ocupado)<span class="solo-lectores">, ocupado</span>@endif
                        </label>
                    @else
                        @php($entrada = $entradas->get($asiento->id))
                        @php($estado = $entrada === null ? 'libre' : ($entrada->orden->estado === \App\Models\Orden::PAGADA ? 'vendido' : 'retenido'))
                        <span class="asiento asiento--{{ $estado }}" title="{{ $asiento->etiqueta() }}: {{ $estado }}{{ $entrada ? ' · '.$entrada->orden->nombre : '' }}">
                            <span class="solo-lectores">{{ $asiento->etiqueta() }} {{ $estado }}</span><span aria-hidden="true">{{ $asiento->numero }}</span>
                        </span>
                    @endif
                @endforeach
                <span class="mapa__letra" aria-hidden="true">{{ $fila }}</span>
            </div>
        @endforeach
    </div>
</div>
