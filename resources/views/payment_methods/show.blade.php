@php use App\Support\Formato; @endphp

<x-mus.page title="{{ $paymentMethod->name }}" subtitle="Forma de cobro" icon="card"
            :crumbs="['Medios de pago' => route('payment_methods.index'), 'Detalle' => null]">

    <x-slot name="actions">
        @can('medios_pago.editar')<x-mus.btn href="{{ route('payment_methods.edit', $paymentMethod) }}" icon="pencil">Editar</x-mus.btn>@endcan
        @can('medios_pago.eliminar')<x-mus.del :action="route('payment_methods.destroy', $paymentMethod)" what="el medio de pago" />@endcan
    </x-slot>

    <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start"
         class="mshow-grid">

        <x-mus.panel title="Información" sub="Datos registrados">
            <dl class="mdl">
                <div>
                    <dt>Nombre</dt>
                    <dd>{{ $paymentMethod->name }}</dd>
                </div>
                <div>
                    <dt>Descripción</dt>
                    <dd>{{ $paymentMethod->description ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>@if ($paymentMethod->is_active)<x-mus.badge tone="ok" :dot="true">Activo</x-mus.badge>@else<x-mus.badge tone="off" :dot="true">Inactivo</x-mus.badge>@endif</dd>
                </div>
            </dl>
        </x-mus.panel>

        <x-mus.panel title="Registro" sub="Trazabilidad">
            <dl class="mdl">
                <div>
                    <dt>Identificador</dt>
                    <dd>#{{ $paymentMethod->id }}</dd>
                </div>
                <div>
                    <dt>Creado</dt>
                    <dd>{{ Formato::enPalabras($paymentMethod->created_at, 'D MMM YYYY, HH:mm', '—') }}</dd>
                </div>
                <div>
                    <dt>Última edición</dt>
                    <dd>{{ Formato::enPalabras($paymentMethod->updated_at, 'D MMM YYYY, HH:mm', '—') }}</dd>
                </div>
            </dl>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('payment_methods.index') }}" icon="back" :block="true">
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
