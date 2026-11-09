@php
    use App\Support\Formato;
    use App\Models\WorkSchedule;
    $cargo    = $empleado->cargoActual();
    $horario  = $empleado->horarioActual();
    $retirado = $empleado->trashed();
    $editar   = auth()->user()->can('empleados.editar') && ! $retirado;
@endphp

<x-mus.page :title="$empleado->nombre_completo" :subtitle="($cargo?->name ?? 'Sin cargo') . ' · ' . $empleado->code" icon="users"
            :crumbs="['Empleados' => route('employees.index'), $empleado->nombre_completo => null]">
    <x-slot name="actions">
        @if ($editar)
            <x-mus.btn href="{{ route('employees.edit', $empleado) }}" icon="pencil">Editar datos</x-mus.btn>
        @endif
    </x-slot>

    @if ($retirado)
        <div class="emp-ret">
            <x-mus.icon name="alert" :w="16" />
            <div><b>Retirado el {{ $empleado->termination_date?->format('d/m/Y') }}</b>
                @if ($empleado->termination_reason)<span>{{ $empleado->termination_reason }}</span>@endif</div>
        </div>
    @endif

    <div class="emp-grid">
        <div class="emp-col">
            <x-mus.panel title="Datos" sub="Identificación y contrato">
                <div class="mficha">
                    <span class="emp-av emp-av--lg">{{ $empleado->iniciales }}</span>
                    <div class="mficha__t">
                        <b>{{ $empleado->nombre_completo }}</b>
                        <span>{{ $empleado->user ? 'Entra al sistema como ' . $empleado->user->email : 'No tiene usuario del sistema' }}</span>
                    </div>
                </div>
                <dl class="mdl">
                    <div><dt>Código</dt><dd><code>{{ $empleado->code }}</code></dd></div>
                    <div><dt>Documento</dt><dd>{{ trim($empleado->document_type . ' ' . $empleado->document_number) ?: '—' }}</dd></div>
                    <div><dt>Teléfono</dt><dd>{{ $empleado->phone ?: '—' }}</dd></div>
                    <div><dt>Correo</dt><dd>{{ $empleado->email ?: '—' }}</dd></div>
                    <div><dt>Ingreso</dt><dd>{{ Formato::enPalabras($empleado->hire_date, 'D [de] MMMM [de] YYYY', '—') }}</dd></div>
                    @can('empleados.editar')
                        <div><dt>Salario básico</dt><dd><b>{{ Formato::moneda($empleado->base_salary) }}</b></dd></div>
                    @endcan
                    <div><dt>Estado</dt><dd>
                        <x-mus.badge :tone="$retirado ? 'off' : 'ok'" :dot="true">{{ $retirado ? 'Retirado' : 'Activo' }}</x-mus.badge>
                    </dd></div>
                </dl>
            </x-mus.panel>

            @can('empleados.eliminar')
                @unless ($retirado)
                    <x-mus.panel title="Registrar retiro" sub="Sale de los activos; su historia se conserva" class="emp-peligro">
                        <form method="POST" action="{{ route('employees.destroy', $empleado) }}" class="emp-form"
                              data-confirm="¿Registrar el retiro de {{ $empleado->nombre_completo }}?">
                            @csrf @method('DELETE')
                            <label>Fecha de retiro
                                <input type="date" name="fecha" value="{{ today()->format('Y-m-d') }}" min="{{ $empleado->hire_date?->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" required>
                            </label>
                            <label>Motivo
                                <input type="text" name="motivo" required minlength="5" maxlength="255" placeholder="Renuncia voluntaria, fin de contrato…">
                            </label>
                            <button type="submit" class="mb mb--danger">Registrar retiro</button>
                        </form>
                    </x-mus.panel>
                @endunless
            @endcan
        </div>

        <div class="emp-col">
            {{-- ═══ Cargo (AT-CGO-001) ═══ --}}
            <x-mus.panel title="Cargo" :sub="$cargo ? 'Desde ' . \Illuminate\Support\Carbon::parse($cargo->pivot->start_date)->format('d/m/Y') : 'Sin cargo asignado'">
                @if ($cargo)
                    <div class="emp-big"><b>{{ $cargo->name }}</b><span>Nivel {{ $cargo->level }}</span></div>
                @endif
                @if ($empleado->positions->count() > ($cargo ? 1 : 0))
                    <ul class="emp-hist">
                        @foreach ($empleado->positions->reject(fn ($p) => $p->pivot->is_active) as $p)
                            <li><span>{{ $p->name }}</span><em>{{ \Illuminate\Support\Carbon::parse($p->pivot->start_date)->format('d/m/Y') }} → {{ $p->pivot->end_date ? \Illuminate\Support\Carbon::parse($p->pivot->end_date)->format('d/m/Y') : '—' }}</em></li>
                        @endforeach
                    </ul>
                @endif
                @if ($editar && $cargos->count())
                    <x-slot name="foot">
                        <form method="POST" action="{{ route('employees.asignar', $empleado) }}" class="emp-form emp-form--row">
                            @csrf <input type="hidden" name="tipo" value="cargo">
                            <select name="position_id" required aria-label="Cargo">
                                @foreach ($cargos as $c)<option value="{{ $c->id }}" @selected($cargo?->id === $c->id)>{{ $c->name }} · nivel {{ $c->level }}</option>@endforeach
                            </select>
                            <input type="date" name="desde" value="{{ today()->format('Y-m-d') }}" aria-label="Desde">
                            <button type="submit" class="mb mb--primary mb--sm">{{ $cargo ? 'Cambiar cargo' : 'Asignar' }}</button>
                        </form>
                    </x-slot>
                @endif
            </x-mus.panel>

            {{-- ═══ Áreas (TBL-EHA-001) ═══ --}}
            <x-mus.panel title="Áreas" :sub="$empleado->departments->count() ? $empleado->departments->count() . ' · la principal va primero' : 'Sin área asignada'">
                @if ($empleado->departments->count())
                    <ul class="emp-areas">
                        @foreach ($empleado->departments as $d)
                            <li>
                                <a href="{{ route('departments.show', $d) }}">{{ $d->name }}</a>
                                @if ($d->pivot->is_primary)<x-mus.badge tone="info">Principal</x-mus.badge>@endif
                                <em>desde {{ \Illuminate\Support\Carbon::parse($d->pivot->assigned_at)->format('d/m/Y') }}</em>
                                @if ($editar)
                                    <span class="emp-areas__acc">
                                        @unless ($d->pivot->is_primary)
                                            <form method="POST" action="{{ route('employees.asignar', $empleado) }}">
                                                @csrf <input type="hidden" name="tipo" value="area"><input type="hidden" name="department_id" value="{{ $d->id }}"><input type="hidden" name="is_primary" value="1">
                                                <button type="submit" class="mb mb--ghost mb--sm">Hacer principal</button>
                                            </form>
                                        @endunless
                                        <form method="POST" action="{{ route('employees.areas.quitar', [$empleado, $d]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="mb mb--ghost mb--sm mb--icon" title="Quitar"><x-mus.icon name="close" :w="14" /></button>
                                        </form>
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @php $libres = $areas->except($empleado->departments->modelKeys()); @endphp
                @if ($editar && $libres->count())
                    <x-slot name="foot">
                        <form method="POST" action="{{ route('employees.asignar', $empleado) }}" class="emp-form emp-form--row">
                            @csrf <input type="hidden" name="tipo" value="area">
                            <select name="department_id" required aria-label="Área">
                                @foreach ($libres as $id => $nombre)<option value="{{ $id }}">{{ $nombre }}</option>@endforeach
                            </select>
                            <label class="emp-chk"><input type="checkbox" name="is_primary" value="1" @checked(! $empleado->departments->count())> Principal</label>
                            <button type="submit" class="mb mb--primary mb--sm">Agregar área</button>
                        </form>
                    </x-slot>
                @endif
            </x-mus.panel>

            {{-- ═══ Horario (AT-HOR-001) ═══ --}}
            <x-mus.panel title="Horario" :sub="$horario ? $horario->schedule->name . ' · desde ' . $horario->start_date->format('d/m/Y') : 'Sin horario asignado'">
                @if ($horario)
                    @include('work_schedules._semana', ['franjas' => $horario->schedule->shifts])
                @endif
                @php $previos = $empleado->scheduleAssignments->reject(fn ($a) => $a->id === $horario?->id); @endphp
                @if ($previos->count())
                    <ul class="emp-hist">
                        @foreach ($previos as $a)
                            <li><span>{{ $a->schedule?->name }}</span><em>{{ $a->start_date->format('d/m/Y') }} → {{ $a->end_date?->format('d/m/Y') ?? '—' }}</em></li>
                        @endforeach
                    </ul>
                @endif
                @if ($editar && $horarios->count())
                    <x-slot name="foot">
                        <form method="POST" action="{{ route('employees.asignar', $empleado) }}" class="emp-form emp-form--row">
                            @csrf <input type="hidden" name="tipo" value="horario">
                            <select name="work_schedule_id" required aria-label="Horario">
                                @foreach ($horarios as $h)<option value="{{ $h->id }}" @selected($horario?->work_schedule_id === $h->id)>{{ $h->name }} · {{ $h->horasSemana() }} h/sem</option>@endforeach
                            </select>
                            <input type="date" name="desde" value="{{ today()->format('Y-m-d') }}" aria-label="Desde">
                            <button type="submit" class="mb mb--primary mb--sm">{{ $horario ? 'Cambiar horario' : 'Asignar' }}</button>
                        </form>
                    </x-slot>
                @endif
            </x-mus.panel>
        </div>
    </div>

    @push('styles')
    <style>
        .emp-grid{ display:grid; gap:16px; grid-template-columns:minmax(0,1fr) minmax(0,1.3fr); align-items:start; }
        .emp-col{ display:grid; gap:16px; min-width:0; }
        @media (max-width:960px){ .emp-grid{ grid-template-columns:minmax(0,1fr); } }
        .emp-av{ width:34px; height:34px; flex:none; border-radius:50%; display:grid; place-items:center; font-size:12px; font-weight:700;
                 background:rgba(46,110,168,.12); color:var(--a-700, #1f4f7a); }
        .emp-av--lg{ width:64px; height:64px; font-size:22px; }
        .emp-ret{ display:flex; gap:10px; align-items:flex-start; margin-bottom:16px; padding:12px 16px; border-radius:12px;
                  background:var(--paper); border:1px solid var(--line); color:var(--muted); }
        .emp-ret b{ display:block; color:var(--ink); font-size:13.4px; }
        .emp-ret span{ font-size:12.5px; }
        .emp-big{ display:flex; align-items:baseline; gap:10px; }
        .emp-big b{ font-size:20px; letter-spacing:-.02em; color:var(--ink); }
        .emp-big span{ font-size:12px; color:var(--muted); }
        .emp-hist{ list-style:none; margin:12px 0 0; padding:0; border-top:1px dashed var(--line); }
        .emp-hist li{ display:flex; justify-content:space-between; gap:10px; padding:7px 0; font-size:12.6px; color:var(--muted); border-bottom:1px dashed var(--line-2); }
        .emp-hist li:last-child{ border-bottom:0; }
        .emp-hist em{ font-style:normal; font-variant-numeric:tabular-nums; }
        .emp-areas{ list-style:none; margin:0; padding:0; }
        .emp-areas li{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:8px 0; border-bottom:1px solid var(--line-2); font-size:13.2px; }
        .emp-areas li:last-child{ border-bottom:0; }
        .emp-areas a{ font-weight:600; color:var(--ink); }
        .emp-areas em{ font-style:normal; font-size:11.8px; color:var(--muted); }
        .emp-areas__acc{ margin-left:auto; display:flex; gap:4px; }
        .emp-areas__acc form{ margin:0; }
        .emp-form{ display:grid; gap:10px; }
        .emp-form label{ display:grid; gap:4px; font-size:12px; font-weight:600; color:var(--ink-2); }
        .emp-form input[type=text], .emp-form input[type=date], .emp-form select{ height:36px; padding:0 10px; border:1px solid var(--line); border-radius:9px; font:inherit; font-size:13px; background:var(--card); color:var(--ink); }
        .emp-form--row{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; width:100%; }
        .emp-form--row select{ flex:1 1 180px; min-width:0; }
        .emp-form .emp-chk{ display:flex; gap:6px; align-items:center; font-weight:600; }
        .emp-peligro .mp__head h3{ color:var(--bad); }
        @media (max-width:760px){ .emp-form input[type=text], .emp-form input[type=date], .emp-form select{ height:42px !important; font-size:16px !important; } }
    </style>
    @endpush
</x-mus.page>
