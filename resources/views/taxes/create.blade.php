<x-mus.page title="Nuevo impuesto" subtitle="Define la tarifa y cómo se calcula" icon="percent"
            :crumbs="['Impuestos' => route('taxes.index'), 'Nuevo' => null]">

    <form method="POST" action="{{ route('taxes.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Datos del impuesto" sub="Tarifa, tipo de cálculo y estado">
            @include('taxes.form', ['item' => null])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('taxes.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar impuesto</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
