<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="code" label="Código" :value="$item?->code" max="10"
                     hint="Corto y visible en la caja: C1, C2…" />

        <x-mus.field name="name" label="Nombre" :value="$item?->name" :required="true" max="60"
                     hint="Por ejemplo: Principal, Rápida, Domicilios" />

        <x-mus.field name="description" label="Descripción" :value="$item?->description" :wide="true" max="200"
                     hint="Opcional · dónde está o para qué se usa" />

        <x-mus.toggle name="is_active" label="Caja activa"
                      hint="Solo en las cajas activas se puede abrir turno."
                      :checked="$item?->is_active ?? true" />
    </div>
</div>
