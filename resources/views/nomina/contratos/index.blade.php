@php use App\Support\Formato; use App\Models\EmploymentContract; @endphp
<x-mus.page title="Contratos" subtitle="Quién está contratado, con qué salario y hasta cuándo" icon="file"
            :crumbs="['Nómina' => route('payrolls.index'), 'Contratos' => null]">
    @include('nomina._nav')

    @if ($sinContrato)
        <div class="naviso"><span><x-mus.icon name="alert" :w="16" /></span>
            <div><b>{{ $sinContrato }} {{ $sinContrato === 1 ? 'empleado activo no tiene' : 'empleados activos no tienen' }} contrato vigente</b>
                <p>No entran en la nómina hasta que se les registre uno. El contrato se crea desde la ficha del empleado, en «Contrato y nómina». <a href="{{ route('employees.index') }}">Ir a empleados</a></p></div>
        </div>
    @endif

    <x-mus.panel :pad="false">
        <div class="nfil">
            <div class="ntabs">
                @foreach (['activos' => 'Activos', 'por_vencer' => 'Por vencer (30 días)', 'historicos' => 'Históricos'] as $clave => $nombre)
                    <a href="{{ route('contracts.index', ['ver' => $clave]) }}" class="{{ $filtro === $clave ? 'is-on' : '' }}">{{ $nombre }} <i>{{ $conteo[$clave] }}</i></a>
                @endforeach
            </div>
        </div>

        @if ($contratos->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead><tr><th>Empleado</th><th>Tipo</th><th>Cargo</th><th>Vigencia</th><th class="num">Salario básico</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach ($contratos as $c)
                            <tr data-row>
                                <td class="nwho">
                                    @if ($c->employee)<a href="{{ route('employees.show', $c->employee) }}#nomina"><b>{{ $c->employee->nombre_completo }}</b></a><small>{{ $c->employee->code }}</small>
                                    @else <b>Empleado #{{ $c->employee_id }}</b> @endif
                                </td>
                                <td>{{ EmploymentContract::TIPOS[$c->type] ?? $c->type }}</td>
                                <td>{{ $c->position?->name ?? '—' }}</td>
                                <td>
                                    {{ Formato::fecha($c->start_date) }} → {{ $c->ended_at ? Formato::fecha($c->ended_at) : ($c->end_date ? Formato::fecha($c->end_date) : 'indefinido') }}
                                    @if ($c->status === EmploymentContract::ACTIVO && $c->vencido())<x-mus.badge tone="bad">Vencido</x-mus.badge>@endif
                                </td>
                                <td class="num nmon">{{ Formato::moneda($c->base_salary) }}</td>
                                <td>@include('nomina._estado', ['estado' => $c->status, 'texto' => EmploymentContract::ESTADOS[$c->status] ?? $c->status])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-mus.pagination :items="$contratos" label="contratos" />
        @else
            <x-mus.empty icon="file" title="No hay contratos en esta vista" text="Los contratos se registran desde la ficha de cada empleado." />
        @endif
    </x-mus.panel>
</x-mus.page>
