@php use App\Support\Formato; @endphp

<x-mus.page title="Atributos" subtitle="Variantes que distinguen un producto de otro" icon="tag">
    <x-slot name="actions">
        @can('atributos.crear')
            <x-mus.btn href="{{ route('attributes.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo atributo" data-modal-ancho="620">
                Nuevo
            </x-mus.btn>
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($attributes->total())
            <x-mus.toolbar :count="$attributes->total()" label="atributos" :action="route('attributes.index')" :q="$q ?? ''" />

            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Tipo de valor</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attributes as $attribute)
                            <tr data-row>
                                <td><span class="mt__id">{{ $attribute->id }}</span></td>
                                <td><b>{{ $attribute->name }}</b></td>
                                <td><x-mus.badge tone="info">{{ ucfirst($attribute->type) }}</x-mus.badge></td>
                                <td>@if ($attribute->is_active)
                                        <x-mus.badge tone="ok" :dot="true">Activo</x-mus.badge>
                                    @else
                                        <x-mus.badge tone="off" :dot="true">Inactivo</x-mus.badge>
                                    @endif</td>
                                <td><span style="color:var(--muted-2)">{{ Formato::enPalabras($attribute->created_at, 'D MMM YYYY', '—') }}</span></td>
                                <td class="act">
                                    <span class="mt__acts">
                                        @can('atributos.ver')<x-mus.btn href="{{ route('attributes.show', $attribute) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                            <x-mus.icon name="eye" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('atributos.editar')<x-mus.btn href="{{ route('attributes.edit', $attribute) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                            <x-mus.icon name="pencil" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('atributos.eliminar')<x-mus.del :action="route('attributes.destroy', $attribute)"
                                                   what="el atributo" />@endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$attributes" label="atributos" />
        @else
            <x-mus.empty icon="tag" title="Sin atributos definidos"
                         text="Los atributos permiten que un mismo producto tenga variantes: talla, color, sabor.">
                @can('atributos.crear')
                    <x-slot name="action">
                        <x-mus.btn href="{{ route('attributes.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo atributo" data-modal-ancho="620">
                            Nuevo atributo
                        </x-mus.btn>
                    </x-slot>
                @endcan
            </x-mus.empty>
        @endif
    </x-mus.panel>
</x-mus.page>
