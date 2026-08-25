@php
    use App\Support\Formato;

    $tonoStock  = ['agotado' => 'bad', 'bajo' => 'warn', 'ok' => 'ok'];
    $textoStock = ['agotado' => 'Agotado', 'bajo' => 'Por debajo del mínimo', 'ok' => 'Disponible'];

    // Precio con impuesto incluido, para saber qué paga realmente el cliente.
    $impuesto = $product->tax;
    $valorImp = 0.0;
    if ($impuesto) {
        $valorImp = $impuesto->type === 'fixed'
            ? (float) $impuesto->rate
            : (float) $product->price * (float) $impuesto->rate / 100;
    }
    $precioFinal = (float) $product->price + $valorImp;
@endphp

<x-mus.page title="{{ $product->name }}" subtitle="Ficha de producto" icon="box"
            :crumbs="['Productos' => route('products.index'), 'Detalle' => null]">

    <x-slot name="actions">
        @can('productos.editar')
            <x-mus.btn href="{{ route('products.edit', $product) }}" icon="pencil">Editar</x-mus.btn>
        @endcan
        @can('productos.eliminar')
            <x-mus.del :action="route('products.destroy', $product)" what="el producto" />
        @endcan
    </x-slot>

    {{-- ══════════ Tira de cifras ══════════ --}}
    <div class="pshow-kpi" data-reveal data-stagger>
        <div class="pshow-kpi__c">
            <span>Existencias</span>
            <b>{{ $product->stock_texto }}
                <i>{{ optional($product->unit)->symbol }}</i></b>
            <x-mus.badge :tone="$tonoStock[$product->stock_state]" :dot="true">
                {{ $textoStock[$product->stock_state] }}
            </x-mus.badge>
        </div>
        <div class="pshow-kpi__c">
            <span>Ganancia por unidad</span>
            <b><i class="moneda">$</i>{{ number_format($product->profit, 0, ',', '.') }}</b>
            <x-mus.badge :tone="$product->margin >= 30 ? 'ok' : ($product->margin > 0 ? 'warn' : 'off')">
                {{ number_format($product->margin, 1) }}% de margen
            </x-mus.badge>
        </div>
        <div class="pshow-kpi__c">
            <span>Valor en bodega</span>
            <b><i class="moneda">$</i>{{ number_format($product->stock_value, 0, ',', '.') }}</b>
            <em>{{ $product->stock_texto }} × ${{ number_format($product->cost, 0, ',', '.') }}</em>
        </div>
        <div class="pshow-kpi__c">
            <span>Precio con impuesto</span>
            <b><i class="moneda">$</i>{{ number_format($precioFinal, 0, ',', '.') }}</b>
            <em>{{ $impuesto ? $impuesto->label : 'Sin impuesto asociado' }}</em>
        </div>
    </div>

    <div class="mshow-grid">

        <x-mus.panel title="Información" sub="Datos registrados">
            @if ($product->imagen)
                <figure class="pfoto">
                    <img src="{{ $product->imagen }}" alt="{{ $product->name }}">
                    <figcaption>{{ $product->image_url }}</figcaption>
                </figure>
            @endif

            <dl class="mdl">
                <div><dt>Nombre</dt><dd>{{ $product->name }}</dd></div>
                <div><dt>SKU</dt><dd>{{ $product->sku ?: '—' }}</dd></div>
                <div><dt>Código de barras</dt><dd>{{ $product->barcode ?: '—' }}</dd></div>
                <div>
                    <dt>Categoría</dt>
                    <dd>{{ $product->category?->path ?? '—' }}</dd>
                </div>
                <div><dt>Unidad</dt><dd>{{ optional($product->unit)->label ?? '—' }}</dd></div>
                <div><dt>Impuesto</dt><dd>{{ $impuesto?->label ?? '—' }}</dd></div>
                <div>
                    <dt>Proveedor</dt>
                    <dd>
                        @if ($product->supplier)
                            <a href="{{ route('suppliers.show', $product->supplier) }}">{{ $product->supplier->name }}</a>
                        @else — @endif
                    </dd>
                </div>
                <div><dt>Costo</dt><dd><i class="moneda">$</i>{{ number_format($product->cost, 2, ',', '.') }}</dd></div>
                <div><dt>Precio de venta</dt><dd><i class="moneda">$</i>{{ number_format($product->price, 2, ',', '.') }}</dd></div>
                <div><dt>Stock mínimo</dt><dd>{{ number_format($product->min_stock, 0, ',', '.') }}</dd></div>
                <div><dt>Descripción</dt><dd>{{ $product->description ?: '—' }}</dd></div>
                <div>
                    <dt>Estado</dt>
                    <dd>
                        @if ($product->is_active)
                            <x-mus.badge tone="ok" :dot="true">Activo</x-mus.badge>
                        @else
                            <x-mus.badge tone="off" :dot="true">Inactivo</x-mus.badge>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-mus.panel>

        <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1fr);align-content:start">
            <x-mus.panel title="Registro" sub="Trazabilidad">
                <dl class="mdl">
                    <div><dt>Identificador</dt><dd>#{{ $product->id }}</dd></div>
                    <div>
                        <dt>Creado por</dt>
                        <dd>{{ $product->creator?->name ?? 'Sistema' }}</dd>
                    </div>
                    <div>
                        <dt>Creado</dt>
                        <dd>{{ Formato::enPalabras($product->created_at, 'D MMM YYYY, HH:mm', '—') }}</dd>
                    </div>
                    <div>
                        <dt>Última edición</dt>
                        <dd>{{ $product->editor?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Editado</dt>
                        <dd>{{ Formato::enPalabras($product->updated_at, 'D MMM YYYY, HH:mm', '—') }}</dd>
                    </div>
                </dl>

                <x-slot name="foot">
                    <x-mus.btn href="{{ route('products.index') }}" icon="back" :block="true">
                        Volver al listado
                    </x-mus.btn>
                </x-slot>
            </x-mus.panel>

            @if ($relacionados->count())
                <x-mus.panel title="En la misma categoría" sub="Para comparar precios rápido">
                    <ul class="prel">
                        @foreach ($relacionados as $r)
                            <li>
                                <a href="{{ route('products.show', $r) }}">
                                    <span>
                                        <b>{{ $r->name }}</b>
                                        <i>{{ $r->sku ?: '#' . $r->id }} · {{ $r->stock }} und.</i>
                                    </span>
                                    <em><i class="moneda">$</i>{{ number_format($r->price, 0, ',', '.') }}</em>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-mus.panel>
            @endif
        </div>
    </div>

    @push('styles')
    <style>
        .mshow-grid{ display:grid; gap:16px; grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);
                     align-items:start; }
        @media (max-width:900px){ .mshow-grid{ grid-template-columns:minmax(0,1fr); } }

        .pshow-kpi{ display:grid; gap:12px; margin-bottom:16px;
                    grid-template-columns:repeat(auto-fit,minmax(196px,1fr)); }
        .pshow-kpi__c{ padding:15px 17px; background:var(--card); border:1px solid var(--line);
                       border-radius:12px; }
        .pshow-kpi__c > span:first-child{ display:block; font-size:11.4px; letter-spacing:.05em;
                                          text-transform:uppercase; color:var(--muted-2); font-weight:600; }
        .pshow-kpi__c b{ display:block; margin:6px 0 8px; font-size:24px; font-weight:700;
                         letter-spacing:-.03em; color:var(--ink); line-height:1;
                         font-variant-numeric:tabular-nums; }
        .pshow-kpi__c b i{ font-style:normal; font-size:13px; font-weight:600; color:var(--muted); }
        .pshow-kpi__c em{ display:block; font-style:normal; font-size:11.8px; color:var(--muted); }

        .pfoto{ margin:0 0 18px; }
        .pfoto img{ display:block; width:100%; max-height:280px; object-fit:cover;
                    border:1px solid var(--line); border-radius:12px; background:var(--paper); }
        .pfoto figcaption{ margin-top:7px; font-size:11.3px; color:var(--muted-2);
                           font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
                           overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

        .prel{ list-style:none; margin:0; padding:0; }
        .prel li + li{ border-top:1px solid var(--line-2); }
        .prel a{ display:flex; align-items:center; justify-content:space-between; gap:12px;
                 padding:10px 2px; text-decoration:none; color:inherit; }
        .prel a:hover b{ color:var(--a-600); }
        .prel b{ display:block; font-size:12.9px; font-weight:600; color:var(--ink); }
        .prel i{ display:block; font-style:normal; font-size:11.4px; color:var(--muted); margin-top:1px; }
        .prel em{ font-style:normal; font-size:13px; font-weight:700; color:var(--ink);
                  font-variant-numeric:tabular-nums; white-space:nowrap; }
    </style>
    @endpush
</x-mus.page>
