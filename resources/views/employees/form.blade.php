@php $alta = $empleado === null; @endphp
<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="first_name" label="Nombres" :value="$empleado?->first_name" :required="true" max="80" />
        <x-mus.field name="last_name" label="Apellidos" :value="$empleado?->last_name" max="80" />

        <x-mus.field name="code" label="Código de empleado" :value="$empleado?->code ?? $sugerido" :required="true" max="20"
                     hint="Único en este local. Ej.: EMP-001" style="text-transform:uppercase" />

        <x-mus.select name="user_id" label="Usuario del sistema" :value="$empleado?->user_id" empty="— No entra al sistema —"
                      :options="$usuarios->mapWithKeys(fn ($u) => [$u->id => trim($u->name . ' ' . $u->last_name) . ' · ' . $u->email])->all()"
                      hint="Opcional · vincúlalo si esta persona inicia sesión." />

        <x-mus.select name="document_type" label="Tipo de documento" :value="$empleado?->document_type" empty="—"
                      :options="['CC' => 'Cédula de ciudadanía', 'CE' => 'Cédula de extranjería', 'TI' => 'Tarjeta de identidad', 'PAS' => 'Pasaporte', 'PPT' => 'Permiso de protección temporal']" />
        <x-mus.field name="document_number" label="Número de documento" :value="$empleado?->document_number" max="20" />

        <x-mus.field name="phone" label="Teléfono" :value="$empleado?->phone" max="20" />
        <x-mus.field name="email" label="Correo" type="email" :value="$empleado?->email" max="120" />

        <x-mus.field name="hire_date" label="Fecha de ingreso" type="date" :required="true"
                     :value="$empleado?->hire_date?->format('Y-m-d') ?? today()->format('Y-m-d')" />
        <x-mus.field name="base_salary" label="Salario básico mensual" type="number" step="1" min="0" prefix="$" :required="true"
                     :value="$empleado ? (int) $empleado->base_salary : null" />
    </div>

    @if ($alta)
        <h4 class="emp-sec">Dónde trabaja <small>opcional · también se asigna después desde la ficha</small></h4>
        <div class="mf__grid">
            <x-mus.select name="position_id" label="Cargo" empty="— Sin cargo —"
                          :options="$cargos->mapWithKeys(fn ($c) => [$c->id => $c->name . ' · nivel ' . $c->level])->all()" />
            <x-mus.select name="department_id" label="Área principal" empty="— Sin área —" :options="$areas->all()" />
            <x-mus.select name="work_schedule_id" label="Horario" empty="— Sin horario —"
                          :options="$horarios->mapWithKeys(fn ($h) => [$h->id => $h->name . ' · ' . $h->horasSemana() . ' h/semana'])->all()" />
        </div>
    @endif
</div>

@push('styles')
<style>
    .emp-sec{ margin:22px 0 10px; font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); }
    .emp-sec small{ font-weight:600; text-transform:none; letter-spacing:0; margin-left:6px; opacity:.8; }
</style>
@endpush
