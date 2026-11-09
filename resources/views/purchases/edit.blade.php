<x-mus.page title="Editar compra {{ $purchase->number }}"
            subtitle="Solo se pueden modificar las compras en borrador" icon="truck"
            :crumbs="['Compras' => route('purchases.index'), 'Editar' => null]">

    <form method="POST" action="{{ route('purchases.update', $purchase) }}" novalidate>
        @csrf
        @method('PUT')
        <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1fr)">
            @include('purchases._form')
        </div>
    </form>
</x-mus.page>
