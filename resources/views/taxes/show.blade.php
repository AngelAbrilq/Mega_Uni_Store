<x-mus.page title="{{ $tax->name }}" subtitle="Tarifa aplicable" icon="percent"
            :crumbs="['Impuestos' => route('taxes.index'), 'Detalle' => null]">

    <x-slot name="actions">
        @can('impuestos.editar')<x-mus.btn href="{{ route('taxes.edit', $tax) }}" icon="pencil">Editar</x-mus.btn>@endcan
        @can('impuestos.eliminar')<x-mus.del :action="route('taxes.destroy', $tax)" what="el impuesto" />@endcan
    </x-slot>

    <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start"
         class="mshow-grid">

        <x-mus.panel title="Información" sub="Datos registrados">
            <dl class="mdl">
                <div>
                    <dt>Nombre</dt>
                    <dd>{{ $tax->name }}</dd>
                </div>
                <div>
                    <dt>Tarifa</dt>
                    <dd>{{ rtrim(rtrim(number_format($tax->rate, 2, ',', '.'), '0'), ',') }}{{ $tax->type === 'percentage' ? '%' : '' }}</dd>
                </div>
                <div>
                    <dt>Tipo de cálculo</dt>
                    <dd>{{ $tax->type === 'percentage' ? 'Porcentaje sobre el precio' : 'Monto fijo' }}</dd>
                </div>
                <div>
                    <dt>Descripción</dt>
                    <dd>{{ $tax->description ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>@if ($tax->is_active)<x-mus.badge tone="ok" :dot="true">Activo</x-mus.badge>@else<x-mus.badge tone="off" :dot="true">Inactivo</x-mus.badge>@endif</dd>
                </div>
            </dl>
        </x-mus.panel>

        <x-mus.panel title="Registro" sub="Trazabilidad">
            <dl class="mdl">
                <div>
                    <dt>Identificador</dt>
                    <dd>#{{ $tax->id }}</dd>
                </div>
                <div>
                    <dt>Creado</dt>
                    <dd>{{ $tax->created_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Última edición</dt>
                    <dd>{{ $tax->updated_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm') ?? '—' }}</dd>
                </div>
            </dl>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('taxes.index') }}" icon="back" :block="true">
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
