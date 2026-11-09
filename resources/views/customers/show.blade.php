@php use App\Support\Formato; @endphp

<x-mus.page title="{{ trim($customer->first_name . ' ' . $customer->last_name) }}" subtitle="Ficha de cliente" icon="users"
            :crumbs="['Clientes' => route('customers.index'), 'Detalle' => null]">

    <x-slot name="actions">
        @can('clientes.editar')<x-mus.btn href="{{ route('customers.edit', $customer) }}" icon="pencil">Editar</x-mus.btn>@endcan
        @can('clientes.eliminar')<x-mus.del :action="route('customers.destroy', $customer)" what="el cliente" />@endcan
    </x-slot>

    <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start"
         class="mshow-grid">

        <x-mus.panel title="Información" sub="Datos registrados">
            <div class="mficha">
                <x-mus.avatar :src="$customer->imagen" :letras="$customer->iniciales"
                              :color="$customer->color_avatar" :size="64" :round="true" />
                <div class="mficha__t">
                    <b>{{ $customer->full_name }}</b>
                    <span>{{ $customer->email ?: ($customer->phone ?: 'Sin datos de contacto') }}</span>
                </div>
            </div>

            <dl class="mdl">
                <div>
                    <dt>Nombres</dt>
                    <dd>{{ $customer->first_name }}</dd>
                </div>
                <div>
                    <dt>Apellidos</dt>
                    <dd>{{ $customer->last_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Correo</dt>
                    <dd>{{ $customer->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Teléfono</dt>
                    <dd>{{ $customer->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Documento</dt>
                    <dd>{{ trim(($customer->document_type ?? '') . ' ' . ($customer->document_number ?? '')) ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Dirección</dt>
                    <dd>{{ $customer->address ?: '—' }}</dd>
                </div>
            </dl>
        </x-mus.panel>

        <x-mus.panel title="Registro" sub="Trazabilidad">
            <dl class="mdl">
                <div>
                    <dt>Identificador</dt>
                    <dd>#{{ $customer->id }}</dd>
                </div>
                <div>
                    <dt>Creado</dt>
                    <dd>{{ Formato::enPalabras($customer->created_at, 'D MMM YYYY, HH:mm', '—') }}</dd>
                </div>
                <div>
                    <dt>Última edición</dt>
                    <dd>{{ Formato::enPalabras($customer->updated_at, 'D MMM YYYY, HH:mm', '—') }}</dd>
                </div>
            </dl>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('customers.index') }}" icon="back" :block="true">
                    Volver al listado
                </x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </div>

    {{-- ═══ CL-TDC-001 · CL-PUN-001 · El cliente en cada local ═══ --}}
    @php
        $activas  = $customer->tiendas->where('is_active', true);
        $puntosTot = (int) $activas->sum('points_balance');
    @endphp
    <div class="mshow-grid lyl" style="display:grid;gap:16px;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start;margin-top:16px">
        <x-mus.panel title="Fidelidad por local" :pad="false"
                     :sub="$puntosActivos ? 'Puntos, compras y total gastado en cada local' : 'La acumulación de puntos está apagada en Ajustes'">
            <div class="lyl__sum">
                <span class="lyl__star"><x-mus.icon name="star" :w="20" stroke-width="1.8" /></span>
                <div>
                    <b>{{ number_format($puntosTot, 0, ',', '.') }} <small>puntos</small></b>
                    <span>en {{ $activas->count() }} {{ $activas->count() === 1 ? 'local' : 'locales' }}</span>
                </div>
            </div>

            @if ($customer->tiendas->count())
                <div class="mt-wrap">
                    <table class="mt">
                        <thead>
                            <tr>
                                <th>Local</th>
                                <th class="num">Puntos</th>
                                <th class="num">Compras</th>
                                <th class="num">Total gastado</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customer->tiendas as $c)
                                <tr data-row>
                                    <td><b>{{ $locales[$c->tienda_id] ?? 'Local #' . $c->tienda_id }}</b></td>
                                    <td class="num"><b>{{ number_format($c->points_balance, 0, ',', '.') }}</b></td>
                                    <td class="num">{{ $c->purchases_count }}</td>
                                    <td class="num"><i class="moneda">$</i>{{ number_format((float) $c->total_spent, 0, ',', '.') }}</td>
                                    <td>
                                        @if ($c->is_active)
                                            <x-mus.badge tone="ok" :dot="true">Asociado</x-mus.badge>
                                        @else
                                            <x-mus.badge tone="off" :dot="true">Inactivo</x-mus.badge>
                                        @endif
                                    </td>
                                    <td class="num">
                                        @can('clientes.editar')
                                            @if (in_array($c->tienda_id, $misLocales, true))
                                                @if ($c->is_active)
                                                    <form method="POST" action="{{ route('customers.locales.destroy', [$customer, $c->tienda_id]) }}">
                                                        @csrf @method('DELETE')
                                                        <x-mus.btn type="submit" :sm="true" icon="close">Desasociar</x-mus.btn>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('customers.locales.store', $customer) }}">
                                                        @csrf <input type="hidden" name="tienda_id" value="{{ $c->tienda_id }}">
                                                        <x-mus.btn type="submit" :sm="true" icon="check">Reactivar</x-mus.btn>
                                                    </form>
                                                @endif
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-mus.empty icon="star" title="Todavía no compra en ningún local"
                             text="Al venderle con su nombre en el punto de venta queda asociado al local y empieza a sumar puntos." />
            @endif

            @can('clientes.editar')
                @if ($porAsociar->count())
                    <x-slot name="foot">
                        <form method="POST" action="{{ route('customers.locales.store', $customer) }}" class="lyl__form">
                            @csrf
                            <x-mus.select name="tienda_id" label="Asociar a otro local" :options="$porAsociar->all()" />
                            <x-mus.btn type="submit" variant="primary" icon="plus">Asociar</x-mus.btn>
                        </form>
                    </x-slot>
                @endif
            @endcan
        </x-mus.panel>

        <x-mus.panel title="Movimientos de puntos" sub="Los últimos 10" :pad="false">
            @if ($movimientos->count())
                <ul class="lyl__mov">
                    @foreach ($movimientos as $m)
                        <li>
                            <span class="lyl__pt {{ $m->points >= 0 ? 'is-up' : 'is-down' }}">{{ $m->points >= 0 ? '+' : '' }}{{ number_format($m->points, 0, ',', '.') }}</span>
                            <div>
                                <b>
                                    @if ($m->sale)
                                        <a href="{{ route('sales.show', $m->sale_id) }}">{{ $m->notes }}</a>
                                    @else
                                        {{ $m->notes }}
                                    @endif
                                </b>
                                <span>{{ $locales[$m->tienda_id] ?? '' }} · {{ Formato::enPalabras($m->created_at, 'D MMM, HH:mm', '') }}</span>
                            </div>
                            <em>saldo {{ number_format($m->balance_after, 0, ',', '.') }}</em>
                        </li>
                    @endforeach
                </ul>
            @else
                <x-mus.empty icon="clock" title="Sin movimientos" text="Aquí aparecen los puntos ganados y los devueltos." />
            @endif
        </x-mus.panel>
    </div>

    @push('styles')
        <style>
            @media (max-width:900px){ .mshow-grid{ grid-template-columns:minmax(0,1fr) !important; } }
            .lyl__sum{ display:flex; align-items:center; gap:12px; padding:14px 18px; border-bottom:1px solid var(--line, #e7e7ea); }
            .lyl__star{ width:40px; height:40px; border-radius:12px; display:grid; place-items:center;
                        background:color-mix(in srgb, #f5a524 16%, transparent); color:#c47f05; }
            .lyl__sum b{ display:block; font-size:22px; letter-spacing:-.02em; line-height:1.1; }
            .lyl__sum b small{ font-size:12px; font-weight:600; opacity:.6; }
            .lyl__sum span{ font-size:12.5px; opacity:.65; }
            .lyl__form{ display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
            .lyl__form > div{ flex:1 1 200px; }
            .lyl__mov{ list-style:none; margin:0; padding:0; }
            .lyl__mov li{ display:flex; gap:12px; align-items:center; padding:11px 16px; border-bottom:1px solid var(--line, #e7e7ea); }
            .lyl__mov li:last-child{ border-bottom:0; }
            .lyl__mov li > div{ flex:1; min-width:0; }
            .lyl__mov b{ display:block; font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .lyl__mov b a{ color:inherit; }
            .lyl__mov span{ font-size:11.5px; opacity:.6; }
            .lyl__mov em{ font-style:normal; font-size:11.5px; opacity:.55; white-space:nowrap; }
            .lyl__pt{ min-width:52px; text-align:center; font-weight:700; font-size:13px; padding:4px 8px; border-radius:8px; }
            .lyl__pt.is-up{ background:color-mix(in srgb, #17a34a 14%, transparent); color:#15803d; }
            .lyl__pt.is-down{ background:color-mix(in srgb, #dc2626 12%, transparent); color:#b91c1c; }
        </style>
    @endpush
</x-mus.page>
