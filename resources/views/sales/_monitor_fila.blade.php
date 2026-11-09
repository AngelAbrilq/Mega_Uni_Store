<tr data-row data-id="{{ $f['id'] }}" class="{{ $f['anulada'] ? 'is-void' : '' }} {{ $f['alto'] ? 'is-alto' : '' }}">
    <td>
        <a href="{{ $f['url'] }}" class="mt__id">{{ $f['numero'] }}</a>
        @if ($f['alto'])<span class="mon-alto">Alto valor</span>@endif
    </td>
    <td class="mon-hora">{{ $f['hora'] }}</td>
    <td class="mon-cli">{{ $f['cliente'] ?? __('mus.vacio.consumidor_final') }}</td>
    <td class="mon-ven">{{ $f['vendedor'] }}</td>
    <td class="mon-med sub">{{ implode(' + ', $f['medios']) ?: '—' }}</td>
    <td class="num"><b class="mon-tot"><i class="moneda">$</i>{{ number_format($f['total'], 0, ',', '.') }}</b></td>
    <td class="mon-est">
        @if ($f['anulada'])
            <x-mus.badge tone="bad" :dot="true">Anulada</x-mus.badge>
            @if ($f['motivo'])<span class="mon-motivo" title="{{ $f['motivo'] }}">{{ $f['motivo'] }}{{ $f['anulo'] ? ' · ' . $f['anulo'] : '' }}</span>@endif
        @else
            <x-mus.badge tone="ok" :dot="true">Pagada</x-mus.badge>
        @endif
    </td>
</tr>
