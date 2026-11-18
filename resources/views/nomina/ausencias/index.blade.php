@php use App\Support\Formato; use App\Models\LeaveRequest; @endphp
<x-mus.page title="Vacaciones y ausencias" subtitle="Vacaciones, incapacidades, licencias y ausencias del equipo" icon="calendar"
            :crumbs="['Nómina' => auth()->user()->can('nomina.ver') ? route('payrolls.index') : null, 'Ausencias' => null]">
    @include('nomina._nav')

    @if ($pendientes->count())
        <x-mus.panel :pad="false" title="Solicitudes pendientes" :sub="$pendientes->count() . ' por revisar'" style="margin-bottom:16px">
            <div class="mt-wrap">
                <table class="mt">
                    <thead><tr><th>Empleado</th><th>Tipo</th><th>Fechas</th><th class="num">Días</th><th class="act"></th></tr></thead>
                    <tbody>
                        @foreach ($pendientes as $a)
                            <tr>
                                <td class="nwho"><b>{{ $a->employee?->nombre_completo }}</b><small>Registró {{ $a->registeredBy?->name }}@if ($a->notes) · {{ $a->notes }}@endif</small></td>
                                <td>{{ $a->tipoTexto() }}</td>
                                <td>{{ Formato::fecha($a->start_date) }} → {{ Formato::fecha($a->end_date) }}</td>
                                <td class="num">{{ $a->days }}</td>
                                <td class="act">
                                    @can('nomina.aprobar_novedades')
                                        <div class="nacc">
                                            <form method="POST" action="{{ route('leaves.aprobar', $a) }}">@csrf <x-mus.btn type="submit" variant="primary" :sm="true" icon="check">Aprobar</x-mus.btn></form>
                                            <form method="POST" action="{{ route('leaves.rechazar', $a) }}">
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

    @error('ausencia')<div class="naviso naviso--bad" role="alert"><span><x-mus.icon name="alert" :w="16" /></span><div><b>{{ $message }}</b></div></div>@enderror

    <div class="ngrid">
        <x-mus.panel :pad="false" title="Historial">
            <div class="nfil">
                <div class="ntabs">
                    <a href="{{ route('leaves.index') }}" class="{{ $tipo ? '' : 'is-on' }}">Todas</a>
                    @foreach (LeaveRequest::TIPOS as $clave => $nombre)
                        <a href="{{ route('leaves.index', ['tipo' => $clave]) }}" class="{{ $tipo === $clave ? 'is-on' : '' }}">{{ $nombre }}</a>
                    @endforeach
                </div>
            </div>
            @if ($novedades->total())
                <div class="mt-wrap">
                    <table class="mt">
                        <thead><tr><th>Empleado</th><th>Tipo</th><th>Fechas</th><th class="num">Días</th><th>Estado</th><th class="act"></th></tr></thead>
                        <tbody>
                            @foreach ($novedades as $a)
                                <tr>
                                    <td class="nwho"><b>{{ $a->employee?->nombre_completo }}</b>@if ($a->rejection_reason)<small>Rechazo: {{ $a->rejection_reason }}</small>@endif</td>
                                    <td>{{ $a->tipoTexto() }}</td>
                                    <td>{{ Formato::fecha($a->start_date) }} → {{ Formato::fecha($a->end_date) }}</td>
                                    <td class="num">{{ $a->days }}</td>
                                    <td>@include('nomina._estado', ['estado' => $a->status, 'texto' => LeaveRequest::ESTADOS[$a->status] ?? $a->status])</td>
                                    <td class="act">@if ($a->document_path)<x-mus.btn href="{{ route('leaves.soporte', $a) }}" :sm="true" icon="file" target="_blank">Soporte</x-mus.btn>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-mus.pagination :items="$novedades" label="novedades" />
            @else
                <x-mus.empty icon="calendar" title="Sin novedades registradas" />
            @endif
        </x-mus.panel>

        <div style="display:grid; gap:16px">
            <x-mus.panel title="Registrar una novedad">
                @if ($empleados->count())
                    <form method="POST" action="{{ route('leaves.store') }}" enctype="multipart/form-data" class="nform">
                        @csrf
                        <label>Empleado
                            <select name="employee_id" required>
                                <option value="">Selecciona…</option>
                                @foreach ($empleados as $e)
                                    <option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->first_name }} {{ $e->last_name }} · {{ $saldos[$e->id] ?? 0 }} días de vacaciones</option>
                                @endforeach
                            </select>
                            @error('employee_id')<span class="nerr">{{ $message }}</span>@enderror
                        </label>
                        <label>Tipo
                            <select name="type" required>
                                @foreach (LeaveRequest::TIPOS as $clave => $nombre)<option value="{{ $clave }}" @selected(old('type') === $clave)>{{ $nombre }}</option>@endforeach
                            </select>
                            @error('type')<span class="nerr">{{ $message }}</span>@enderror
                        </label>
                        <div class="nform__2">
                            <label>Desde
                                <input type="date" name="start_date" value="{{ old('start_date') }}" required>
                                @error('start_date')<span class="nerr">{{ $message }}</span>@enderror
                            </label>
                            <label>Hasta
                                <input type="date" name="end_date" value="{{ old('end_date') }}" required>
                                @error('end_date')<span class="nerr">{{ $message }}</span>@enderror
                            </label>
                        </div>
                        <label>Soporte <small>(PDF o imagen, máx. 4 MB)</small>
                            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png">
                            @error('document')<span class="nerr">{{ $message }}</span>@enderror
                        </label>
                        <label>Nota (opcional)
                            <input type="text" name="notes" maxlength="200" value="{{ old('notes') }}">
                        </label>
                        <x-mus.btn type="submit" variant="primary" icon="plus">Registrar</x-mus.btn>
                    </form>
                    <p class="nhint">Las vacaciones se cuentan en días hábiles y no pueden superar el saldo. Incapacidades y ausencias injustificadas se registran directo, sin aprobación.</p>
                @else
                    <x-mus.empty icon="users" title="No hay empleados activos" />
                @endif
            </x-mus.panel>

            <x-mus.panel :pad="false" title="Próximas ausencias" sub="Aprobadas en los siguientes 45 días">
                @if ($proximas->count())
                    <ul class="nlist">
                        @foreach ($proximas as $a)
                            <li><div class="nwho"><b>{{ $a->employee?->nombre_completo }}</b><small>{{ $a->tipoTexto() }}</small></div>
                                <span class="sub">{{ Formato::fecha($a->start_date) }} → {{ Formato::fecha($a->end_date) }}</span></li>
                        @endforeach
                    </ul>
                @else
                    <p class="nhint" style="border:0">Nadie tiene ausencias aprobadas próximamente.</p>
                @endif
            </x-mus.panel>
        </div>
    </div>
</x-mus.page>
