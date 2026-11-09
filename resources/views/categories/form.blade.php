<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="name" label="Nombre" :value="$item?->name" :required="true" max="100" />
        
        <x-mus.select name="parent_id" label="Categoría superior" :value="$item?->parent_id"
                      empty="Ninguna — es una categoría principal"
                      hint="Úsalo para crear subcategorías"
                      :options="($categories ?? collect())->pluck('name', 'id')->all()" />
        
        <x-mus.textarea name="description" label="Descripción" :value="$item?->description"
                        hint="Opcional. Ayuda a saber qué entra en esta categoría." />
        
        <x-mus.imagen name="imagen" label="Imagen de la categoría" :actual="$item?->imagen"
                      hint="Opcional. JPG, PNG o WEBP · máximo 2 MB." />
        
        <x-mus.toggle name="is_active" label="Categoría activa"
                      hint="Si la desactivas deja de ofrecerse al clasificar productos"
                      :checked="$item?->is_active ?? true" />
    </div>
</div>
