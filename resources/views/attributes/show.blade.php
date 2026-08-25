@php use App\Support\Formato; @endphp

<x-mus.page title="{{ $attribute->name }}" subtitle="Atributo de producto" icon="tag"
            :crumbs="['Atributos' => route('attributes.index'), 'Detalle' => null]">

    <x-slot name="actions">
        @can('atributos.editar')<x-mus.btn href="{{ route('attributes.edit', $attribute) }}" icon="pencil">Editar</x-mus.btn>@endcan
        @can('atributos.eliminar')<x-mus.del :action="route('attributes.destroy', $attribute)" what="el atributo" />@endcan
    </x-slot>

    <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start"
         class="mshow-grid">

        <x-mus.panel title="Información" sub="Datos registrados">
            <dl class="mdl">
                <div>
                    <dt>Nombre</dt>
                    <dd>{{ $attribute->name }}</dd>
                </div>
                <div>
                    <dt>Tipo de valor</dt>
                    <dd>{{ ucfirst($attribute->type) }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>@if ($attribute->is_active)<x-mus.badge tone="ok" :dot="true">Activo</x-mus.badge>@else<x-mus.badge tone="off" :dot="true">Inactivo</x-mus.badge>@endif</dd>
                </div>
            </dl>
        </x-mus.panel>

        <x-mus.panel title="Registro" sub="Trazabilidad">
            <dl class="mdl">
                <div>
                    <dt>Identificador</dt>
                    <dd>#{{ $attribute->id }}</dd>
                </div>
                <div>
                    <dt>Creado</dt>
                    <dd>{{ Formato::enPalabras($attribute->created_at, 'D MMM YYYY, HH:mm', '—') }}</dd>
                </div>
                <div>
                    <dt>Última edición</dt>
                    <dd>{{ Formato::enPalabras($attribute->updated_at, 'D MMM YYYY, HH:mm', '—') }}</dd>
                </div>
            </dl>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('attributes.index') }}" icon="back" :block="true">
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
