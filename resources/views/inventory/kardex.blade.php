@php
    $tono = ['entrada' => 'ok', 'salida' => 'bad', 'ajuste' => 'warn', 'inicial' => 'info'];
@endphp

<x-mus.page title="Kardex de {{ $product->name }}"
            subtitle="{{ $product->sku ?: 'Sin SKU' }} · saldo actual {{ rtrim(rtrim(number_format((float) $product->stock, 2, ',', '.'), '0'), ',') }} {{ $product->unit->symbol ?? 'und' }}"
            icon="stock"
            :crumbs="['Inventario' => route('inventory.index'), 'Kardex' => null]">

    <x-slot name="actions">
        @can('inventario.ajustar')
            <x-mus.btn href="{{ route('inventory.ajustar', $product) }}" variant="primary" icon="pencil">
                Ajustar existencias
            </x-mus.btn>
        @endcan
        <x-mus.btn href="{{ route('products.show', $product) }}" icon="eye">Ver producto</x-mus.btn>
    </x-slot>

    <div class="ikpi" data-reveal data-stagger>
        <div class="ikpi__c">
            <span>Saldo actual</span>
            <b>{{ rtrim(rtrim(number_format((float) $product->stock, 2, ',', '.'), '0'), ',') }}</b>
            <em>mínimo {{ $product->min_stock }}</em>
        </div>
        <div class="ikpi__c">
            <span>Valor en bodega</span>
            <b><i class="moneda">$</i>{{ number_format($product->stock_value, 0, ',', '.') }}</b>
            <em>al costo de ${{ number_format((float) $product->cost, 0, ',', '.') }}</em>
        </div>
        <div class="ikpi__c">
            <span>Estado</span>
            <b class="{{ $product->stock_state === 'agotado' ? 'bad' : ($product->stock_state === 'bajo' ? 'warn' : '') }}">
                {{ ['agotado' => 'Agotado', 'bajo' => 'Bajo', 'ok' => 'Disponible'][$product->stock_state] }}
            </b>
        </div>
        <div class="ikpi__c">
            <span>Movimientos</span>
            <b data-count="{{ $movimientos->total() }}">0</b>
        </div>
    </div>

    <x-mus.panel :pad="false" :reveal="true" title="Historial" sub="Del más reciente al más antiguo">
        @if ($movimientos->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Motivo</th>
                            <th class="num">Entra</th>
                            <th class="num">Sale</th>
                            <th class="num">Saldo</th>
                            <th>Responsable</th>
                            <th>Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movimientos as $m)
                            @php $q = (float) $m->quantity; @endphp
                            <tr data-row>
                                <td>
                                    {{ $m->created_at?->format('d/m/Y') }}
                                    <span class="sub">{{ $m->created_at?->format('H:i') }}</span>
                                </td>
                                <td>
                                    <x-mus.badge :tone="$tono[$m->type] ?? 'soft'">{{ $m->reason_label }}</x-mus.badge>
                                </td>
                                <td class="num">
                                    @if ($q > 0)
                                        <b style="color:var(--ok)">{{ rtrim(rtrim(number_format($q, 2, ',', '.'), '0'), ',') }}</b>
                                    @else — @endif
                                </td>
                                <td class="num">
                                    @if ($q < 0)
                                        <b style="color:var(--bad)">{{ rtrim(rtrim(number_format(abs($q), 2, ',', '.'), '0'), ',') }}</b>
                                    @else — @endif
                                </td>
                                <td class="num"><b>{{ rtrim(rtrim(number_format((float) $m->balance_after, 2, ',', '.'), '0'), ',') }}</b></td>
                                <td>{{ $m->user->name ?? 'Sistema' }}</td>
                                <td><span class="sub">{{ \Illuminate\Support\Str::limit($m->notes, 36) ?: '—' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$movimientos" label="movimientos" />
        @else
            <x-mus.empty icon="stock" title="Este producto no tiene movimientos"
                         text="El saldo actual viene de la carga inicial. En cuanto se venda o se ajuste, aparecerá el historial." />
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .ikpi{ display:grid; gap:12px; margin-bottom:16px;
               grid-template-columns:repeat(auto-fit,minmax(184px,1fr)); }
        .ikpi__c{ padding:14px 16px; background:var(--card); border:1px solid var(--line);
                  border-radius:12px; }
        .ikpi__c > span{ display:block; font-size:11.1px; letter-spacing:.05em; text-transform:uppercase;
                         color:var(--muted-2); font-weight:600; }
        .ikpi__c b{ display:block; margin:6px 0 2px; font-size:22px; font-weight:700;
                    letter-spacing:-.03em; color:var(--ink); line-height:1.05;
                    font-variant-numeric:tabular-nums; }
        .ikpi__c b.warn{ color:var(--warn); }
        .ikpi__c b.bad{ color:var(--bad); }
        .ikpi__c em{ display:block; font-style:normal; font-size:11.4px; color:var(--muted); }
    </style>
    @endpush
</x-mus.page>
