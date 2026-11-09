@php use App\Support\Formato; @endphp

<x-mus.page title="Cajas registradoras" subtitle="Las cajas físicas de este local" icon="money"
            :crumbs="['Caja' => route('cash.index'), 'Cajas registradoras' => null]">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('cash_registers.create') }}" variant="primary" icon="plus"
                   data-modal="Nueva caja" data-modal-ancho="620">
            Nueva caja
        </x-mus.btn>
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($cajas->count())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Caja</th>
                            <th>Estado</th>
                            <th>Turno abierto</th>
                            <th class="num">Turnos</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cajas as $caja)
                            @php $turno = $caja->sessions->first(); @endphp
                            <tr data-row>
                                <td>@if ($caja->code)<x-mus.badge tone="info">{{ $caja->code }}</x-mus.badge>@else — @endif</td>
                                <td>
                                    <b>{{ $caja->name }}</b>
                                    @if ($caja->description)<span class="sub" style="display:block">{{ $caja->description }}</span>@endif
                                </td>
                                <td>
                                    <x-mus.badge :tone="$caja->is_active ? 'ok' : 'off'" :dot="true">
                                        {{ $caja->is_active ? 'Activa' : 'Inactiva' }}
                                    </x-mus.badge>
                                </td>
                                <td>
                                    @if ($turno)
                                        <a href="{{ route('cash.show', $turno) }}">#{{ $turno->id }} · {{ $turno->user?->nombre_completo }}</a>
                                        <span class="sub" style="display:block">desde {{ Formato::haceCuanto($turno->opened_at, true) }}</span>
                                    @else
                                        <span style="color:var(--muted-2)">Libre</span>
                                    @endif
                                </td>
                                <td class="num"><x-mus.badge :tone="$caja->sessions_count ? 'info' : 'off'">{{ $caja->sessions_count }}</x-mus.badge></td>
                                <td class="act">
                                    <span class="mt__acts">
                                        <x-mus.btn href="{{ route('cash_registers.edit', $caja) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                            <x-mus.icon name="pencil" :w="15" />
                                        </x-mus.btn>
                                        <x-mus.del :action="route('cash_registers.destroy', $caja)"
                                                   what="la caja {{ $caja->etiqueta }} (si tiene historial, solo se desactiva)" />
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-mus.empty icon="money" title="Este local no tiene cajas definidas"
                         text="Sin cajas, cada turno es solo de la persona que lo abre. Crea cajas si el local tiene más de un punto de pago y quieres saber en cuál está el dinero.">
                <x-slot name="action">
                    <x-mus.btn href="{{ route('cash_registers.create') }}" variant="primary" icon="plus"
                               data-modal="Nueva caja" data-modal-ancho="620">
                        Nueva caja
                    </x-mus.btn>
                </x-slot>
            </x-mus.empty>
        @endif
    </x-mus.panel>
</x-mus.page>
