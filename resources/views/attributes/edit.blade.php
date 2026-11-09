<x-mus.page title="Editar atributo" subtitle="{{ $attribute->name }}" icon="tag"
            :crumbs="['Atributos' => route('attributes.index'), 'Editar' => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('attributes.show', $attribute) }}" icon="eye">Ver detalle</x-mus.btn>
    </x-slot>

    <form method="POST" action="{{ route('attributes.update', $attribute) }}" novalidate>
        @csrf
        @method('PUT')

        <x-mus.panel title="Datos del atributo" sub="Nombre y tipo de valor">
            @include('attributes.form', ['item' => $attribute])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('attributes.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
