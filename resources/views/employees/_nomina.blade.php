{{-- Contratos del empleado (NR-NOM-001, RN-45). Los salarios son reservados:
     el panel solo existe para quien tiene «nomina.ver». --}}
@php
    use App\Models\EmploymentContract;
    use App\Support\Formato;
    $contratos = $empleado->contracts->sortByDesc('start_date');
    $vigente   = $contratos->firstWhere('status', EmploymentContract::ACTIVO);
    $gestiona  = auth()->user()->can('nomina.gestionar') && ! $empleado->trashed();
@endphp
<x-mus.panel title="Contrato y nómina" :sub="$vigente ? (EmploymentContract::TIPOS[$vigente->type] ?? $vigente->type) : 'Sin contrato vigente: no entra en la nómina'" id="nomina" style="margin-top:16px">
    @foreach (['type', 'start_date', 'end_date', 'base_salary', 'position_id', 'arl_class', 'ended_at', 'motivo'] as $campo)
        @error($campo)<div class="emp-ret" role="alert"><x-mus.icon name="alert" :w="16" /><div><b>{{ $message }}</b></div></div>@enderror
    @endforeach

    @if ($vigente)
        <div class="emp-big"><b>{{ Formato::moneda($vigente->base_salary) }}</b><span>salario básico mensual · desde {{ Formato::fecha($vigente->start_date) }}{{ $vigente->end_date ? ' hasta ' . Formato::fecha($vigente->end_date) : '' }}</span></div>
        @if ($vigente->vencido())<p class="sub" style="color:var(--bad)">Este contrato ya venció: renuévalo o termínalo.</p>@endif
    @endif

    @if ($contratos->count())
        <ul class="emp-hist">
            @foreach ($contratos as $c)
                <li>
                    <span>{{ EmploymentContract::TIPOS[$c->type] ?? $c->type }} · {{ $c->position?->name ?? 'sin cargo' }} · {{ EmploymentContract::ESTADOS[$c->status] ?? $c->status }}@if ($c->end_reason) ({{ $c->end_reason }})@endif</span>
                    <em>{{ Formato::fecha($c->start_date) }} → {{ $c->ended_at ? Formato::fecha($c->ended_at) : ($c->end_date ? Formato::fecha($c->end_date) : 'indefinido') }} · {{ Formato::moneda($c->base_salary) }}</em>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($gestiona)
        <x-slot name="foot">
            <div style="display:grid; gap:14px; width:100%">
                <form method="POST" action="{{ $vigente ? route('contracts.renovar', $vigente) : route('contracts.store', $empleado) }}" class="emp-form">
                    @csrf
                    <b style="font-size:13px">{{ $vigente ? 'Renovar o cambiar condiciones' : 'Registrar contrato' }}</b>
                    <div class="emp-form--row">
                        <label>Tipo
                            <select name="type" @if (! $vigente) required @endif>
                                @foreach (EmploymentContract::TIPOS as $clave => $nombre)<option value="{{ $clave }}" @selected(old('type', $vigente?->type) === $clave)>{{ $nombre }}</option>@endforeach
                            </select>
                        </label>
                        <label>Cargo
                            <select name="position_id">
                                <option value="">Sin cargo</option>
                                @foreach ($cargos as $cargo)<option value="{{ $cargo->id }}" @selected((int) old('position_id', $vigente?->position_id) === $cargo->id)>{{ $cargo->name }}</option>@endforeach
                            </select>
                        </label>
                        <label>Salario básico
                            <input type="text" inputmode="numeric" name="base_salary" value="{{ old('base_salary', $vigente ? (int) $vigente->base_salary : '') }}" required>
                        </label>
                    </div>
                    <div class="emp-form--row">
                        <label>Inicia <input type="date" name="start_date" value="{{ old('start_date', today()->toDateString()) }}" required></label>
                        <label>Termina <input type="date" name="end_date" value="{{ old('end_date') }}"></label>
                        <label>Riesgo ARL
                            <select name="arl_class">
                                @foreach ([1, 2, 3, 4, 5] as $clase)<option value="{{ $clase }}" @selected((int) old('arl_class', $vigente?->arl_class ?? 1) === $clase)>Clase {{ $clase }}</option>@endforeach
                            </select>
                        </label>
                        <button type="submit" class="mb mb--primary mb--sm">{{ $vigente ? 'Renovar' : 'Registrar' }}</button>
                    </div>
                </form>

                @if ($vigente)
                    <form method="POST" action="{{ route('contracts.terminar', $vigente) }}" class="emp-form emp-form--row"
                          data-confirm="¿Terminar el contrato de {{ $empleado->nombre_completo }}? Después podrás calcular su liquidación final en Prestaciones.">
                        @csrf
                        <label>Fecha de terminación <input type="date" name="ended_at" value="{{ old('ended_at', today()->toDateString()) }}" required></label>
                        <label style="flex:1 1 220px">Motivo <input type="text" name="motivo" minlength="5" maxlength="255" value="{{ old('motivo') }}" required></label>
                        <button type="submit" class="mb mb--danger mb--sm">Terminar contrato</button>
                    </form>
                @endif
            </div>
        </x-slot>
    @endif
</x-mus.panel>
