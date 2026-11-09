@props(['items', 'label' => 'registros'])
@if ($items->total() > 0)
    <div class="mpag">
        <div class="mpag__info">
            {{ $items->firstItem() }}–{{ $items->lastItem() }} de {{ number_format($items->total(), 0, ',', '.') }} {{ $label }}
        </div>

        @if ($items->hasPages())
            <nav class="mpag__nav" aria-label="Paginación">
                @if ($items->onFirstPage())
                    <span class="off" aria-hidden="true"><x-mus.icon name="back" :w="14" stroke-width="2.2" /></span>
                @else
                    <a href="{{ $items->previousPageUrl() }}" rel="prev" aria-label="Anterior">
                        <x-mus.icon name="back" :w="14" stroke-width="2.2" />
                    </a>
                @endif

                @php
                    $desde = max(1, $items->currentPage() - 2);
                    $hasta = min($items->lastPage(), $items->currentPage() + 2);
                    $rango = $items->getUrlRange($desde, $hasta);
                @endphp
                @foreach ($rango as $p => $url)
                    @if ($p == $items->currentPage())
                        <span class="on" aria-current="page">{{ $p }}</span>
                    @else
                        <a href="{{ $url }}">{{ $p }}</a>
                    @endif
                @endforeach

                @if ($items->hasMorePages())
                    <a href="{{ $items->nextPageUrl() }}" rel="next" aria-label="Siguiente">
                        <x-mus.icon name="next" :w="14" stroke-width="2.2" />
                    </a>
                @else
                    <span class="off" aria-hidden="true"><x-mus.icon name="next" :w="14" stroke-width="2.2" /></span>
                @endif
            </nav>
        @endif
    </div>
@endif
