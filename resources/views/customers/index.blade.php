<x-mus.page title="Clientes" subtitle="Quiénes compran en tu tienda" icon="users">
    <x-slot name="actions">
        @can('clientes.crear')
            <x-mus.btn href="{{ route('customers.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo cliente" data-modal-ancho="760">
                Nuevo
            </x-mus.btn>
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($customers->total())
            <x-mus.toolbar :count="$customers->total()" label="clientes" :action="route('customers.index')" :q="$q ?? ''" />

            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th>Documento</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr data-row>
                                <td><span class="mt__id">{{ $customer->id }}</span></td>
                                <td><div class="mt__ent">
                                        <x-mus.avatar :src="$customer->imagen" :letras="$customer->iniciales"
                                                      :color="$customer->color_avatar" :size="34" :round="true" />
                                        <span>
                                            <b>{{ trim($customer->first_name . ' ' . $customer->last_name) }}</b>
                                            @if ($customer->address)
                                                <span class="sub">{{ \Illuminate\Support\Str::limit($customer->address, 46) }}</span>
                                            @endif
                                        </span>
                                    </div></td>
                                <td>@if ($customer->email)
                                        {{ $customer->email }}
                                    @endif
                                    @if ($customer->phone)
                                        <span class="sub">{{ $customer->phone }}</span>
                                    @endif
                                    @if (! $customer->email && ! $customer->phone)—@endif</td>
                                <td>{{ trim(($customer->document_type ?? '') . ' ' . ($customer->document_number ?? '')) ?: '—' }}</td>
                                <td class="act">
                                    <span class="mt__acts">
                                        @can('clientes.ver')<x-mus.btn href="{{ route('customers.show', $customer) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                            <x-mus.icon name="eye" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('clientes.editar')<x-mus.btn href="{{ route('customers.edit', $customer) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                            <x-mus.icon name="pencil" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('clientes.eliminar')<x-mus.del :action="route('customers.destroy', $customer)"
                                                   what="el cliente" />@endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$customers" label="clientes" />
        @else
            <x-mus.empty icon="users" title="Sin clientes registrados"
                         text="Registrar a tus compradores te permite darles seguimiento y agilizar sus compras.">
                @can('clientes.crear')
                    <x-slot name="action">
                        <x-mus.btn href="{{ route('customers.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo cliente" data-modal-ancho="760">
                            Nuevo cliente
                        </x-mus.btn>
                    </x-slot>
                @endcan
            </x-mus.empty>
        @endif
    </x-mus.panel>
</x-mus.page>
