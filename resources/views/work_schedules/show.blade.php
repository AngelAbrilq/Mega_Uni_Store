@php use App\Models\WorkSchedule; @endphp
<x-mus.page :title="$horario->name" :subtitle="$horario->horasSemana() . ' horas por semana' . ($horario->description ? ' · ' . $horario->description : '')" icon="clock"
            :crumbs="['Empleados' => route('employees.index'), 'Horarios' => route('work_schedules.index'), $horario->name => null]">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('work_schedules.edit', $horario) }}" icon="pencil">Editar</x-mus.btn>
        <x-mus.del :action="route('work_schedules.destroy', $horario)" what="el horario {{ $horario->name }}" />
    </x-slot>

    @error('horario')<div class="rh-err"><x-mus.icon name="alert" :w="15" /> {{ $message }}</div>@enderror

    <div class="hor-grid">
        <x-mus.panel title="La semana" sub="Franjas ordenadas por día">
            @include('work_schedules._semana', ['franjas' => $horario->shifts])

            @if ($horario->shifts->count())
                <ul class="hor-fr">
                    @foreach ($horario->shifts as $f)
                        <li>
                            <b>{{ $f->dia }}</b><span>{{ $f->rango }}</span><em>{{ $f->horas() }} h</em>
                            <form method="POST" action="{{ route('work_schedules.turnos.destroy', [$horario, $f]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="mb mb--ghost mb--sm mb--icon" title="Quitar franja"><x-mus.icon name="close" :w="14" /></button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <x-slot name="foot">
                <form method="POST" action="{{ route('work_schedules.turnos.store', $horario) }}" class="hor-add">
                    @csrf
                    <div class="hor-dias" role="group" aria-label="Días">
                        @foreach (WorkSchedule::DIAS as $n => $dia)
                            <label><input type="checkbox" name="weekdays[]" value="{{ $n }}" @checked(in_array($n, old('weekdays', []), false))><span>{{ mb_substr($dia, 0, 3) }}</span></label>
                        @endforeach
                    </div>
                    <label>Entrada <input type="time" name="start_time" value="{{ old('start_time', '07:00') }}" required></label>
                    <label>Salida <input type="time" name="end_time" value="{{ old('end_time', '15:00') }}" required></label>
                    <button type="submit" class="mb mb--primary mb--sm">Agregar franja</button>
                </form>
                @if ($errors->any())
                    <p class="mfld__err" style="width:100%;margin:6px 0 0">{{ $errors->first() }}</p>
                @endif
            </x-slot>
        </x-mus.panel>

        <x-mus.panel title="Quién lo tiene hoy" :sub="$horario->asignacionesVigentes->count() . ' ' . ($horario->asignacionesVigentes->count() === 1 ? 'empleado' : 'empleados')">
            @forelse ($horario->asignacionesVigentes as $a)
                <div class="hor-emp">
                    <a href="{{ route('employees.show', $a->employee) }}">{{ $a->employee?->nombre_completo }}</a>
                    <span>desde {{ $a->start_date->format('d/m/Y') }}</span>
                </div>
            @empty
                <p class="sub" style="margin:0">Nadie todavía. Se asigna desde la ficha del empleado.</p>
            @endforelse
        </x-mus.panel>
    </div>

    @include('departments._estilos')
    @push('styles')
    <style>
        .hor-grid{ display:grid; gap:16px; grid-template-columns:minmax(0,2fr) minmax(0,1fr); align-items:start; }
        @media (max-width:960px){ .hor-grid{ grid-template-columns:minmax(0,1fr); } }
        .hor-fr{ list-style:none; margin:14px 0 0; padding:0; border-top:1px solid var(--line-2); }
        .hor-fr li{ display:flex; align-items:center; gap:12px; padding:7px 0; border-bottom:1px solid var(--line-2); font-size:13px; }
        .hor-fr li b{ width:90px; color:var(--ink); }
        .hor-fr li span{ font-variant-numeric:tabular-nums; }
        .hor-fr li em{ font-style:normal; color:var(--muted); font-size:12px; margin-left:auto; }
        .hor-fr form{ margin:0; }
        .hor-add{ display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; width:100%; }
        .hor-add > label{ display:grid; gap:3px; font-size:11.5px; font-weight:600; color:var(--muted); }
        .hor-add input[type=time]{ height:34px; padding:0 8px; border:1px solid var(--line); border-radius:8px; font:inherit; font-size:13px; }
        .hor-dias{ display:flex; gap:4px; flex-wrap:wrap; }
        .hor-dias input{ position:absolute; opacity:0; pointer-events:none; }
        .hor-dias span{ display:inline-grid; place-items:center; min-width:40px; height:34px; padding:0 8px; border-radius:8px; border:1px solid rgba(15,23,42,.14);
                        font-size:12.5px; font-weight:600; color:var(--ink-2); cursor:pointer; background:var(--card); }
        .hor-dias input:checked + span{ background:var(--a-600); border-color:var(--a-600); color:#fff; }
        .hor-dias input:focus-visible + span{ outline:2px solid var(--a-400); outline-offset:2px; }
        .hor-emp{ display:flex; justify-content:space-between; gap:8px; padding:8px 0; border-bottom:1px solid var(--line-2); font-size:13px; }
        .hor-emp:last-child{ border-bottom:0; }
        .hor-emp a{ font-weight:600; color:var(--ink); }
        .hor-emp span{ color:var(--muted); font-size:12px; }
    </style>
    @endpush
</x-mus.page>
