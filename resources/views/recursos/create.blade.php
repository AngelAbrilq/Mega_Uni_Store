<x-mus.page title="Agregar a la agenda" subtitle="Una persona, una silla, un espacio" icon="users"
            :crumbs="['Quién atiende' => route('recursos.index'), 'Nuevo' => null]">
    <form method="POST" action="{{ route('recursos.store') }}" novalidate>
        @csrf
        <x-mus.panel title="Datos" sub="Cómo se llama y cuándo trabaja">
            @include('recursos.form', ['item' => null])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('recursos.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
