@php use App\Support\Formato; @endphp

<x-mus.page title="Unidades" subtitle="Medidas con las que vendes tus productos" icon="ruler">
    <x-slot name="actions">
        @can('unidades.crear')
            <x-mus.btn href="{{ route('units.create') }}" variant="primary" icon="plus"
                       data-modal="Nueva unidad" data-modal-ancho="620">
                Nuevo
            </x-mus.btn>
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($units->total())
            <x-mus.toolbar :count="$units->total()" label="unidades" :action="route('units.index')" :q="$q ?? ''" />

            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Símbolo</th>
                            <th>Tipo</th>
                            <th class="num">Productos</th>
                            <th>Creada</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($units as $unit)
                            <tr data-row>
                                <td><span class="mt__id">{{ $unit->id }}</span></td>
                                <td><b>{{ $unit->name }}</b></td>
                                <td><x-mus.badge tone="info">{{ $unit->symbol }}</x-mus.badge></td>
                                <td>{{ $unit->type ?: '—' }}</td>
                                <td class="num">
                                    <x-mus.badge :tone="$unit->products_count ? 'info' : 'off'">{{ $unit->products_count }}</x-mus.badge>
                                </td>
                                <td><span style="color:var(--muted-2)">{{ Formato::enPalabras($unit->created_at, 'D MMM YYYY', '—') }}</span></td>
                                <td class="act">
                                    <span class="mt__acts">
                                        @can('unidades.ver')<x-mus.btn href="{{ route('units.show', $unit) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                            <x-mus.icon name="eye" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('unidades.editar')<x-mus.btn href="{{ route('units.edit', $unit) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                            <x-mus.icon name="pencil" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('unidades.eliminar')<x-mus.del :action="route('units.destroy', $unit)"
                                                   what="la unidad" />@endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$units" label="unidades" />
        @else
            <x-mus.empty icon="ruler" title="Sin unidades de medida"
                         text="Las unidades definen cómo se vende cada producto: por kilo, por caja, por metro.">
                @can('unidades.crear')
                    <x-slot name="action">
                        <x-mus.btn href="{{ route('units.create') }}" variant="primary" icon="plus"
                       data-modal="Nueva unidad" data-modal-ancho="620">
                            Nueva unidad
                        </x-mus.btn>
                    </x-slot>
                @endcan
            </x-mus.empty>
        @endif
    </x-mus.panel>
</x-mus.page>
