<x-mus.page title="Cupones" subtitle="Descuentos por código que se aplican en el POS" icon="tag">
    <x-slot name="actions">
        @can('cupones.crear')
            <x-mus.btn href="{{ route('coupons.create') }}" variant="primary" icon="plus">Nuevo cupón</x-mus.btn>
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($cupones->total() || $q !== '')
            <x-mus.toolbar :count="$cupones->total()" label="cupones" :action="route('coupons.index')" :q="$q" />
        @endif

        @if ($cupones->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descuento</th>
                            <th>Condiciones</th>
                            <th>Vigencia</th>
                            <th class="num">Usos</th>
                            <th class="num">Descontado</th>
                            <th>Estado</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cupones as $cupon)
                            <tr data-row>
                                <td>
                                    <a href="{{ route('coupons.show', $cupon) }}" class="cup-code">{{ $cupon->code }}</a>
                                    @if ($cupon->description)<span class="sub" style="display:block">{{ $cupon->description }}</span>@endif
                                </td>
                                <td><b>{{ $cupon->etiqueta }}</b></td>
                                <td class="sub">
                                    {{ $cupon->min_purchase ? 'Mín. $' . number_format((float) $cupon->min_purchase, 0, ',', '.') : 'Sin mínimo' }}
                                    @if ($cupon->max_discount) · tope ${{ number_format((float) $cupon->max_discount, 0, ',', '.') }} @endif
                                </td>
                                <td class="sub">
                                    {{ $cupon->starts_at?->format('d/m/Y') ?? 'Ya' }} → {{ $cupon->ends_at?->format('d/m/Y') ?? 'sin fin' }}
                                </td>
                                <td class="num">{{ $cupon->used_count }}{{ $cupon->max_uses ? ' / ' . $cupon->max_uses : '' }}</td>
                                <td class="num">${{ number_format((float) $cupon->descontado, 0, ',', '.') }}</td>
                                <td>@include('coupons._estado', ['cupon' => $cupon])</td>
                                <td class="act">
                                    <span class="mt__acts">
                                        @can('cupones.editar')
                                            <x-mus.btn href="{{ route('coupons.edit', $cupon) }}" variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                                <x-mus.icon name="pencil" :w="15" />
                                            </x-mus.btn>
                                        @endcan
                                        @can('cupones.eliminar')
                                            <x-mus.del :action="route('coupons.destroy', $cupon)" what="el cupón {{ $cupon->code }}" />
                                        @endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-mus.pagination :items="$cupones" label="cupones" />
        @else
            <x-mus.empty icon="tag" title="{{ $q !== '' ? 'Ningún cupón coincide' : 'Todavía no hay cupones' }}"
                         text="Crea un código como BIENVENIDA10 (10 %) o FIJO5000 ($5.000) y el cajero lo aplica en el POS.">
                @can('cupones.crear')
                    <x-slot name="action">
                        <x-mus.btn href="{{ route('coupons.create') }}" variant="primary" icon="plus">Nuevo cupón</x-mus.btn>
                    </x-slot>
                @endcan
            </x-mus.empty>
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .cup-code{ display:inline-block; padding:3px 9px; border:1px dashed var(--a-400); border-radius:7px;
                   font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12.5px; font-weight:700;
                   letter-spacing:.05em; color:var(--a-600); background:rgba(46,110,168,.06); }
    </style>
    @endpush
</x-mus.page>
