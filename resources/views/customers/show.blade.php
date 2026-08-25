<x-mus.page title="{{ trim($customer->first_name . ' ' . $customer->last_name) }}" subtitle="Ficha de cliente" icon="users"
            :crumbs="['Clientes' => route('customers.index'), 'Detalle' => null]">

    <x-slot name="actions">
        @can('clientes.editar')<x-mus.btn href="{{ route('customers.edit', $customer) }}" icon="pencil">Editar</x-mus.btn>@endcan
        @can('clientes.eliminar')<x-mus.del :action="route('customers.destroy', $customer)" what="el cliente" />@endcan
    </x-slot>

    <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start"
         class="mshow-grid">

        <x-mus.panel title="Información" sub="Datos registrados">
            <div class="mficha">
                <x-mus.avatar :src="$customer->imagen" :letras="$customer->iniciales"
                              :color="$customer->color_avatar" :size="64" :round="true" />
                <div class="mficha__t">
                    <b>{{ $customer->full_name }}</b>
                    <span>{{ $customer->email ?: ($customer->phone ?: 'Sin datos de contacto') }}</span>
                </div>
            </div>

            <dl class="mdl">
                <div>
                    <dt>Nombres</dt>
                    <dd>{{ $customer->first_name }}</dd>
                </div>
                <div>
                    <dt>Apellidos</dt>
                    <dd>{{ $customer->last_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Correo</dt>
                    <dd>{{ $customer->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Teléfono</dt>
                    <dd>{{ $customer->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Documento</dt>
                    <dd>{{ trim(($customer->document_type ?? '') . ' ' . ($customer->document_number ?? '')) ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Dirección</dt>
                    <dd>{{ $customer->address ?: '—' }}</dd>
                </div>
            </dl>
        </x-mus.panel>

        <x-mus.panel title="Registro" sub="Trazabilidad">
            <dl class="mdl">
                <div>
                    <dt>Identificador</dt>
                    <dd>#{{ $customer->id }}</dd>
                </div>
                <div>
                    <dt>Creado</dt>
                    <dd>{{ $customer->created_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Última edición</dt>
                    <dd>{{ $customer->updated_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm') ?? '—' }}</dd>
                </div>
            </dl>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('customers.index') }}" icon="back" :block="true">
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
