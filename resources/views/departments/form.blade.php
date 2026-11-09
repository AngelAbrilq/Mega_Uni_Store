<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="name" label="Nombre del área" :value="$area?->name" :required="true" max="60" hint="Ventas, Caja, Bodega, Administración…" />
        <x-mus.select name="manager_id" label="Responsable" :value="$area?->manager_id" empty="— Sin responsable —" :options="$empleados->all()"
                      hint="Un empleado activo del local." />
        <x-mus.field name="description" label="Descripción" :value="$area?->description" :wide="true" max="200" />
        @if ($area)
            <x-mus.toggle name="is_active" label="Área activa" hint="Inactiva no se ofrece al asignar empleados." :checked="$area->is_active" />
        @endif
    </div>
</div>
