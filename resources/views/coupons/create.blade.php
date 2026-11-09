<x-mus.page title="Nuevo cupón" subtitle="Descuento por código para el POS" icon="tag"
            :crumbs="['Cupones' => route('coupons.index'), 'Nuevo' => null]">
    <form method="POST" action="{{ route('coupons.store') }}" novalidate>
        @csrf
        <x-mus.panel title="Datos del cupón" sub="Código, descuento, condiciones y vigencia">
            @include('coupons.form', ['item' => null])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('coupons.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cupón</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
