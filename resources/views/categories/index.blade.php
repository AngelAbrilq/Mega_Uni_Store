<x-mus.page title="Categorías" subtitle="Cómo se clasifica tu catálogo" icon="layers">
    <x-slot name="actions">
        @can('categorias.crear')
            <x-mus.btn href="{{ route('categories.create') }}" variant="primary" icon="plus"
                       data-modal="Nueva categoría" data-modal-ancho="700">
                Nuevo
            </x-mus.btn>
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($categories->total())
            <x-mus.toolbar :count="$categories->total()" label="categorías" :action="route('categories.index')" :q="$q ?? ''" />

            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Categoría</th>
                            <th>Depende de</th>
                            <th class="num">Productos</th>
                            <th>Estado</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr data-row>
                                <td><span class="mt__id">{{ $category->id }}</span></td>
                                <td><div class="mt__ent">
                                        <x-mus.avatar :src="$category->imagen" :letras="$category->iniciales"
                                                      :color="$category->color_avatar" :size="34" />
                                        <span>
                                            <b>{{ $category->name }}</b>
                                            @if ($category->description)
                                                <span class="sub">{{ \Illuminate\Support\Str::limit($category->description, 54) }}</span>
                                            @endif
                                        </span>
                                    </div></td>
                                <td>{{ optional($category->parent)->name ?? '—' }}</td>
                                <td class="num">
                                    <x-mus.badge :tone="$category->products_count ? 'info' : 'off'">{{ $category->products_count }}</x-mus.badge>
                                </td>
                                <td>@if ($category->is_active)
                                        <x-mus.badge tone="ok" :dot="true">Activa</x-mus.badge>
                                    @else
                                        <x-mus.badge tone="off" :dot="true">Inactiva</x-mus.badge>
                                    @endif</td>
                                <td class="act">
                                    <span class="mt__acts">
                                        @can('categorias.ver')<x-mus.btn href="{{ route('categories.show', $category) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                            <x-mus.icon name="eye" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('categorias.editar')<x-mus.btn href="{{ route('categories.edit', $category) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                            <x-mus.icon name="pencil" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('categorias.eliminar')<x-mus.del :action="route('categories.destroy', $category)"
                                                   what="la categoría" />@endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$categories" label="categorías" />
        @else
            <x-mus.empty icon="layers" title="Sin categorías todavía"
                         text="Agrupar el catálogo en categorías hace que encontrar productos sea mucho más rápido.">
                @can('categorias.crear')
                    <x-slot name="action">
                        <x-mus.btn href="{{ route('categories.create') }}" variant="primary" icon="plus"
                       data-modal="Nueva categoría" data-modal-ancho="700">
                            Nueva categoría
                        </x-mus.btn>
                    </x-slot>
                @endcan
            </x-mus.empty>
        @endif
    </x-mus.panel>
</x-mus.page>
