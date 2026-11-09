@php
    $tonoStock = ['agotado' => 'bad', 'bajo' => 'warn', 'ok' => 'ok'];
    $textoStock = ['agotado' => 'Agotado', 'bajo' => 'Bajo', 'ok' => 'Disponible'];
@endphp

<x-mus.page title="Productos" subtitle="El catálogo completo de tu tienda" icon="box">
    <x-slot name="actions">
        @can('productos.crear')
            <x-mus.btn href="{{ route('products.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo producto" data-modal-ancho="820">
                Nuevo
            </x-mus.btn>
        @endcan
    </x-slot>

    {{-- ══════════ Cifras de cabecera ══════════ --}}
    <div class="pkpi" data-reveal data-stagger>
        <div class="pkpi__c">
            <span class="pkpi__ico" style="background:var(--a-500)"><x-mus.icon name="box" :w="15" /></span>
            <div>
                <b data-count="{{ $resumen['total'] }}">0</b>
                <span>productos en catálogo</span>
            </div>
        </div>
        <div class="pkpi__c">
            <span class="pkpi__ico" style="background:var(--ok)"><x-mus.icon name="check" :w="15" /></span>
            <div>
                <b data-count="{{ $resumen['activos'] }}">0</b>
                <span>activos para la venta</span>
            </div>
        </div>
        <a class="pkpi__c pkpi__c--link {{ $resumen['bajos'] ? 'is-alert' : '' }}"
           href="{{ route('products.index', ['bajo' => 1]) }}">
            <span class="pkpi__ico" style="background:var(--warn)"><x-mus.icon name="alert" :w="15" /></span>
            <div>
                <b data-count="{{ $resumen['bajos'] }}">0</b>
                <span>por debajo del mínimo</span>
            </div>
        </a>
        <div class="pkpi__c">
            <span class="pkpi__ico" style="background:var(--n-700)"><x-mus.icon name="money" :w="15" /></span>
            <div>
                <b><i class="moneda">$</i>{{ number_format($resumen['inventario'], 0, ',', '.') }}</b>
                <span>invertido en inventario</span>
            </div>
        </div>
    </div>

    <x-mus.panel :pad="false" :reveal="true">

        {{-- ══════════ Filtros (van al servidor, no solo a la página actual) ══════════ --}}
        <form method="GET" action="{{ route('products.index') }}" class="pfil">
            <div class="pfil__search">
                <x-mus.icon name="search" :w="15" stroke-width="2" />
                <input type="text" name="q" value="{{ $filtros['q'] }}"
                       placeholder="Buscar por nombre, SKU o código de barras…">
            </div>

            <select name="categoria" class="pfil__sel" onchange="this.form.submit()">
                <option value="">Todas las categorías</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected($filtros['categoria'] == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>

            <select name="estado" class="pfil__sel" onchange="this.form.submit()">
                <option value="">Activos e inactivos</option>
                <option value="activos" @selected($filtros['estado'] === 'activos')>Solo activos</option>
                <option value="inactivos" @selected($filtros['estado'] === 'inactivos')>Solo inactivos</option>
            </select>

            <select name="orden" class="pfil__sel" onchange="this.form.submit()">
                <option value="">Más recientes</option>
                <option value="nombre" @selected($filtros['orden'] === 'nombre')>Nombre (A–Z)</option>
                <option value="precio" @selected($filtros['orden'] === 'precio')>Precio (mayor)</option>
                <option value="stock"  @selected($filtros['orden'] === 'stock')>Existencias (menor)</option>
            </select>

            <label class="pfil__chk">
                <input type="checkbox" name="bajo" value="1" @checked($filtros['bajo'])
                       onchange="this.form.submit()">
                <span>Solo stock bajo</span>
            </label>

            <button type="submit" class="mb mb--primary mb--sm">Filtrar</button>

            @if ($filtros['q'] || $filtros['categoria'] || $filtros['estado'] || $filtros['orden'] || $filtros['bajo'])
                <a href="{{ route('products.index') }}" class="mb mb--ghost mb--sm">Limpiar</a>
            @endif

            <span class="pfil__sp"></span>
            <span class="mtool__count">{{ number_format($products->total(), 0, ',', '.') }} resultados</span>
        </form>

        @if ($products->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Producto</th>
                            <th class="num">Existencias</th>
                            <th class="num">Costo</th>
                            <th class="num">Precio</th>
                            <th class="num">Margen</th>
                            <th>Estado</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr data-row>
                                <td><span class="mt__id">{{ $product->sku ?? '#' . $product->id }}</span></td>
                                <td>
                                    <div class="mt__ent">
                                        <x-mus.avatar :src="$product->imagen" :letras="$product->iniciales"
                                                      :color="$product->color_avatar" :size="34" />
                                        <span>
                                            <b>{{ $product->name }}</b>
                                            <span class="sub">
                                                {{ optional($product->category)->name ?? 'Sin categoría' }}
                                                @if ($product->supplier) · {{ $product->supplier->name }} @endif
                                            </span>
                                        </span>
                                    </div>
                                </td>
                                <td class="num">
                                    <x-mus.badge :tone="$tonoStock[$product->stock_state]" :dot="true">
                                        {{ $product->stock_texto }}
                                        {{ optional($product->unit)->symbol }}
                                    </x-mus.badge>
                                    <span class="sub" style="display:block;margin-top:3px">
                                        mín. {{ $product->min_stock_texto }}
                                    </span>
                                </td>
                                <td class="num"><span style="color:var(--muted)"><i class="moneda">$</i>{{ number_format($product->cost, 0, ',', '.') }}</span></td>
                                <td class="num"><b><i class="moneda">$</i>{{ number_format($product->price, 0, ',', '.') }}</b></td>
                                <td class="num">
                                    <x-mus.badge :tone="$product->margin >= 30 ? 'ok' : ($product->margin > 0 ? 'warn' : 'off')">
                                        {{ number_format($product->margin, 0) }}%
                                    </x-mus.badge>
                                </td>
                                <td>
                                    @if ($product->is_active)
                                        <x-mus.badge tone="ok" :dot="true">Activo</x-mus.badge>
                                    @else
                                        <x-mus.badge tone="off" :dot="true">Inactivo</x-mus.badge>
                                    @endif
                                </td>
                                <td class="act">
                                    <span class="mt__acts">
                                        @can('productos.ver')
                                            <x-mus.btn href="{{ route('products.show', $product) }}"
                                                       variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                                <x-mus.icon name="eye" :w="15" />
                                            </x-mus.btn>
                                        @endcan
                                        @can('productos.editar')
                                            <x-mus.btn href="{{ route('products.edit', $product) }}"
                                                       variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                                <x-mus.icon name="pencil" :w="15" />
                                            </x-mus.btn>
                                        @endcan
                                        @can('productos.eliminar')
                                            <x-mus.del :action="route('products.destroy', $product)"
                                                       what="el producto" />
                                        @endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$products" label="productos" />
        @else
            <x-mus.empty icon="box"
                         title="{{ $filtros['q'] || $filtros['categoria'] || $filtros['bajo'] ? 'Ningún producto coincide' : 'El catálogo está vacío' }}"
                         text="{{ $filtros['q'] || $filtros['categoria'] || $filtros['bajo'] ? 'Prueba con otra búsqueda o quita los filtros.' : 'Agrega tu primer producto para empezar a ver movimiento en el panel.' }}">
                <x-slot name="action">
                    @if ($filtros['q'] || $filtros['categoria'] || $filtros['estado'] || $filtros['bajo'])
                        <x-mus.btn href="{{ route('products.index') }}" icon="back">Quitar filtros</x-mus.btn>
                    @elseif (Route::has('products.create'))
                        <x-mus.btn href="{{ route('products.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo producto" data-modal-ancho="820">
                            Nuevo producto
                        </x-mus.btn>
                    @endif
                </x-slot>
            </x-mus.empty>
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .pkpi{ display:grid; gap:12px; margin-bottom:16px;
               grid-template-columns:repeat(auto-fit,minmax(206px,1fr)); }
        .pkpi__c{ display:flex; align-items:center; gap:12px; padding:14px 16px;
                  background:var(--card); border:1px solid var(--line); border-radius:12px;
                  text-decoration:none; color:inherit;
                  transition:border-color .2s var(--e-soft), transform .2s var(--e-soft),
                             box-shadow .2s var(--e-soft); }
        .pkpi__c--link:hover{ border-color:var(--a-400); transform:translateY(-2px);
                              box-shadow:0 10px 26px -18px rgba(16,24,37,.5); }
        .pkpi__c.is-alert{ border-color:rgba(150,112,60,.4); background:rgba(150,112,60,.045); }
        .pkpi__ico{ display:grid; place-items:center; width:34px; height:34px; flex:none;
                    border-radius:10px; color:#fff; }
        .pkpi__c b{ display:block; font-size:21px; font-weight:700; letter-spacing:-.025em;
                    color:var(--ink); line-height:1.1; font-variant-numeric:tabular-nums; }
        .pkpi__c span:last-child{ display:block; font-size:11.8px; color:var(--muted); margin-top:2px; }

        .pfil{ display:flex; align-items:center; gap:9px; flex-wrap:wrap;
               padding:13px 16px; border-bottom:1px solid var(--line-2); background:var(--paper); }
        .pfil__search{ display:flex; align-items:center; gap:8px; flex:1 1 240px; min-width:200px;
                       padding:0 12px; height:36px; background:#fff; border:1px solid var(--line);
                       border-radius:9px; color:var(--muted);
                       transition:border-color .18s var(--e-soft), box-shadow .18s var(--e-soft); }
        .pfil__search:focus-within{ border-color:var(--a-400); box-shadow:0 0 0 3px rgba(46,110,168,.12); }
        .pfil__search input{ flex:1; border:0; outline:0; background:transparent;
                             font:inherit; font-size:13px; color:var(--ink); }
        .pfil__sel{ height:36px; padding:0 28px 0 11px; border:1px solid var(--line); border-radius:9px;
                    background:#fff; font:inherit; font-size:12.6px; color:var(--ink); cursor:pointer; }
        .pfil__chk{ display:flex; align-items:center; gap:7px; height:36px; padding:0 12px;
                    border:1px solid var(--line); border-radius:9px; background:#fff;
                    font-size:12.6px; color:var(--ink-2); cursor:pointer; user-select:none; }
        .pfil__chk input{ accent-color:var(--a-500); }
        .pfil__sp{ flex:1 1 auto; }

        /* La miniatura ocupa el mismo hueco que la inicial de respaldo. */
        .mt__av--img{ object-fit:cover; padding:0; }

        /* ── Celular: cada filtro en su propia línea, a lo ancho ── */
        @media (max-width:760px){
            .pfil{ gap:8px; }
            .pfil__search{ flex:1 0 100%; min-width:0; height:42px; }
            .pfil__search input{ font-size:16px; }
            .pfil__sel{ flex:1 0 100%; width:100%; min-width:0; height:42px; font-size:16px; }
            .pfil__chk{ flex:1 1 auto; height:42px; }
            .pfil__sp{ display:none; }
            .pfil .mb{ flex:1 1 auto; }
        }
    </style>
    @endpush
</x-mus.page>
