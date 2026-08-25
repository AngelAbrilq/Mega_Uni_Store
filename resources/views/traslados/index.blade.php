@php use App\Support\Formato; @endphp

<x-mus.page title="Traslados" subtitle="Mercancía que se movió de un local a otro" icon="back">
    <x-slot name="actions">
        @can('inventario.ajustar')
            <x-mus.btn href="{{ route('traslados.create') }}" variant="primary" icon="plus">
                Nuevo traslado
            </x-mus.btn>
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($locales->count() > 1)
            <div class="mtras__filtros">
                <a href="{{ route('traslados.index') }}" class="{{ $tienda === '' ? 'is-on' : '' }}">Todos</a>
                @foreach ($locales as $l)
                    <a href="{{ route('traslados.index', ['tienda' => $l->id]) }}"
                       class="{{ (string) $tienda === (string) $l->id ? 'is-on' : '' }}">{{ $l->nombre }}</a>
                @endforeach
            </div>
        @endif

        @if ($traslados->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Recorrido</th>
                            <th>Valor</th>
                            <th>Quién</th>
                            <th>Cuándo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($traslados as $t)
                            <tr data-row>
                                <td><span class="mt__id">{{ $t->numero }}</span></td>
                                <td>
                                    <b>{{ $t->product->name ?? '—' }}</b>
                                    @if ($t->product?->sku)
                                        <em class="mtras__sku">{{ $t->product->sku }}</em>
                                    @endif
                                </td>
                                <td>{{ rtrim(rtrim(number_format($t->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                                <td>
                                    <span class="mtras__ruta">
                                        {{ $t->desde->nombre ?? '—' }}
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M5 12h14M12 5l7 7-7 7"/>
                                        </svg>
                                        {{ $t->hacia->nombre ?? '—' }}
                                    </span>
                                </td>
                                <td>{{ Formato::moneda($t->valor()) }}</td>
                                <td><span style="color:var(--muted-2)">{{ $t->user->name ?? '—' }}</span></td>
                                <td><span style="color:var(--muted-2)">{{ $t->created_at?->locale('es')->isoFormat('D MMM YYYY, h:mm a') }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$traslados" label="traslados" />
        @else
            <x-mus.empty icon="back" title="Todavía no se ha movido nada"
                         text="Un traslado deja constancia de la mercancía que sale de un local y llega a otro. Sin él, la salida y la entrada quedan sueltas y nadie puede amarrarlas.">
                @can('inventario.ajustar')
                    @if ($locales->count() > 1)
                        <x-slot name="action">
                            <x-mus.btn href="{{ route('traslados.create') }}" variant="primary" icon="plus">
                                Registrar el primero
                            </x-mus.btn>
                        </x-slot>
                    @endif
                @endcan
            </x-mus.empty>
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .mtras__filtros{ display:flex; gap:5px; flex-wrap:wrap; padding:11px 15px;
                         border-bottom:1px solid var(--line-2); background:var(--paper); }
        .mtras__filtros a{ padding:5px 11px; border-radius:99px; font-size:12.2px;
                           color:var(--muted); text-decoration:none; transition:background .16s, color .16s; }
        .mtras__filtros a:hover{ background:var(--line-2); color:var(--ink-2); }
        .mtras__filtros a.is-on{ background:var(--a-500); color:#fff; }

        .mtras__sku{ display:block; font-style:normal; font-size:11.2px; color:var(--muted); }
        .mtras__ruta{ display:inline-flex; align-items:center; gap:7px; white-space:nowrap; }
        .mtras__ruta svg{ width:13px; height:13px; color:var(--a-500); flex:none; }
    </style>
    @endpush
</x-mus.page>
