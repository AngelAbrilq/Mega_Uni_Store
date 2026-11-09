<x-mus.page title="Editar impuesto" subtitle="{{ $tax->name }}" icon="percent"
            :crumbs="['Impuestos' => route('taxes.index'), 'Editar' => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('taxes.show', $tax) }}" icon="eye">Ver detalle</x-mus.btn>
    </x-slot>

    <form method="POST" action="{{ route('taxes.update', $tax) }}" novalidate>
        @csrf
        @method('PUT')

        <x-mus.panel title="Datos del impuesto" sub="Tarifa, tipo de cálculo y estado">
            @include('taxes.form', ['item' => $tax])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('taxes.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
