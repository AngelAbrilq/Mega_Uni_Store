@php use App\Support\Formato; use App\Models\Payroll; @endphp
<x-mus.page title="Nómina" subtitle="Liquidaciones del local: se calculan en borrador, se aprueban y se pagan" icon="receipt">
    @include('nomina._nav')

    @error('nomina')<div class="naviso naviso--bad" role="alert"><span><x-mus.icon name="alert" :w="16" /></span><div><b>{{ $message }}</b></div></div>@enderror

    @if ($pendientes['sinContrato'] || $pendientes['vencidos'])
        <div class="naviso" role="status">
            <span><x-mus.icon name="alert" :w="16" /></span>
            <div>
                <b>Hay contratos por revisar antes de liquidar</b>
                <p>
                    @if ($pendientes['sinContrato']) {{ $pendientes['sinContrato'] }} {{ $pendientes['sinContrato'] === 1 ? 'empleado activo no tiene' : 'empleados activos no tienen' }} contrato vigente y no entrarán en la nómina. @endif
                    @if ($pendientes['vencidos']) {{ $pendientes['vencidos'] }} {{ $pendientes['vencidos'] === 1 ? 'contrato ya venció' : 'contratos ya vencieron' }}. @endif
                    <a href="{{ route('contracts.index') }}">Ver contratos</a>
                </p>
            </div>
        </div>
    @endif

    <div class="nk">
        <div class="nk__c nk__c--main"><span>Último neto pagado</span><b>{{ Formato::moneda($resumen['ultimoNeto']) }}</b><small>última nómina aprobada</small></div>
        <div class="nk__c"><span>Costo laboral del año</span><b>{{ Formato::moneda($resumen['anio']) }}</b><small>devengado + aportes del empleador</small></div>
        <div class="nk__c {{ $resumen['borradores'] ? 'nk__c--warn' : '' }}"><span>Borradores</span><b>{{ $resumen['borradores'] }}</b><small>sin aprobar</small></div>
        @can('nomina.novedades')
            <a class="nk__c {{ $pendientes['horas'] ? 'nk__c--warn' : '' }}" href="{{ route('overtime.index') }}"><span>Horas extra pendientes</span><b>{{ $pendientes['horas'] }}</b><small>por aprobar</small></a>
            <a class="nk__c {{ $pendientes['ausencias'] ? 'nk__c--warn' : '' }}" href="{{ route('leaves.index') }}"><span>Ausencias pendientes</span><b>{{ $pendientes['ausencias'] }}</b><small>por aprobar</small></a>
        @endcan
    </div>

    <div class="ngrid">
        <x-mus.panel :pad="false" title="Liquidaciones">
            <div class="nfil">
                <div class="ntabs">
                    <a href="{{ route('payrolls.index') }}" class="{{ $estado ? '' : 'is-on' }}">Todas</a>
                    @foreach (Payroll::ESTADOS as $clave => $nombre)
                        <a href="{{ route('payrolls.index', ['estado' => $clave]) }}" class="{{ $estado === $clave ? 'is-on' : '' }}">{{ $nombre }}</a>
                    @endforeach
                </div>
            </div>

            @if ($nominas->total())
                <div class="mt-wrap">
                    <table class="mt">
                        <thead><tr><th>Nómina</th><th>Período</th><th class="num">Empleados</th><th class="num">Devengado</th><th class="num">Neto</th><th>Estado</th></tr></thead>
                        <tbody>
                            @foreach ($nominas as $n)
                                <tr data-row>
                                    <td>
                                        <a href="{{ route('payrolls.show', $n) }}" class="ncode">{{ $n->number }}</a>
                                        @if ($n->esAjuste())<x-mus.badge tone="info">Ajuste</x-mus.badge>@endif
                                        <span class="sub" style="display:block">{{ $n->creator?->name }}</span>
                                    </td>
                                    <td>{{ $n->periodo() }}</td>
                                    <td class="num">{{ $n->employees_count }}</td>
                                    <td class="num nmon">{{ Formato::moneda($n->total_earned) }}</td>
                                    <td class="num nmon"><b>{{ Formato::moneda($n->total_net) }}</b></td>
                                    <td>@include('nomina._estado', ['estado' => $n->status, 'texto' => Payroll::ESTADOS[$n->status] ?? $n->status])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-mus.pagination :items="$nominas" label="nóminas" />
            @else
                <x-mus.empty icon="receipt" title="{{ $estado ? 'No hay nóminas en ese estado' : 'Todavía no se ha liquidado ninguna nómina' }}"
                             text="Registra los contratos, aprueba las novedades del período y liquida: queda en borrador para que la revises." />
            @endif
        </x-mus.panel>

        @can('nomina.gestionar')
            <x-mus.panel title="Liquidar un período" sub="Calcula a todos los empleados con contrato vigente">
                <form method="POST" action="{{ route('payrolls.store') }}" class="nform">
                    @csrf
                    <div class="nform__2">
                        <label>Desde
                            <input type="date" name="period_start" value="{{ old('period_start', $sugerido['desde']->toDateString()) }}" required>
                            @error('period_start')<span class="nerr">{{ $message }}</span>@enderror
                        </label>
                        <label>Hasta
                            <input type="date" name="period_end" value="{{ old('period_end', $sugerido['hasta']->toDateString()) }}" required>
                            @error('period_end')<span class="nerr">{{ $message }}</span>@enderror
                        </label>
                    </div>
                    <label>Nota (opcional)
                        <input type="text" name="notes" maxlength="200" value="{{ old('notes') }}" placeholder="Ej.: primera quincena">
                    </label>
                    <x-mus.btn type="submit" variant="primary" icon="check">Calcular en borrador</x-mus.btn>
                </form>
                <p class="nhint">Solo entran las horas extra y ausencias <b>aprobadas</b>. Mientras esté en borrador se puede recalcular o eliminar; al aprobarla queda bloqueada.</p>
            </x-mus.panel>
        @endcan
    </div>
</x-mus.page>
