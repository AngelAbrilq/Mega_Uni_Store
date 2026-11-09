@php
    $tono = ['entrada' => 'ok', 'salida' => 'bad', 'ajuste' => 'warn', 'inicial' => 'info'];
@endphp

<x-mus.page title="Inventario" subtitle="El kardex: por qué el stock es el que es" icon="stock">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('products.index', ['bajo' => 1]) }}" icon="alert">
            Productos por reponer
        </x-mus.btn>
    </x-slot>

    <div class="ikpi" data-reveal data-stagger>
        <div class="ikpi__c">
            <span>Valor del inventario</span>
            <b><i class="moneda">$</i>{{ number_format($resumen['valor'], 0, ',', '.') }}</b>
            <em>al costo</em>
        </div>
        <div class="ikpi__c">
            <span>Unidades en piso</span>
            <b data-count="{{ $resumen['unidades'] }}">0</b>
        </div>
        <div class="ikpi__c">
            <span>Por debajo del mínimo</span>
            <b class="{{ $resumen['bajos'] ? 'warn' : '' }}" data-count="{{ $resumen['bajos'] }}">0</b>
        </div>
        <div class="ikpi__c">
            <span>Agotados</span>
            <b class="{{ $resumen['agotados'] ? 'bad' : '' }}" data-count="{{ $resumen['agotados'] }}">0</b>
        </div>
    </div>

    <x-mus.panel :pad="false" :reveal="true">
        <form method="GET" action="{{ route('inventory.index') }}" class="ifil">
            <select name="producto" class="ifil__sel">
                <option value="">Todos los productos</option>
                @foreach ($productos as $p)
                    <option value="{{ $p->id }}" @selected($filtros['producto'] == $p->id)>
                        {{ $p->sku ? $p->sku . ' · ' : '' }}{{ $p->name }}
                    </option>
                @endforeach
            </select>

            <select name="motivo" class="ifil__sel">
                <option value="">Todos los motivos</option>
                @foreach ($motivos as $k => $v)
                    <option value="{{ $k }}" @selected($filtros['motivo'] === $k)>{{ $v }}</option>
                @endforeach
            </select>

            <label class="ifil__f">Desde <input type="date" name="desde" value="{{ $filtros['desde'] }}"></label>
            <label class="ifil__f">Hasta <input type="date" name="hasta" value="{{ $filtros['hasta'] }}"></label>

            <button type="submit" class="mb mb--primary mb--sm">Filtrar</button>
            <a href="{{ route('inventory.index') }}" class="mb mb--ghost mb--sm">Limpiar</a>
        </form>

        @if ($movimientos->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Motivo</th>
                            <th class="num">Cantidad</th>
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
                                    @if ($m->product)
                                        <a href="{{ route('inventory.kardex', $m->product) }}">
                                            <b>{{ $m->product->name }}</b>
                                        </a>
                                        @if ($m->product->sku)<span class="sub">{{ $m->product->sku }}</span>@endif
                                    @else
                                        <span style="color:var(--muted-2)">Producto eliminado</span>
                                    @endif
                                </td>
                                <td>
                                    <x-mus.badge :tone="$tono[$m->type] ?? 'soft'">
                                        {{ $m->reason_label }}
                                    </x-mus.badge>
                                </td>
                                <td class="num">
                                    <b style="color:{{ $q >= 0 ? 'var(--ok)' : 'var(--bad)' }}">
                                        {{ $q > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($q, 2, ',', '.'), '0'), ',') }}
                                    </b>
                                </td>
                                <td class="num">{{ rtrim(rtrim(number_format((float) $m->balance_after, 2, ',', '.'), '0'), ',') }}</td>
                                <td>{{ $m->user->name ?? 'Sistema' }}</td>
                                <td><span class="sub">{{ \Illuminate\Support\Str::limit($m->notes, 40) ?: '—' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$movimientos" label="movimientos" />
        @else
            <x-mus.empty icon="stock" title="Sin movimientos de inventario"
                         text="Cada venta, compra o ajuste dejará su huella aquí." />
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

        .ifil{ display:flex; align-items:center; gap:9px; flex-wrap:wrap;
               padding:13px 16px; border-bottom:1px solid var(--line-2); background:var(--paper); }
        .ifil__sel{ height:36px; padding:0 28px 0 11px; border:1px solid var(--line); border-radius:9px;
                    background:#fff; font:inherit; font-size:12.6px; color:var(--ink); cursor:pointer;
                    max-width:250px; }
        .ifil__f{ display:flex; align-items:center; gap:6px; font-size:12.2px; color:var(--muted); }
        .ifil__f input{ height:36px; padding:0 9px; border:1px solid var(--line); border-radius:9px;
                        background:#fff; font:inherit; font-size:12.6px; color:var(--ink); }

        /* ── Celular: un filtro por línea, campos grandes ── */
        @media (max-width:760px){
            .ifil{ gap:8px; }
            .ifil__sel{ flex:1 0 100%; width:100%; min-width:0; height:42px; font-size:16px; }
            .ifil__f{ flex:1 1 100%; flex-wrap:wrap; }
            .ifil__f input{ flex:1 1 120px; min-width:0; height:42px; font-size:16px; }
            .ifil .mb{ flex:1 1 auto; }
        }
    </style>
    @endpush
</x-mus.page>
