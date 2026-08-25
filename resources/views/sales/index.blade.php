@php use App\Support\Formato; @endphp

<x-mus.page :title="__('mus.ventas.titulo')" :subtitle="__('mus.ventas.subtitulo')" icon="money">
    <x-slot name="actions">
        @can('ventas.crear')
            <x-mus.btn href="{{ route('pos.index') }}" variant="primary" icon="plus">{{ __('mus.ventas.nueva') }}</x-mus.btn>
        @endcan
    </x-slot>

    <div class="skpi" data-reveal data-stagger>
        <div class="skpi__c">
            <span>{{ __('mus.ventas.del_periodo') }}</span>
            <b data-count="{{ $resumen['ventas'] }}">0</b>
        </div>
        <div class="skpi__c">
            <span>{{ __('mus.ventas.facturado') }}</span>
            <b><i class="moneda">$</i>{{ number_format($resumen['total'], 0, ',', '.') }}</b>
        </div>
        <div class="skpi__c">
            <span>{{ __('mus.ventas.utilidad') }}</span>
            <b class="ok"><i class="moneda">$</i>{{ number_format($resumen['utilidad'], 0, ',', '.') }}</b>
            <em>{{ $resumen['total'] > 0 ? number_format($resumen['utilidad'] / $resumen['total'] * 100, 1) : '0' }}{{ __('mus.ventas.margen_pct') }}</em>
        </div>
        <div class="skpi__c">
            <span>{{ __('mus.ventas.ticket_promedio') }}</span>
            <b><i class="moneda">$</i>{{ number_format($resumen['ticket'], 0, ',', '.') }}</b>
        </div>
        <div class="skpi__c">
            <span>{{ __('mus.ventas.anuladas') }}</span>
            <b class="{{ $resumen['anuladas'] ? 'bad' : '' }}">{{ $resumen['anuladas'] }}</b>
        </div>
    </div>

    <x-mus.panel :pad="false" :reveal="true">
        <form method="GET" action="{{ route('sales.index') }}" class="sfil">
            <div class="sfil__s">
                <x-mus.icon name="search" :w="15" stroke-width="2" />
                <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="{{ __('mus.ventas.buscar_ph') }}">
            </div>

            <label class="sfil__f">{{ __('mus.tiempo.desde') }}
                <input type="date" name="desde" value="{{ $filtros['desde'] }}">
            </label>
            <label class="sfil__f">{{ __('mus.tiempo.hasta') }}
                <input type="date" name="hasta" value="{{ $filtros['hasta'] }}">
            </label>

            <select name="estado" class="sfil__sel">
                <option value="">{{ __('mus.ventas.todas') }}</option>
                <option value="pagada" @selected($filtros['estado'] === 'pagada')>{{ __('mus.ventas.pagadas') }}</option>
                <option value="anulada" @selected($filtros['estado'] === 'anulada')>{{ __('mus.ventas.anuladas') }}</option>
            </select>

            <select name="vendedor" class="sfil__sel">
                <option value="">{{ __('mus.ventas.todos_vendedores') }}</option>
                @foreach ($vendedores as $v)
                    <option value="{{ $v->id }}" @selected($filtros['vendedor'] == $v->id)>{{ $v->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="mb mb--primary mb--sm">{{ __('mus.acciones.filtrar') }}</button>
            <a href="{{ route('sales.index') }}" class="mb mb--ghost mb--sm">{{ __('mus.acciones.limpiar') }}</a>
        </form>

        @if ($sales->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>{{ __('mus.ventas.numero') }}</th>
                            <th>{{ __('mus.campos.cliente') }}</th>
                            <th>{{ __('mus.ventas.vendedor') }}</th>
                            <th class="num">{{ __('mus.ventas.articulos') }}</th>
                            <th class="num">{{ __('mus.campos.total') }}</th>
                            <th class="num">{{ __('mus.ventas.utilidad') }}</th>
                            <th>{{ __('mus.campos.estado') }}</th>
                            <th>{{ __('mus.campos.fecha') }}</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sales as $sale)
                            <tr data-row>
                                <td><span class="mt__id">{{ $sale->number }}</span></td>
                                <td>
                                    <b>{{ $sale->customer?->full_name ?? __('mus.vacio.consumidor_final') }}</b>
                                    @if ($sale->customer?->document_number)
                                        <span class="sub">{{ $sale->customer->document }}</span>
                                    @endif
                                </td>
                                <td>{{ $sale->user->name ?? '—' }}</td>
                                <td class="num">{{ $sale->items_count }}</td>
                                <td class="num"><b><i class="moneda">$</i>{{ number_format((float) $sale->total, 0, ',', '.') }}</b></td>
                                <td class="num">
                                    <span style="color:{{ $sale->anulada ? 'var(--muted-2)' : 'var(--ok)' }}">
                                        ${{ number_format((float) $sale->profit_total, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td>
                                    @if ($sale->anulada)
                                        <x-mus.badge tone="bad" :dot="true">{{ __('mus.ventas.anulada') }}</x-mus.badge>
                                    @else
                                        <x-mus.badge tone="ok" :dot="true">{{ __('mus.ventas.pagada') }}</x-mus.badge>
                                    @endif
                                </td>
                                <td>
                                    {{ Formato::enPalabras($sale->sold_at, 'D MMM, HH:mm', '—') }}
                                </td>
                                <td class="act">
                                    <span class="mt__acts">
                                        <x-mus.btn href="{{ route('sales.show', $sale) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" :title="__('mus.acciones.ver')">
                                            <x-mus.icon name="eye" :w="15" />
                                        </x-mus.btn>
                                        <x-mus.btn href="{{ route('sales.recibo', $sale) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" :title="__('mus.ventas.recibo')"
                                                   target="_blank">
                                            <x-mus.icon name="id" :w="15" />
                                        </x-mus.btn>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$sales" :label="__('mus.entidades.ventas')" />
        @else
            <x-mus.empty icon="money" :title="__('mus.ventas.vacio_titulo')"
                         :text="__('mus.ventas.vacio_texto')">
                <x-slot name="action">
                    @can('ventas.crear')
                        <x-mus.btn href="{{ route('pos.index') }}" variant="primary" icon="plus">
                            {{ __('mus.ventas.ir_al_pos') }}
                        </x-mus.btn>
                    @endcan
                </x-slot>
            </x-mus.empty>
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .skpi{ display:grid; gap:12px; margin-bottom:16px;
               grid-template-columns:repeat(auto-fit,minmax(168px,1fr)); }
        .skpi__c{ padding:14px 16px; background:var(--card); border:1px solid var(--line);
                  border-radius:12px; }
        .skpi__c > span{ display:block; font-size:11.1px; letter-spacing:.05em; text-transform:uppercase;
                         color:var(--muted-2); font-weight:600; }
        .skpi__c b{ display:block; margin:6px 0 2px; font-size:22px; font-weight:700;
                    letter-spacing:-.03em; color:var(--ink); line-height:1.05;
                    font-variant-numeric:tabular-nums; }
        .skpi__c b.ok{ color:var(--ok); }
        .skpi__c b.bad{ color:var(--bad); }
        .skpi__c em{ display:block; font-style:normal; font-size:11.4px; color:var(--muted); }

        .sfil{ display:flex; align-items:center; gap:9px; flex-wrap:wrap;
               padding:13px 16px; border-bottom:1px solid var(--line-2); background:var(--paper); }
        .sfil__s{ display:flex; align-items:center; gap:8px; flex:1 1 200px; min-width:180px;
                  padding:0 12px; height:36px; background:#fff; border:1px solid var(--line);
                  border-radius:9px; color:var(--muted); }
        .sfil__s:focus-within{ border-color:var(--a-400); box-shadow:0 0 0 3px rgba(46,110,168,.12); }
        .sfil__s input{ flex:1; border:0; outline:0; background:transparent; font:inherit;
                        font-size:13px; color:var(--ink); }
        .sfil__f{ display:flex; align-items:center; gap:6px; font-size:12.2px; color:var(--muted); }
        .sfil__f input{ height:36px; padding:0 9px; border:1px solid var(--line); border-radius:9px;
                        background:#fff; font:inherit; font-size:12.6px; color:var(--ink); }
        .sfil__sel{ height:36px; padding:0 28px 0 11px; border:1px solid var(--line); border-radius:9px;
                    background:#fff; font:inherit; font-size:12.6px; color:var(--ink); cursor:pointer; }

        /* ── Celular: un filtro por línea, campos grandes ── */
        @media (max-width:760px){
            .sfil{ gap:8px; }
            .sfil__s{ flex:1 0 100%; min-width:0; height:42px; }
            .sfil__s input{ font-size:16px; }
            .sfil__f{ flex:1 1 100%; flex-wrap:wrap; }
            .sfil__f input{ flex:1 1 120px; min-width:0; height:42px; font-size:16px; }
            .sfil__sel{ flex:1 0 100%; width:100%; min-width:0; height:42px; font-size:16px; }
            .sfil .mb{ flex:1 1 auto; }
        }
    </style>
    @endpush
</x-mus.page>
