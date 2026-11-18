@php use App\Support\Formato; use App\Models\Payroll; @endphp
<x-mus.page :title="'Nómina ' . $nomina->number" :subtitle="$nomina->periodo()" icon="receipt"
            :crumbs="['Nómina' => route('payrolls.index'), $nomina->number => null]">
    <x-slot name="actions">
        @if ($nomina->esBorrador())
            @can('nomina.gestionar')
                <form method="POST" action="{{ route('payrolls.recalcular', $nomina) }}">@csrf <x-mus.btn type="submit" icon="trend">Recalcular</x-mus.btn></form>
            @endcan
            @can('nomina.aprobar')
                <form method="POST" action="{{ route('payrolls.aprobar', $nomina) }}" data-confirm="¿Aprobar la nómina {{ $nomina->number }}? Quedará bloqueada: las correcciones se hacen con una nómina de ajuste.">
                    @csrf <x-mus.btn type="submit" variant="primary" icon="check">Aprobar</x-mus.btn>
                </form>
            @endcan
        @else
            <x-mus.btn href="{{ route('payrolls.pila', $nomina) }}" icon="download">PILA</x-mus.btn>
            <x-mus.btn href="{{ route('payrolls.desprendibles', $nomina) }}" icon="download">Desprendibles (ZIP)</x-mus.btn>
            @can('nomina.gestionar')
                <form method="POST" action="{{ route('payrolls.enviar', $nomina) }}" data-confirm="¿Enviar por correo el desprendible a cada empleado?">@csrf <x-mus.btn type="submit" icon="send">Enviar desprendibles</x-mus.btn></form>
            @endcan
            @if ($nomina->status === Payroll::APROBADA)
                @can('nomina.aprobar')
                    <form method="POST" action="{{ route('payrolls.pagar', $nomina) }}" data-confirm="¿Registrar el pago de la nómina {{ $nomina->number }}?">@csrf <x-mus.btn type="submit" variant="primary" icon="money">Registrar pago</x-mus.btn></form>
                @endcan
            @endif
        @endif
    </x-slot>

    @include('nomina._nav')

    @error('nomina')<div class="naviso naviso--bad" role="alert"><span><x-mus.icon name="alert" :w="16" /></span><div><b>{{ $message }}</b></div></div>@enderror

    @if ($nomina->esAjuste() && $nomina->adjusts)
        <div class="naviso naviso--info"><span><x-mus.icon name="info" :w="16" /></span><div><b>Nómina de ajuste</b><p>Corrige a <a href="{{ route('payrolls.show', $nomina->adjusts) }}">{{ $nomina->adjusts->number }}</a>. Solo lleva los conceptos que se agreguen a mano.</p></div></div>
    @endif
    @if ($nomina->adjustments->count())
        <div class="naviso naviso--info"><span><x-mus.icon name="info" :w="16" /></span><div><b>Tiene ajustes</b><p>@foreach ($nomina->adjustments as $a)<a href="{{ route('payrolls.show', $a) }}">{{ $a->number }}</a>@if (! $loop->last), @endif @endforeach</p></div></div>
    @endif

    <div class="nk">
        <div class="nk__c nk__c--main"><span>Neto a pagar</span><b>{{ Formato::moneda($nomina->total_net) }}</b><small>{{ $nomina->employees_count }} {{ $nomina->employees_count === 1 ? 'empleado' : 'empleados' }}</small></div>
        <div class="nk__c"><span>Devengado</span><b>{{ Formato::moneda($nomina->total_earned) }}</b></div>
        <div class="nk__c"><span>Deducciones</span><b>{{ Formato::moneda($nomina->total_deductions) }}</b></div>
        <div class="nk__c"><span>Aportes del empleador</span><b>{{ Formato::moneda($nomina->total_employer) }}</b><small>seguridad social y parafiscales</small></div>
        <div class="nk__c"><span>Estado</span><b style="font-size:16px">@include('nomina._estado', ['estado' => $nomina->status, 'texto' => Payroll::ESTADOS[$nomina->status] ?? $nomina->status])</b>
            <small>
                @if ($nomina->paid_at) Pagada {{ Formato::fecha($nomina->paid_at) }} · {{ $nomina->payer?->name }}
                @elseif ($nomina->approved_at) Aprobada {{ Formato::fecha($nomina->approved_at) }} · {{ $nomina->approver?->name }}
                @else Creada por {{ $nomina->creator?->name }} @endif
            </small>
        </div>
    </div>

    <div class="ngrid">
        <x-mus.panel :pad="false" title="Empleados liquidados">
            @forelse ($nomina->employees as $f)
                <details class="nemp" @if ($loop->first && $nomina->employees->count() <= 3) open @endif>
                    <summary>
                        <span class="nwho"><b>{{ $f->employee?->nombre_completo ?? 'Empleado #' . $f->employee_id }}</b>
                            <small>{{ $f->department?->name ?? 'Sin área' }} · {{ rtrim(rtrim(number_format((float) $f->days_worked, 2, ',', '.'), '0'), ',') }} días · básico {{ Formato::moneda($f->base_salary) }}</small></span>
                        <span class="nmon"><b>{{ Formato::moneda($f->net) }}</b></span>
                    </summary>
                    <div class="mt-wrap">
                        <table class="mt">
                            <thead><tr><th>Concepto</th><th class="num">Cant.</th><th class="num">Devengado</th><th class="num">Deducción</th><th class="act"></th></tr></thead>
                            <tbody>
                                @foreach ($f->items as $i)
                                    <tr>
                                        <td>{{ $i->name }} @if ($i->is_manual)<x-mus.badge tone="info">Manual</x-mus.badge>@endif</td>
                                        <td class="num">{{ $i->quantity !== null ? rtrim(rtrim(number_format((float) $i->quantity, 2, ',', '.'), '0'), ',') : '' }}</td>
                                        <td class="num nmon">{{ $i->type === 'devengado' ? Formato::moneda($i->amount) : '' }}</td>
                                        <td class="num nmon">{{ $i->type === 'deduccion' ? Formato::moneda($i->amount) : '' }}</td>
                                        <td class="act">
                                            @if ($i->is_manual && $nomina->esBorrador())
                                                @can('nomina.gestionar')<x-mus.del :action="route('payrolls.conceptos.destroy', [$nomina, $i])" what="el concepto {{ $i->name }}" />@endcan
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td><b>Totales</b></td><td></td>
                                    <td class="num nmon"><b>{{ Formato::moneda($f->earned) }}</b></td>
                                    <td class="num nmon"><b>{{ Formato::moneda($f->deductions) }}</b></td><td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="nhint">
                        IBC {{ Formato::moneda($f->ibc) }}@if ($f->contribution?->ibc_capped) (topado a 25 SMLMV)@endif
                        · aportes del empleador {{ Formato::moneda($f->contribution?->total_employer) }}
                        @if (! $nomina->esBorrador())
                            · <a href="{{ route('payrolls.desprendible', [$nomina, $f]) }}" target="_blank" rel="noopener">Ver desprendible (PDF)</a>
                        @endif
                    </p>
                </details>
            @empty
                <x-mus.empty icon="users" title="Sin empleados" text="{{ $nomina->esAjuste() ? 'Agrega los conceptos a corregir desde el panel de la derecha.' : 'No había contratos vigentes en el período.' }}" />
            @endforelse
        </x-mus.panel>

        <div style="display:grid; gap:16px">
            <x-mus.panel :pad="false" title="Aportes del empleador">
                <ul class="nlist">
                    <li><div>Salud</div><b class="nmon">{{ Formato::moneda($aportes['salud']) }}</b></li>
                    <li><div>Pensión</div><b class="nmon">{{ Formato::moneda($aportes['pension']) }}</b></li>
                    <li><div>ARL</div><b class="nmon">{{ Formato::moneda($aportes['arl']) }}</b></li>
                    <li><div>Caja de compensación</div><b class="nmon">{{ Formato::moneda($aportes['ccf']) }}</b></li>
                    <li><div>ICBF</div><b class="nmon">{{ Formato::moneda($aportes['icbf']) }}</b></li>
                    <li><div>SENA</div><b class="nmon">{{ Formato::moneda($aportes['sena']) }}</b></li>
                </ul>
                @if ($aportes['topados'])<p class="nhint">{{ $aportes['topados'] }} {{ $aportes['topados'] === 1 ? 'empleado cotiza' : 'empleados cotizan' }} sobre el tope de 25 SMLMV.</p>@endif
            </x-mus.panel>

            @if ($nomina->esBorrador())
                @can('nomina.gestionar')
                    <x-mus.panel title="Agregar un concepto" sub="Bonos, descuentos o libranzas de un empleado">
                        @if ($conceptos->count() && $nomina->employees->count())
                            <form method="POST" action="{{ route('payrolls.conceptos.store', $nomina) }}" class="nform">
                                @csrf
                                <label>Empleado
                                    <select name="employee_id" required>
                                        @foreach ($nomina->employees as $f)
                                            <option value="{{ $f->employee_id }}" @selected(old('employee_id') == $f->employee_id)>{{ $f->employee?->nombre_completo }}</option>
                                        @endforeach
                                    </select>
                                    @error('employee_id')<span class="nerr">{{ $message }}</span>@enderror
                                </label>
                                <label>Concepto
                                    <select name="payroll_concept_id" required>
                                        @foreach ($conceptos as $c)
                                            <option value="{{ $c->id }}" @selected(old('payroll_concept_id') == $c->id)>{{ $c->name }} ({{ \App\Models\PayrollConcept::TIPOS[$c->type] ?? $c->type }})</option>
                                        @endforeach
                                    </select>
                                    @error('payroll_concept_id')<span class="nerr">{{ $message }}</span>@enderror
                                </label>
                                <div class="nform__2">
                                    <label>Valor
                                        <input type="number" name="amount" min="1" step="1" value="{{ old('amount') }}" required>
                                        @error('amount')<span class="nerr">{{ $message }}</span>@enderror
                                    </label>
                                    <label>Cantidad <small>(opcional)</small>
                                        <input type="number" name="quantity" min="0" step="0.01" value="{{ old('quantity') }}">
                                    </label>
                                </div>
                                <x-mus.btn type="submit" variant="primary" icon="plus">Agregar</x-mus.btn>
                            </form>
                        @else
                            <p class="nhint" style="border:0;background:none;padding:0">Crea primero un concepto propio (devengado o deducción) en <a href="{{ route('payroll_concepts.index') }}">Conceptos</a>.</p>
                        @endif
                    </x-mus.panel>

                    <x-mus.panel title="Eliminar el borrador">
                        <form method="POST" action="{{ route('payrolls.destroy', $nomina) }}" data-confirm="¿Eliminar el borrador {{ $nomina->number }}? Las novedades vuelven a quedar disponibles.">
                            @csrf @method('DELETE')
                            <x-mus.btn type="submit" variant="danger" icon="trash">Eliminar borrador</x-mus.btn>
                        </form>
                    </x-mus.panel>
                @endcan
            @elseif (! $nomina->esAjuste())
                @can('nomina.gestionar')
                    <x-mus.panel title="¿Hay que corregir algo?" sub="Una nómina aprobada no se edita: se ajusta">
                        <form method="POST" action="{{ route('payrolls.ajuste', $nomina) }}" class="nform">
                            @csrf
                            <label>Motivo del ajuste
                                <input type="text" name="notes" maxlength="200" placeholder="Ej.: bono no incluido">
                            </label>
                            <x-mus.btn type="submit" icon="plus">Crear nómina de ajuste</x-mus.btn>
                        </form>
                    </x-mus.panel>
                @endcan
            @endif
        </div>
    </div>

    @push('styles')
    <style>
        .nemp{ border-top:1px solid var(--line-2); }
        .nemp:first-child{ border-top:0; }
        .nemp > summary{ display:flex; align-items:center; justify-content:space-between; gap:12px; padding:13px 16px; cursor:pointer; list-style:none; }
        .nemp > summary::-webkit-details-marker{ display:none; }
        .nemp > summary:hover{ background:var(--paper); }
        .nemp[open] > summary{ border-bottom:1px solid var(--line-2); }
    </style>
    @endpush
</x-mus.page>
