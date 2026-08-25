<x-mus.page title="Devoluciones" subtitle="Mercancía que volvió del cliente" icon="back">

    <div class="skpi" data-reveal data-stagger>
        <div class="skpi__c"><span>Este mes</span><b data-count="{{ $resumen['mes'] }}">0</b>
            <em>{{ rtrim(rtrim(number_format($resumen['unidades'], 2, ',', '.'), '0'), ',') }} unidades</em></div>
        <div class="skpi__c"><span>Valor devuelto</span>
            <b class="bad"><i class="moneda">$</i>{{ number_format($resumen['valor'], 0, ',', '.') }}</b><em>en el mes</em></div>
        <div class="skpi__c"><span>Total histórico</span><b data-count="{{ $resumen['total'] }}">0</b></div>
    </div>

    <x-mus.panel :pad="false" :reveal="true">
        <x-mus.toolbar :count="$returns->total()" label="devoluciones"
                       :action="route('returns.index')" :q="$q" />

        @if ($returns->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Venta</th>
                            <th>Motivo</th>
                            <th class="num">Ítems</th>
                            <th class="num">Devuelto</th>
                            <th>Quién</th>
                            <th>Fecha</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($returns as $d)
                            <tr data-row>
                                <td><span class="mt__id">{{ $d->number }}</span></td>
                                <td>
                                    <a href="{{ route('sales.show', $d->sale_id) }}">
                                        <b>{{ $d->sale->number ?? '—' }}</b>
                                    </a>
                                </td>
                                <td><span class="sub">{{ \Illuminate\Support\Str::limit($d->reason, 44) }}</span></td>
                                <td class="num">{{ $d->items_count }}</td>
                                <td class="num"><b style="color:var(--bad)"><i class="moneda">$</i>{{ number_format((float) $d->total, 0, ',', '.') }}</b></td>
                                <td>{{ $d->user->name ?? '—' }}</td>
                                <td>{{ $d->returned_at?->locale('es')->isoFormat('D MMM, HH:mm') }}</td>
                                <td class="act">
                                    <x-mus.btn href="{{ route('returns.show', $d) }}"
                                               variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                        <x-mus.icon name="eye" :w="15" />
                                    </x-mus.btn>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$returns" label="devoluciones" />
        @else
            <x-mus.empty icon="back" title="Sin devoluciones registradas"
                         text="Cuando un cliente devuelva algo, se registra desde el detalle de su venta." />
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .skpi{ display:grid; gap:12px; margin-bottom:16px;
               grid-template-columns:repeat(auto-fit,minmax(184px,1fr)); }
        .skpi__c{ padding:14px 16px; background:var(--card); border:1px solid var(--line);
                  border-radius:12px; }
        .skpi__c > span{ display:block; font-size:11.1px; letter-spacing:.05em; text-transform:uppercase;
                         color:var(--muted-2); font-weight:600; }
        .skpi__c b{ display:block; margin:6px 0 2px; font-size:22px; font-weight:700;
                    letter-spacing:-.03em; color:var(--ink); line-height:1.05;
                    font-variant-numeric:tabular-nums; }
        .skpi__c b.bad{ color:var(--bad); }
        .skpi__c em{ display:block; font-style:normal; font-size:11.4px; color:var(--muted); }
    </style>
    @endpush
</x-mus.page>
