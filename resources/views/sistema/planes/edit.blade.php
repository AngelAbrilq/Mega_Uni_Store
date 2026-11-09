<x-mus.page title="Editar {{ $plan->nombre }}" subtitle="Cambiar el catálogo no cambia lo que ya paga cada cliente" icon="card"
            :crumbs="['Todos los negocios' => route('sistema.index'), 'Planes' => route('sistema.planes.index'), $plan->nombre => null]">
    <form method="POST" action="{{ route('sistema.planes.update', $plan) }}" novalidate>
        @csrf @method('PUT')
        <x-mus.panel title="Datos del plan" sub="Precio, límites, funciones y publicación">
            @include('sistema.planes.form', ['plan' => $plan, 'suscritos' => $suscritos])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('sistema.planes.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
