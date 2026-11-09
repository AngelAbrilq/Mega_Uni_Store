@props([
    'count'  => null,
    'label'  => 'registros',
    'search' => true,
    'action' => null,   // si se pasa, la búsqueda va al servidor (toda la tabla)
    'q'      => '',
])

@if ($action)
    {{-- Búsqueda real: recorre toda la tabla, no solo la página visible. --}}
    <form method="GET" action="{{ $action }}" class="mtool">
        <div class="mtool__search">
            <x-mus.icon name="search" :w="15" stroke-width="2" />
            <input type="text" name="q" value="{{ $q }}" placeholder="Buscar…" aria-label="Buscar">
        </div>
        <button type="submit" class="mb mb--primary mb--sm">Buscar</button>
        @if ($q !== '')
            <a href="{{ $action }}" class="mb mb--ghost mb--sm">Limpiar</a>
        @endif
        @if ($count !== null)
            <span class="mtool__count">{{ number_format($count, 0, ',', '.') }} {{ $label }}</span>
        @endif
        <span class="mtool__sp"></span>
        {{ $slot }}
    </form>
@else
    <div class="mtool">
        @if ($search)
            <div class="mtool__search">
                <x-mus.icon name="search" :w="15" stroke-width="2" />
                <input type="text" data-table-filter placeholder="Filtrar en esta página…" aria-label="Filtrar">
            </div>
        @endif
        @if ($count !== null)
            <span class="mtool__count" data-filter-count>{{ number_format($count, 0, ',', '.') }} {{ $label }}</span>
        @endif
        <span class="mtool__sp"></span>
        {{ $slot }}
    </div>
@endif
