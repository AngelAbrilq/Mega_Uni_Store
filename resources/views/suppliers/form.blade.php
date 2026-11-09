<div class="mf">
    <div class="mf__grid">
        <x-mus.imagen name="imagen" label="Logo del proveedor" :actual="$item?->imagen"
                      hint="Opcional · JPG, PNG o WEBP · máximo 2 MB. Se recorta en cuadrado solo." />

        <x-mus.field name="name" label="Razón social" :value="$item?->name" :required="true" max="150" />
        
        <x-mus.field name="tax_id" label="NIT / RUC" :value="$item?->tax_id" max="30" />
        
        <x-mus.field name="contact_name" label="Persona de contacto" :value="$item?->contact_name" max="100" />
        
        <x-mus.field name="phone" label="Teléfono" :value="$item?->phone" max="20" />
        
        <x-mus.field name="email" label="Correo electrónico" type="email" :value="$item?->email" max="150" />
        
        <x-mus.toggle name="is_active" label="Proveedor activo" :wide="false"
                      hint="Los inactivos no se ofrecen al registrar compras"
                      :checked="$item?->is_active ?? true" />
        
        <x-mus.field name="address" label="Dirección" :value="$item?->address" :wide="true" max="255" />
    </div>
</div>
