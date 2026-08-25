<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="name" label="Nombre" :value="$item?->name" :required="true"
                     max="100" hint="Por ejemplo: Efectivo, Tarjeta débito, Nequi" />
        
        <x-mus.toggle name="is_active" label="Disponible"
                      hint="Aparece como opción al cobrar" :wide="false"
                      :checked="$item?->is_active ?? true" />
        
        <x-mus.textarea name="description" label="Descripción" :value="$item?->description"
                        hint="Opcional. Condiciones o notas para el cajero." />
    </div>
</div>
