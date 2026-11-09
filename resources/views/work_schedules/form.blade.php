<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="name" label="Nombre del horario" :value="$horario?->name" :required="true" max="60" hint="Turno mañana, Turno tarde, Fin de semana…" />
        <x-mus.field name="description" label="Descripción" :value="$horario?->description" max="200" />
        @if ($horario)
            <x-mus.toggle name="is_active" label="Horario activo" hint="Inactivo no se ofrece al asignar." :checked="$horario->is_active" />
        @endif
    </div>
</div>
