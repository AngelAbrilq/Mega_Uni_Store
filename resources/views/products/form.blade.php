@php
    /**
     * Formulario compartido por crear y editar.
     * $item es null al crear y el producto al editar.
     *
     * Las categorías se muestran como "Papelería › Cuadernos" para que se
     * entienda el nivel sin tener que abrir el árbol.
     */
    $opcCategorias = ($categories ?? collect())
        ->mapWithKeys(fn ($c) => [$c->id => $c->parent ? $c->parent->name . ' › ' . $c->name : $c->name])
        ->all();
    asort($opcCategorias);

    $opcUnidades = ($units ?? collect())
        ->mapWithKeys(fn ($u) => [$u->id => $u->name . ' (' . $u->symbol . ')'])
        ->all();

    $opcImpuestos = ($taxes ?? collect())
        ->mapWithKeys(fn ($t) => [$t->id => $t->label])
        ->all();

    $opcProveedores = ($suppliers ?? collect())
        ->pluck('name', 'id')
        ->all();
@endphp

<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="name" label="Nombre del producto" :value="$item?->name" :required="true"
                     :wide="true" max="200" />

        <x-mus.field name="sku" label="SKU / código interno" :value="$item?->sku" max="50"
                     hint="Tu propio código. Si lo dejas vacío, no pasa nada." />

        <x-mus.field name="barcode" label="Código de barras" :value="$item?->barcode" max="50"
                     hint="EAN o UPC. No puede repetirse." />

        <x-mus.select name="category_id" label="Categoría" :value="$item?->category_id"
                      empty="Sin categoría" :options="$opcCategorias" />

        <x-mus.select name="unit_id" label="Unidad de medida" :value="$item?->unit_id"
                      empty="Sin unidad" :options="$opcUnidades" />

        <x-mus.select name="tax_id" label="Impuesto aplicable" :value="$item?->tax_id"
                      empty="Sin impuesto" :options="$opcImpuestos"
                      hint="Se aplica al momento de vender." />

        <x-mus.select name="supplier_id" label="Proveedor habitual" :value="$item?->supplier_id"
                      empty="Sin proveedor" :options="$opcProveedores" />

        <x-mus.field name="cost" label="Costo" type="number" step="0.01" prefix="$" min="0"
                     :value="$item?->cost ?? 0" :required="true"
                     hint="Lo que te cuesta a ti." />

        <x-mus.field name="price" label="Precio de venta" type="number" step="0.01" prefix="$" min="0"
                     :value="$item?->price ?? 0" :required="true"
                     hint="Lo que paga el cliente." />

        <x-mus.field name="stock" label="Existencias" type="number" step="1" min="0"
                     :value="$item?->stock ?? 0" :required="true"
                     hint="Unidades disponibles hoy." />

        <x-mus.field name="min_stock" label="Stock mínimo" type="number" step="1" min="0"
                     :value="$item?->min_stock ?? 0" :required="true"
                     hint="Cuando baje de aquí, hay que reponer." />

        <x-mus.imagen name="imagen" label="Foto del producto" :actual="$item?->imagen"
                      hint="JPG, PNG o WEBP · máximo 2 MB. Se guarda en el servidor; en la base de datos solo queda la ruta." />

        <x-mus.textarea name="description" label="Descripción" :value="$item?->description" :rows="3" />

        <x-mus.toggle name="is_active" label="Producto activo"
                      hint="Los inactivos no se ofrecen al vender."
                      :checked="$item?->is_active ?? true" />

        <x-mus.toggle name="is_public" label="Visible en la tienda pública"
                      hint="Apagado, el producto existe solo puertas adentro. Nada sale a internet por accidente."
                      :checked="$item?->is_public ?? false" />
    </div>
</div>
