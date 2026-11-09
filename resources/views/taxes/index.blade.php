<x-mus.page title="Impuestos" subtitle="Tarifas que se aplican a las ventas" icon="percent">
    <x-slot name="actions">
        @can('impuestos.crear')
            <x-mus.btn href="{{ route('taxes.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo impuesto" data-modal-ancho="620">
                Nuevo
            </x-mus.btn>
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($taxes->total())
            <x-mus.toolbar :count="$taxes->total()" label="impuestos" :action="route('taxes.index')" :q="$q ?? ''" />

            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th class="num">Tarifa</th>
                            <th>Tipo</th>
                            <th class="num">Productos</th>
                            <th>Estado</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($taxes as $tax)
                            <tr data-row>
                                <td><span class="mt__id">{{ $tax->id }}</span></td>
                                <td><b>{{ $tax->name }}</b>
                                    @if ($tax->description)
                                        <span class="sub">{{ \Illuminate\Support\Str::limit($tax->description, 60) }}</span>
                                    @endif</td>
                                <td class="num"><b>{{ rtrim(rtrim(number_format($tax->rate, 2, ',', '.'), '0'), ',') }}{{ $tax->type === 'percentage' ? '%' : '' }}</b></td>
                                <td>{{ $tax->type === 'percentage' ? 'Porcentaje' : 'Monto fijo' }}</td>
                                <td class="num">
                                    <x-mus.badge :tone="$tax->products_count ? 'info' : 'off'">{{ $tax->products_count }}</x-mus.badge>
                                </td>
                                <td>@if ($tax->is_active)
                                        <x-mus.badge tone="ok" :dot="true">Activo</x-mus.badge>
                                    @else
                                        <x-mus.badge tone="off" :dot="true">Inactivo</x-mus.badge>
                                    @endif</td>
                                <td class="act">
                                    <span class="mt__acts">
                                        @can('impuestos.ver')<x-mus.btn href="{{ route('taxes.show', $tax) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                            <x-mus.icon name="eye" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('impuestos.editar')<x-mus.btn href="{{ route('taxes.edit', $tax) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                            <x-mus.icon name="pencil" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('impuestos.eliminar')<x-mus.del :action="route('taxes.destroy', $tax)"
                                                   what="el impuesto" />@endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$taxes" label="impuestos" />
        @else
            <x-mus.empty icon="percent" title="Sin impuestos configurados"
                         text="Registra aquí el IVA u otras tarifas para que se apliquen automáticamente.">
                @can('impuestos.crear')
                    <x-slot name="action">
                        <x-mus.btn href="{{ route('taxes.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo impuesto" data-modal-ancho="620">
                            Nuevo impuesto
                        </x-mus.btn>
                    </x-slot>
                @endcan
            </x-mus.empty>
        @endif
    </x-mus.panel>
</x-mus.page>
