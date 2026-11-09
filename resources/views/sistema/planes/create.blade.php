<x-mus.page title="Nuevo plan" subtitle="Lo que se le ofrece a un negocio: precio, topes y funciones" icon="card"
            :crumbs="['Todos los negocios' => route('sistema.index'), 'Planes' => route('sistema.planes.index'), 'Nuevo' => null]">
    <form method="POST" action="{{ route('sistema.planes.store') }}" novalidate>
        @csrf
        <x-mus.panel title="Datos del plan" sub="Precio, límites, funciones y publicación">
            @include('sistema.planes.form', ['plan' => null])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('sistema.planes.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Crear plan</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
