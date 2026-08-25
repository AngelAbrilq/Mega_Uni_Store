@php use App\Support\Formato; @endphp

<x-mus.page title="Medios de pago" subtitle="Formas en que tus clientes pueden pagar" icon="card">
    <x-slot name="actions">
        @can('medios_pago.crear')
            <x-mus.btn href="{{ route('payment_methods.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo medio de pago" data-modal-ancho="620">
                Nuevo
            </x-mus.btn>
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($paymentMethods->total())
            <x-mus.toolbar :count="$paymentMethods->total()" label="medios de pago" :action="route('payment_methods.index')" :q="$q ?? ''" />

            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($paymentMethods as $paymentMethod)
                            <tr data-row>
                                <td><span class="mt__id">{{ $paymentMethod->id }}</span></td>
                                <td><b>{{ $paymentMethod->name }}</b>
                                    @if ($paymentMethod->description)
                                        <span class="sub">{{ \Illuminate\Support\Str::limit($paymentMethod->description, 70) }}</span>
                                    @endif</td>
                                <td>@if ($paymentMethod->is_active)
                                        <x-mus.badge tone="ok" :dot="true">Activo</x-mus.badge>
                                    @else
                                        <x-mus.badge tone="off" :dot="true">Inactivo</x-mus.badge>
                                    @endif</td>
                                <td><span style="color:var(--muted-2)">{{ Formato::enPalabras($paymentMethod->created_at, 'D MMM YYYY', '—') }}</span></td>
                                <td class="act">
                                    <span class="mt__acts">
                                        @can('medios_pago.ver')<x-mus.btn href="{{ route('payment_methods.show', $paymentMethod) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                            <x-mus.icon name="eye" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('medios_pago.editar')<x-mus.btn href="{{ route('payment_methods.edit', $paymentMethod) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                            <x-mus.icon name="pencil" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('medios_pago.eliminar')<x-mus.del :action="route('payment_methods.destroy', $paymentMethod)"
                                                   what="el medio de pago" />@endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$paymentMethods" label="medios de pago" />
        @else
            <x-mus.empty icon="card" title="Sin medios de pago"
                         text="Efectivo, tarjeta, transferencia… registra las formas de cobro que aceptas.">
                @can('medios_pago.crear')
                    <x-slot name="action">
                        <x-mus.btn href="{{ route('payment_methods.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo medio de pago" data-modal-ancho="620">
                            Nuevo medio de pago
                        </x-mus.btn>
                    </x-slot>
                @endcan
            </x-mus.empty>
        @endif
    </x-mus.panel>
</x-mus.page>
