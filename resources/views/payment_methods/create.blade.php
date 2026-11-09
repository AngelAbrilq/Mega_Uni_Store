<x-mus.page title="Nuevo medio de pago" subtitle="Registra una forma de cobro" icon="card"
            :crumbs="['Medios de pago' => route('payment_methods.index'), 'Nuevo' => null]">

    <form method="POST" action="{{ route('payment_methods.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Datos del medio de pago" sub="Nombre, descripción y estado">
            @include('payment_methods.form', ['item' => null])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('payment_methods.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar medio de pago</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
