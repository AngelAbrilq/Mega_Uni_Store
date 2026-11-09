@php use App\Support\Formato; @endphp

<x-mus.page title="{{ $unit->name }}" subtitle="Unidad de medida" icon="ruler"
            :crumbs="['Unidades' => route('units.index'), 'Detalle' => null]">

    <x-slot name="actions">
        @can('unidades.editar')<x-mus.btn href="{{ route('units.edit', $unit) }}" icon="pencil">Editar</x-mus.btn>@endcan
        @can('unidades.eliminar')<x-mus.del :action="route('units.destroy', $unit)" what="la unidad" />@endcan
    </x-slot>

    <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start"
         class="mshow-grid">

        <x-mus.panel title="Información" sub="Datos registrados">
            <dl class="mdl">
                <div>
                    <dt>Nombre</dt>
                    <dd>{{ $unit->name }}</dd>
                </div>
                <div>
                    <dt>Símbolo</dt>
                    <dd><x-mus.badge tone="info">{{ $unit->symbol }}</x-mus.badge></dd>
                </div>
                <div>
                    <dt>Tipo de medida</dt>
                    <dd>{{ $unit->type ? ucfirst($unit->type) : '—' }}</dd>
                </div>
            </dl>
        </x-mus.panel>

        <x-mus.panel title="Registro" sub="Trazabilidad">
            <dl class="mdl">
                <div>
                    <dt>Identificador</dt>
                    <dd>#{{ $unit->id }}</dd>
                </div>
                <div>
                    <dt>Creado</dt>
                    <dd>{{ Formato::enPalabras($unit->created_at, 'D MMM YYYY, HH:mm', '—') }}</dd>
                </div>
                <div>
                    <dt>Última edición</dt>
                    <dd>{{ Formato::enPalabras($unit->updated_at, 'D MMM YYYY, HH:mm', '—') }}</dd>
                </div>
            </dl>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('units.index') }}" icon="back" :block="true">
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
