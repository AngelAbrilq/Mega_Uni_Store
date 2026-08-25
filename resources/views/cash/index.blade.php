<x-mus.page title="Caja" subtitle="Turnos, arqueos y diferencias" icon="money">
    <x-slot name="actions">
        @can('caja.abrir')
            @unless ($abierta)
                <x-mus.btn href="{{ route('cash.create') }}" variant="primary" icon="plus">Abrir turno</x-mus.btn>
            @else
                <x-mus.btn href="{{ route('cash.show', $abierta) }}" variant="primary" icon="clock">
                    Ir a mi turno abierto
                </x-mus.btn>
            @endunless
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($sessions->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Responsable</th>
                            <th class="num">Base</th>
                            <th class="num">Ventas</th>
                            <th class="num">Esperado</th>
                            <th class="num">Contado</th>
                            <th class="num">Diferencia</th>
                            <th>Estado</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sessions as $s)
                            @php $dif = (float) $s->difference; @endphp
                            <tr data-row>
                                <td><span class="mt__id">{{ $s->id }}</span></td>
                                <td>
                                    <b>{{ $s->user->name ?? '—' }}</b>
                                    <span class="sub">
                                        {{ $s->opened_at?->locale('es')->isoFormat('D MMM, HH:mm') }}
                                        @if ($s->closed_at) → {{ $s->closed_at->format('H:i') }} @endif
                                    </span>
                                </td>
                                <td class="num"><i class="moneda">$</i>{{ number_format((float) $s->opening_amount, 0, ',', '.') }}</td>
                                <td class="num">
                                    <b><i class="moneda">$</i>{{ number_format((float) ($s->ventas_total ?? 0), 0, ',', '.') }}</b>
                                    <span class="sub">{{ $s->sales_count }} ventas</span>
                                </td>
                                <td class="num">
                                    {{ $s->status === 'cerrada' ? '$' . number_format((float) $s->expected_amount, 0, ',', '.') : '—' }}
                                </td>
                                <td class="num">
                                    {{ $s->counted_amount !== null ? '$' . number_format((float) $s->counted_amount, 0, ',', '.') : '—' }}
                                </td>
                                <td class="num">
                                    @if ($s->status === 'cerrada')
                                        <x-mus.badge :tone="abs($dif) < 0.01 ? 'ok' : ($dif > 0 ? 'info' : 'bad')">
                                            {{ $dif > 0 ? '+' : '' }}${{ number_format($dif, 0, ',', '.') }}
                                        </x-mus.badge>
                                    @else — @endif
                                </td>
                                <td>
                                    @if ($s->status === 'abierta')
                                        <x-mus.badge tone="warn" :dot="true">Abierta</x-mus.badge>
                                    @else
                                        <x-mus.badge tone="off" :dot="true">Cerrada</x-mus.badge>
                                    @endif
                                </td>
                                <td class="act">
                                    <x-mus.btn href="{{ route('cash.show', $s) }}"
                                               variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                        <x-mus.icon name="eye" :w="15" />
                                    </x-mus.btn>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$sessions" label="turnos" />
        @else
            <x-mus.empty icon="money" title="Todavía no se ha abierto ninguna caja"
                         text="Abre un turno con su base inicial para que las ventas en efectivo queden cuadradas.">
                <x-slot name="action">
                    @can('caja.abrir')
                        <x-mus.btn href="{{ route('cash.create') }}" variant="primary" icon="plus">
                            Abrir el primer turno
                        </x-mus.btn>
                    @endcan
                </x-slot>
            </x-mus.empty>
        @endif
    </x-mus.panel>
</x-mus.page>
