@php use App\Models\PayrollConcept; @endphp
<x-mus.page title="Conceptos de nómina" subtitle="Lo que se paga y lo que se descuenta. Los de ley los calcula el sistema" icon="layers"
            :crumbs="['Nómina' => route('payrolls.index'), 'Conceptos' => null]">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('payroll_concepts.create') }}" variant="primary" icon="plus">Nuevo concepto</x-mus.btn>
    </x-slot>

    @include('nomina._nav')

    <x-mus.panel :pad="false">
        <div class="mt-wrap">
            <table class="mt">
                <thead><tr><th>Concepto</th><th>Tipo</th><th>Cálculo</th><th>Aplica</th><th class="num">Usos</th><th>Estado</th><th class="act"></th></tr></thead>
                <tbody>
                    @foreach ($conceptos as $c)
                        <tr data-row>
                            <td><b>{{ $c->name }}</b> <span class="ncode" style="margin-left:6px">{{ $c->code }}</span>
                                @if ($c->is_system)<x-mus.badge tone="info">De ley</x-mus.badge>@endif</td>
                            <td>{{ PayrollConcept::TIPOS[$c->type] ?? $c->type }}</td>
                            <td class="sub">{{ $c->formula() }}</td>
                            <td class="sub">{{ $c->is_mandatory ? 'A todos' : 'Por empleado' }}{{ $c->is_salary ? ' · salarial' : '' }}</td>
                            <td class="num">{{ $usos[$c->id] ?? 0 }}</td>
                            <td>@include('nomina._estado', ['estado' => $c->is_active ? 'activo' : 'terminado', 'texto' => $c->is_active ? 'Activo' : 'Inactivo'])</td>
                            <td class="act">
                                <span class="mt__acts">
                                    <x-mus.btn href="{{ route('payroll_concepts.edit', $c) }}" variant="ghost" :sm="true" class="mb--icon" title="Editar"><x-mus.icon name="pencil" :w="15" /></x-mus.btn>
                                    @unless ($c->is_system)
                                        <form method="POST" action="{{ route('payroll_concepts.estado', $c) }}" style="display:inline">
                                            @csrf @method('PATCH')
                                            <x-mus.btn type="submit" :sm="true">{{ $c->is_active ? 'Desactivar' : 'Activar' }}</x-mus.btn>
                                        </form>
                                    @endunless
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-mus.panel>
</x-mus.page>
