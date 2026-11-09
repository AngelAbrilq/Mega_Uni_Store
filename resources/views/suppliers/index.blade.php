<x-mus.page title="Proveedores" subtitle="A quién le compras lo que vendes" icon="truck">
    <x-slot name="actions">
        @can('proveedores.crear')
            <x-mus.btn href="{{ route('suppliers.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo proveedor" data-modal-ancho="760">
                Nuevo
            </x-mus.btn>
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($suppliers->total())
            <x-mus.toolbar :count="$suppliers->total()" label="proveedores" :action="route('suppliers.index')" :q="$q ?? ''" />

            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Proveedor</th>
                            <th>NIT / RUC</th>
                            <th class="num">Productos</th>
                            <th>Contacto</th>
                            <th>Estado</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($suppliers as $supplier)
                            <tr data-row>
                                <td><span class="mt__id">{{ $supplier->id }}</span></td>
                                <td><div class="mt__ent">
                                        <x-mus.avatar :src="$supplier->imagen" :letras="$supplier->iniciales"
                                                      :color="$supplier->color_avatar" :size="34" />
                                        <span>
                                            <b>{{ $supplier->name }}</b>
                                            @if ($supplier->contact_name)
                                                <span class="sub">Contacto: {{ $supplier->contact_name }}</span>
                                            @endif
                                        </span>
                                    </div></td>
                                <td>{{ $supplier->tax_id ?: '—' }}</td>
                                <td class="num">
                                    <x-mus.badge :tone="$supplier->products_count ? 'info' : 'off'">{{ $supplier->products_count }}</x-mus.badge>
                                </td>
                                <td>@if ($supplier->email){{ $supplier->email }}@endif
                                    @if ($supplier->phone)<span class="sub">{{ $supplier->phone }}</span>@endif
                                    @if (! $supplier->email && ! $supplier->phone)—@endif</td>
                                <td>@if ($supplier->is_active)
                                        <x-mus.badge tone="ok" :dot="true">Activo</x-mus.badge>
                                    @else
                                        <x-mus.badge tone="off" :dot="true">Inactivo</x-mus.badge>
                                    @endif</td>
                                <td class="act">
                                    <span class="mt__acts">
                                        @can('proveedores.ver')<x-mus.btn href="{{ route('suppliers.show', $supplier) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                            <x-mus.icon name="eye" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('proveedores.editar')<x-mus.btn href="{{ route('suppliers.edit', $supplier) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                            <x-mus.icon name="pencil" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('proveedores.eliminar')<x-mus.del :action="route('suppliers.destroy', $supplier)"
                                                   what="el proveedor" />@endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$suppliers" label="proveedores" />
        @else
            <x-mus.empty icon="truck" title="Sin proveedores registrados"
                         text="Tener a tus proveedores a la mano acelera las reposiciones de inventario.">
                @can('proveedores.crear')
                    <x-slot name="action">
                        <x-mus.btn href="{{ route('suppliers.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo proveedor" data-modal-ancho="760">
                            Nuevo proveedor
                        </x-mus.btn>
                    </x-slot>
                @endcan
            </x-mus.empty>
        @endif
    </x-mus.panel>
</x-mus.page>
