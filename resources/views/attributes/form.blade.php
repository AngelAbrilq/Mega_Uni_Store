<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="name" label="Nombre" :value="$item?->name" :required="true"
                     max="80" hint="Por ejemplo: Talla, Color, Material" />

        <x-mus.select name="type" label="Tipo de valor" :required="true" :value="$item?->type"
                      empty="Selecciona un tipo"
                      :options="$tipos ?? \App\Http\Controllers\AttributeController::TIPOS" />

        <x-mus.toggle name="is_active" label="Atributo activo"
                      hint="Los inactivos no se ofrecen al crear variantes"
                      :checked="$item?->is_active ?? true" />
    </div>
</div>
