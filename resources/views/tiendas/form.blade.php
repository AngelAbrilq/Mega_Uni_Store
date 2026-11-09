<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="nombre" label="Nombre del local" :value="$item?->nombre" :required="true"
                     max="150" hint="Como lo llama la gente: Sede Norte, Centro, La 80" />

        <x-mus.field name="codigo" label="Prefijo de documentos" :value="$item?->codigo"
                     max="6" hint="Sale en los números: V-N-000001. Déjalo vacío si no lo necesitas" />

        <x-mus.field name="direccion" label="Dirección" :value="$item?->direccion" max="180" :wide="true" />

        <x-mus.field name="ciudad" label="Ciudad" :value="$item?->ciudad" max="80" />

        <x-mus.field name="telefono" label="Teléfono" :value="$item?->telefono" max="30" />

        <x-mus.field name="correo" label="Correo" type="email" :value="$item?->correo" max="150" />
    </div>
</div>

<div class="mf" style="margin-top:18px">
    <p style="margin:0 0 12px;color:var(--muted-2);font-size:.9rem">
        Identidad propia. Si los dejas vacíos, este local factura con el NIT y la razón social
        del negocio — que es lo normal. Se llenan solo cuando la sede factura aparte.
    </p>

    <div class="mf__grid">
        <x-mus.field name="razon_social" label="Razón social" :value="$item?->razon_social" max="180" />
        <x-mus.field name="nit" label="NIT" :value="$item?->nit" max="30" />
    </div>
</div>
