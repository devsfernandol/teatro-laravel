@if ($obra->imagenUrl())
    <img class="portada" src="{{ $obra->imagenUrl() }}" alt="" loading="lazy" decoding="async">
@else
    <div class="portada portada--vacia portada--{{ $obra->id % 5 }}" aria-hidden="true">
        <span>{{ mb_substr($obra->titulo, 0, 1) }}</span>
    </div>
@endif
