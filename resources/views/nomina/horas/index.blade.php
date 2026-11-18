@php use App\Support\Formato; use App\Models\OvertimeEntry; @endphp
<x-mus.page title="Horas extra y recargos" subtitle="Se registran, un responsable las aprueba y entran en la siguiente liquidación" icon="clock"
            :crumbs="['Nómina' => auth()->user()->can('nomina.ver') ? route('payrolls.index') : null, 'Horas extra' => null]">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('overtime.excel', ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'estado' => $estado]) }}" icon="download">Excel</x-mus.btn>
    </x-slot>

    @include('nomina._nav')

    @if ($pendientes->count())
        <x-mus.panel :pad="false" title="Pendientes de aprobación" :sub="$pendientes->count() . ' por revisar'" style="margin-bottom:16px">
            <div class="mt-wrap">
                <table class="mt">
                    <thead><tr><th>Empleado</th><th>Fecha</th><th>Tipo</th><th class="num">Horas</th><th class="num">Valor</th><th class="act"></th></tr></thead>
                    <tbody>
                        @foreach ($pendientes as $h)
                            <tr>
                                <td class="nwho"><b>{{ $h->employee?->nombre_completo }}</b><small>Registró {{ $h->registeredBy?->name }}@if ($h->notes) · {{ $h->notes }}@endif</small></td>
                                <td>{{ Formato::fecha($h->date) }}</td>
                                <td>{{ $h->tipoTexto() }}</td>
                                <td class="num">{{ rtrim(rtrim(number_format((float) $h->hours, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="num nmon">{{ Formato::moneda($h->amount) }}</td>
                                <td class="act">
                                    @can('nomina.aprobar_novedades')
                                        <div class="nacc">
                                            <form method="POST" action="{{ route('overtime.aprobar', $h) }}">@csrf <x-mus.btn type="submit" variant="primary" :sm="true" icon="check">Aprobar</x-mus.btn></form>
                                            <form method="POST" action="{{ route('overtime.rechazar', $h) }}">
                                                @csrf
                                                <input type="text" name="motivo" placeholder="Motivo del rechazo" minlength="5" maxlength="255" required aria-label="Motivo del rechazo">
                                                <x-mus.btn type="submit" :sm="true">Rechazar</x-mus.btn>
                                            </form>
                                        </div>
                                    @else
                                        @include('nomina._estado', ['estado' => 'pendiente', 'texto' => 'Pendiente'])
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('motivo')<p class="nhint nerr">{{ $message }}</p>@enderror
        </x-mus.panel>
    @endif

    <div class="ngrid">
        <x-mus.panel :pad="false" title="Por empleado">
            <form method="GET" action="{{ route('overtime.index') }}" class="nfil">
                <label>Desde <input type="date" name="desde" value="{{ $desde->toDateString() }}"></label>
                <label>Hasta <input type="date" name="hasta" value="{{ $hasta->toDateString() }}"></label>
                <label>Estado
                    <select name="estado">
                        <option value="">Todos</option>
                        @foreach (OvertimeEntry::ESTADOS as $clave => $nombre)<option value="{{ $clave }}" @selected($estado === $clave)>{{ $nombre }}</option>@endforeach
                    </select>
                </label>
                <span class="nfil__sp"></span>
                <x-mus.btn type="submit" :sm="true" icon="filter">Filtrar</x-mus.btn>
            </form>

            @if ($grupos->count())
                <div class="mt-wrap">
                    <table class="mt">
                        <thead><tr><th>Empleado</th><th>Detalle</th><th class="num">Horas</th><th class="num">Valor</th></tr></thead>
                        <tbody>
                            @foreach ($grupos as $g)
                                <tr>
                                    <td class="nwho"><b>{{ $g['empleado']?->nombre_completo ?? '—' }}</b><small>{{ $g['empleado']?->code }}</small></td>
                                    <td class="sub">
                                        @foreach ($g['por_tipo'] as $tipo => $t)
                                            {{ $tipos[$tipo]['nombre'] ?? $tipo }}: {{ rtrim(rtrim(number_format($t['horas'], 2, ',', '.'), '0'), ',') }} h{{ $loop->last ? "" : " · " }}
                                        @endforeach
                                    </td>
                                    <td class="num">{{ rtrim(rtrim(number_format($g['horas'], 2, ',', '.'), '0'), ',') }}</td>
                                    <td class="num nmon"><b>{{ Formato::moneda($g['valor']) }}</b></td>
                                </tr>
                            @endforeach
                            <tr><td><b>Total</b></td><td></td>
                                <td class="num"><b>{{ rtrim(rtrim(number_format($grupos->sum('horas'), 2, ',', '.'), '0'), ',') }}</b></td>
                                <td class="num nmon"><b>{{ Formato::moneda($grupos->sum('valor')) }}</b></td></tr>
                        </tbody>
                    </table>
                </div>
            @else
                <x-mus.empty icon="clock" title="Sin horas extra en ese rango" text="Cambia las fechas o registra una novedad." />
            @endif
        </x-mus.panel>

        <x-mus.panel title="Registrar horas" sub="Quedan pendientes hasta que se aprueben">
            @if ($empleados->count())
                <form method="POST" action="{{ route('overtime.store') }}" class="nform">
                    @csrf
                    <label>Empleado
                        <select name="employee_id" required>
                            <option value="">Selecciona…</option>
                            @foreach ($empleados as $e)<option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->first_name }} {{ $e->last_name }}</option>@endforeach
                        </select>
                        @error('employee_id')<span class="nerr">{{ $message }}</span>@enderror
                    </label>
                    <div class="nform__2">
                        <label>Fecha
                            <input type="date" name="date" max="{{ today()->toDateString() }}" value="{{ old('date', today()->toDateString()) }}" required>
                            @error('date')<span class="nerr">{{ $message }}</span>@enderror
                        </label>
                        <label>Horas
                            <input type="number" name="hours" min="0.25" max="12" step="0.25" value="{{ old('hours') }}" required>
                            @error('hours')<span class="nerr">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    <label>Tipo
                        <select name="type" required>
                            @foreach ($tipos as $clave => $t)
                                <option value="{{ $clave }}" @selected(old('type') === $clave)>{{ $t['nombre'] }} (×{{ rtrim(rtrim(number_format($t['factor'], 2, ',', '.'), '0'), ',') }})</option>
                            @endforeach
                        </select>
                        @error('type')<span class="nerr">{{ $message }}</span>@enderror
                    </label>
                    <label>Nota (opcional)
                        <input type="text" name="notes" maxlength="200" value="{{ old('notes') }}">
                    </label>
                    <x-mus.btn type="submit" variant="primary" icon="plus">Registrar</x-mus.btn>
                </form>
                <p class="nhint">El valor de la hora sale del salario del contrato dividido entre {{ $horasMes }} horas al mes, por el factor del tipo.</p>
            @else
                <x-mus.empty icon="users" title="No hay empleados activos" text="Registra empleados y sus contratos para llevar novedades." />
            @endif
        </x-mus.panel>
    </div>
</x-mus.page>
