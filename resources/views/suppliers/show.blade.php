<x-mus.page title="{{ $supplier->name }}" subtitle="Ficha de proveedor" icon="truck"
            :crumbs="['Proveedores' => route('suppliers.index'), 'Detalle' => null]">

    <x-slot name="actions">
        @can('proveedores.editar')<x-mus.btn href="{{ route('suppliers.edit', $supplier) }}" icon="pencil">Editar</x-mus.btn>@endcan
        @can('proveedores.eliminar')<x-mus.del :action="route('suppliers.destroy', $supplier)" what="el proveedor" />@endcan
    </x-slot>

    <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start"
         class="mshow-grid">

        <x-mus.panel title="Información" sub="Datos registrados">
            <div class="mficha">
                <x-mus.avatar :src="$supplier->imagen" :letras="$supplier->iniciales"
                              :color="$supplier->color_avatar" :size="64" />
                <div class="mficha__t">
                    <b>{{ $supplier->name }}</b>
                    <span>{{ $supplier->tax_id ? 'NIT ' . $supplier->tax_id : ($supplier->contact_name ?: 'Sin NIT registrado') }}</span>
                </div>
            </div>

            <dl class="mdl">
                <div>
                    <dt>Razón social</dt>
                    <dd>{{ $supplier->name }}</dd>
                </div>
                <div>
                    <dt>NIT / RUC</dt>
                    <dd>{{ $supplier->tax_id ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Persona de contacto</dt>
                    <dd>{{ $supplier->contact_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Correo</dt>
                    <dd>{{ $supplier->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Teléfono</dt>
                    <dd>{{ $supplier->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Dirección</dt>
                    <dd>{{ $supplier->address ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>@if ($supplier->is_active)<x-mus.badge tone="ok" :dot="true">Activo</x-mus.badge>@else<x-mus.badge tone="off" :dot="true">Inactivo</x-mus.badge>@endif</dd>
                </div>
            </dl>
        </x-mus.panel>

        <x-mus.panel title="Registro" sub="Trazabilidad">
            <dl class="mdl">
                <div>
                    <dt>Identificador</dt>
                    <dd>#{{ $supplier->id }}</dd>
                </div>
                <div>
                    <dt>Creado</dt>
                    <dd>{{ $supplier->created_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Última edición</dt>
                    <dd>{{ $supplier->updated_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm') ?? '—' }}</dd>
                </div>
            </dl>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('suppliers.index') }}" icon="back" :block="true">
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
