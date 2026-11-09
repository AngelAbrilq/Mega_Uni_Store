<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="name" label="Nombre del cargo" :value="$cargo?->name" :required="true" max="60" />
        <x-mus.field name="level" label="Nivel jerárquico" type="number" min="1" max="10" step="1" :required="true"
                     :value="$cargo?->level ?? 5" hint="1 = el más alto (Gerente). 10 = el de menor responsabilidad." />
        <x-mus.field name="description" label="Descripción" :value="$cargo?->description" :wide="true" max="200" />
        @if ($cargo)
            <x-mus.toggle name="is_active" label="Cargo activo" hint="Inactivo no se ofrece al asignar." :checked="$cargo->is_active" />
        @endif
    </div>
</div>
