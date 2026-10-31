@if ($paginator->hasPages())
    <nav class="paginacion" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="paginacion__deshabilitado">← Anterior</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">← Anterior</a>
        @endif
        <span>Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente →</a>
        @else
            <span class="paginacion__deshabilitado">Siguiente →</span>
        @endif
    </nav>
@endif
