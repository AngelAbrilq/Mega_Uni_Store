<x-mus.page title="{{ $category->name }}" subtitle="Categoría del catálogo" icon="layers"
            :crumbs="['Categorías' => route('categories.index'), 'Detalle' => null]">

    <x-slot name="actions">
        @can('categorias.editar')<x-mus.btn href="{{ route('categories.edit', $category) }}" icon="pencil">Editar</x-mus.btn>@endcan
        @can('categorias.eliminar')<x-mus.del :action="route('categories.destroy', $category)" what="la categoría" />@endcan
    </x-slot>

    <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start"
         class="mshow-grid">

        <x-mus.panel title="Información" sub="Datos registrados">
            <dl class="mdl">
                <div>
                    <dt>Nombre</dt>
                    <dd>{{ $category->name }}</dd>
                </div>
                <div>
                    <dt>Categoría superior</dt>
                    <dd>{{ optional($category->parent)->name ?? 'Ninguna' }}</dd>
                </div>
                <div>
                    <dt>Descripción</dt>
                    <dd>{{ $category->description ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>@if ($category->is_active)<x-mus.badge tone="ok" :dot="true">Activa</x-mus.badge>@else<x-mus.badge tone="off" :dot="true">Inactiva</x-mus.badge>@endif</dd>
                </div>
            </dl>
        </x-mus.panel>

        <x-mus.panel title="Registro" sub="Trazabilidad">
            <dl class="mdl">
                <div>
                    <dt>Identificador</dt>
                    <dd>#{{ $category->id }}</dd>
                </div>
                <div>
                    <dt>Creado</dt>
                    <dd>{{ $category->created_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Última edición</dt>
                    <dd>{{ $category->updated_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm') ?? '—' }}</dd>
                </div>
            </dl>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('categories.index') }}" icon="back" :block="true">
                    Volver al listado
                </x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </div>

    @push('styles')
        <style>
            @media (max-width:900px){ .mshow-grid{ grid-template-columns:minmax(0,1fr) !important; } }
        </style>
    @endpush
</x-mus.page>
