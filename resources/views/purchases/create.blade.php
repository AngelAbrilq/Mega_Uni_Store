<x-mus.page title="Nueva compra" subtitle="Registra el pedido al proveedor" icon="truck"
            :crumbs="['Compras' => route('purchases.index'), 'Nueva' => null]">

    <form method="POST" action="{{ route('purchases.store') }}" novalidate>
        @csrf
        <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1fr)">
            @include('purchases._form', ['purchase' => null])
        </div>
    </form>
</x-mus.page>
