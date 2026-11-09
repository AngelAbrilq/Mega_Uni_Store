<x-mus.page title="Abrir un local" subtitle="Una sede más para este negocio" icon="branch"
            :crumbs="['Locales' => route('tiendas.index'), 'Nuevo' => null]">

    <form method="POST" action="{{ route('tiendas.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Datos del local" sub="Cómo se llama y dónde queda">
            @include('tiendas.form', ['item' => null])

            <div class="mf" style="margin-top:18px">
                <x-mus.toggle name="activa" label="Abierto desde ya"
                              hint="Un local cerrado existe, pero no se puede vender en él" :checked="true" />

                <x-mus.toggle name="copiar_catalogo" label="Poner a la venta el catálogo del negocio"
                              hint="Deja los mismos productos disponibles aquí, todos en cero. Sin esto el punto de venta de este local abre en blanco hasta que le llegue un traslado o una compra."
                              :checked="true" />
            </div>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('tiendas.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Abrir local</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
