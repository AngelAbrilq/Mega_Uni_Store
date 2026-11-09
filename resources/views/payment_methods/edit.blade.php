<x-mus.page title="Editar medio de pago" subtitle="{{ $paymentMethod->name }}" icon="card"
            :crumbs="['Medios de pago' => route('payment_methods.index'), 'Editar' => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('payment_methods.show', $paymentMethod) }}" icon="eye">Ver detalle</x-mus.btn>
    </x-slot>

    <form method="POST" action="{{ route('payment_methods.update', $paymentMethod) }}" novalidate>
        @csrf
        @method('PUT')

        <x-mus.panel title="Datos del medio de pago" sub="Nombre, descripción y estado">
            @include('payment_methods.form', ['item' => $paymentMethod])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('payment_methods.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
