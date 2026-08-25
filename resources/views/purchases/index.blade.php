@php
    use App\Support\Formato;

    $tonos = ['borrador' => 'warn', 'recibida' => 'ok', 'anulada' => 'bad'];
@endphp

<x-mus.page title="Compras" subtitle="Pedidos a proveedores y entrada de mercancía" icon="truck">
    <x-slot name="actions">
        @can('compras.crear')
            <x-mus.btn href="{{ route('purchases.create') }}" variant="primary" icon="plus">Nueva compra</x-mus.btn>
        @endcan
    </x-slot>

    <div class="skpi" data-reveal data-stagger>
        <div class="skpi__c">
            <span>En borrador</span>
            <b class="{{ $resumen['borradores'] ? 'warn' : '' }}" data-count="{{ $resumen['borradores'] }}">0</b>
            <em>esperando entrada</em>
        </div>
        <div class="skpi__c">
            <span>Por recibir</span>
            <b><i class="moneda">$</i>{{ number_format($resumen['pendiente'], 0, ',', '.') }}</b>
        </div>
        <div class="skpi__c">
            <span>Recibidas</span>
            <b data-count="{{ $resumen['recibidas'] }}">0</b>
        </div>
        <div class="skpi__c">
            <span>Comprado este mes</span>
            <b><i class="moneda">$</i>{{ number_format($resumen['mes'], 0, ',', '.') }}</b>
        </div>
    </div>

    <x-mus.panel :pad="false" :reveal="true">
        <form method="GET" action="{{ route('purchases.index') }}" class="sfil">
            <div class="sfil__s">
                <x-mus.icon name="search" :w="15" stroke-width="2" />
                <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="Número, factura o proveedor…">
            </div>

            <select name="estado" class="sfil__sel">
                <option value="">Todos los estados</option>
                <option value="borrador" @selected($filtros['estado'] === 'borrador')>Borrador</option>
                <option value="recibida" @selected($filtros['estado'] === 'recibida')>Recibidas</option>
                <option value="anulada"  @selected($filtros['estado'] === 'anulada')>Anuladas</option>
            </select>

            <select name="proveedor" class="sfil__sel">
                <option value="">Todos los proveedores</option>
                @foreach ($proveedores as $p)
                    <option value="{{ $p->id }}" @selected($filtros['proveedor'] == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>

            <button type="submit" class="mb mb--primary mb--sm">Filtrar</button>
            <a href="{{ route('purchases.index') }}" class="mb mb--ghost mb--sm">Limpiar</a>
        </form>

        @if ($purchases->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Proveedor</th>
                            <th>Factura</th>
                            <th class="num">Ítems</th>
                            <th class="num">Total</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchases as $c)
                            <tr data-row>
                                <td><span class="mt__id">{{ $c->number }}</span></td>
                                <td>
                                    <b>{{ $c->supplier->name ?? '—' }}</b>
                                    <span class="sub">registró {{ $c->user->name ?? '—' }}</span>
                                </td>
                                <td>{{ $c->invoice_number ?: '—' }}</td>
                                <td class="num">{{ $c->items_count }}</td>
                                <td class="num"><b><i class="moneda">$</i>{{ number_format((float) $c->total, 0, ',', '.') }}</b></td>
                                <td>
                                    <x-mus.badge :tone="$tonos[$c->status] ?? 'soft'" :dot="true">
                                        {{ $c->estado_label }}
                                    </x-mus.badge>
                                </td>
                                <td>
                                    {{ Formato::enPalabras($c->ordered_at, 'D MMM YYYY') }}
                                    @if ($c->received_at)
                                        <span class="sub">recibida {{ $c->received_at->format('d/m') }}</span>
                                    @endif
                                </td>
                                <td class="act">
                                    <span class="mt__acts">
                                        <x-mus.btn href="{{ route('purchases.show', $c) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                            <x-mus.icon name="eye" :w="15" />
                                        </x-mus.btn>
                                        @can('compras.editar')
                                            @if ($c->editable)
                                                <x-mus.btn href="{{ route('purchases.edit', $c) }}"
                                                           variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                                    <x-mus.icon name="pencil" :w="15" />
                                                </x-mus.btn>
                                            @endif
                                        @endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$purchases" label="compras" />
        @else
            <x-mus.empty icon="truck" title="Sin compras registradas"
                         text="Registra el pedido al proveedor y dale entrada cuando llegue: el inventario sube solo.">
                <x-slot name="action">
                    @can('compras.crear')
                        <x-mus.btn href="{{ route('purchases.create') }}" variant="primary" icon="plus">
                            Registrar la primera compra
                        </x-mus.btn>
                    @endcan
                </x-slot>
            </x-mus.empty>
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .skpi{ display:grid; gap:12px; margin-bottom:16px;
               grid-template-columns:repeat(auto-fit,minmax(178px,1fr)); }
        .skpi__c{ padding:14px 16px; background:var(--card); border:1px solid var(--line);
                  border-radius:12px; }
        .skpi__c > span{ display:block; font-size:11.1px; letter-spacing:.05em; text-transform:uppercase;
                         color:var(--muted-2); font-weight:600; }
        .skpi__c b{ display:block; margin:6px 0 2px; font-size:22px; font-weight:700;
                    letter-spacing:-.03em; color:var(--ink); line-height:1.05;
                    font-variant-numeric:tabular-nums; }
        .skpi__c b.warn{ color:var(--warn); }
        .skpi__c em{ display:block; font-style:normal; font-size:11.4px; color:var(--muted); }

        .sfil{ display:flex; align-items:center; gap:9px; flex-wrap:wrap;
               padding:13px 16px; border-bottom:1px solid var(--line-2); background:var(--paper); }
        .sfil__s{ display:flex; align-items:center; gap:8px; flex:1 1 200px; min-width:180px;
                  padding:0 12px; height:36px; background:#fff; border:1px solid var(--line);
                  border-radius:9px; color:var(--muted); }
        .sfil__s:focus-within{ border-color:var(--a-400); box-shadow:0 0 0 3px rgba(46,110,168,.12); }
        .sfil__s input{ flex:1; border:0; outline:0; background:transparent; font:inherit;
                        font-size:13px; color:var(--ink); }
        .sfil__sel{ height:36px; padding:0 28px 0 11px; border:1px solid var(--line); border-radius:9px;
                    background:#fff; font:inherit; font-size:12.6px; color:var(--ink); cursor:pointer; }

        /* ── Celular: un filtro por línea, campos grandes ── */
        @media (max-width:760px){
            .sfil{ gap:8px; }
            .sfil__s{ flex:1 0 100%; min-width:0; height:42px; }
            .sfil__s input{ font-size:16px; }
            .sfil__sel{ flex:1 0 100%; width:100%; min-width:0; height:42px; font-size:16px; }
            .sfil .mb{ flex:1 1 auto; }
        }
    </style>
    @endpush
</x-mus.page>
