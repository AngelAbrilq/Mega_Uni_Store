<x-mus.page title="Editar cupón" subtitle="{{ $cupon->code }}" icon="tag"
            :crumbs="['Cupones' => route('coupons.index'), 'Editar' => null]">
    <form method="POST" action="{{ route('coupons.update', $cupon) }}" novalidate>
        @csrf
        @method('PUT')
        <x-mus.panel title="Datos del cupón" sub="Las ventas que ya lo usaron conservan el descuento que recibieron">
            @include('coupons.form', ['item' => $cupon])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('coupons.show', $cupon) }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
