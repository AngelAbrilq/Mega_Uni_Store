@php use App\Support\Formato; @endphp

<x-mus.page :title="__('mus.ventas.una') . ' ' . $sale->number"
            subtitle="{{ Formato::enPalabras($sale->sold_at, 'D [de] MMMM [de] YYYY, HH:mm') }}"
            icon="money"
            :crumbs="[__('mus.entidades.ventas') => route('sales.index'), __('mus.acciones.ver_detalle') => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('sales.recibo', $sale) }}" icon="id" target="_blank" id="btnRecibo">{{ __('mus.ventas.recibo') }}</x-mus.btn>
        @can('ventas.devolver')
            @unless ($sale->anulada)
                <x-mus.btn href="{{ route('returns.create', $sale) }}" icon="back">{{ __('mus.ventas.devolucion') }}</x-mus.btn>
            @endunless
        @endcan
        @can('ventas.anular')
            @unless ($sale->anulada)
                <x-mus.btn variant="danger" icon="alert" id="btnAnular">{{ __('mus.ventas.anular') }}</x-mus.btn>
            @endunless
        @endcan
    </x-slot>

    @if ($sale->anulada)
        <div class="vanul" data-reveal>
            <span><x-mus.icon name="alert" :w="17" stroke-width="2.2" /></span>
            <div>
                <b>{{ __('mus.ventas.esta_anulada') }}</b>
                <p>{{ __('mus.ventas.la_anulo', [
                        'quien'  => $sale->voider->name ?? __('mus.vacio.alguien'),
                        'cuando' => Formato::enPalabras($sale->voided_at, 'D MMM YYYY, HH:mm'),
                   ]) }}</p>
                @if ($sale->void_reason)
                    <q>{{ $sale->void_reason }}</q>
                @endif
            </div>
        </div>
    @endif

    @if ($sale->returns->count())
        <div class="vdev" data-reveal>
            <span><x-mus.icon name="back" :w="16" stroke-width="2.2" /></span>
            <div>
                <b>{{ __('mus.ventas.con_devoluciones', ['n' => $sale->returns->count()]) }}</b>
                <p>{{ __('mus.ventas.se_regreso', ['monto' => Formato::moneda($sale->returns->sum('total'))]) }}</p>
                <ul>
                    @foreach ($sale->returns as $d)
                        <li>
                            <a href="{{ route('returns.show', $d) }}">{{ $d->number }}</a>
                            · {{ $d->returned_at?->format('d/m/Y') }}
                            · ${{ number_format((float) $d->total, 0, ',', '.') }}
                            · {{ \Illuminate\Support\Str::limit($d->reason, 40) }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="vgrid">
        <x-mus.panel :title="__('mus.ventas.productos_vendidos')" :pad="false"
                     :sub="$sale->items->count() . ' ' . __('mus.ventas.renglones')">
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>{{ __('mus.entidades.producto') }}</th>
                            <th class="num">{{ __('mus.ventas.cant') }}</th>
                            <th class="num">{{ __('mus.ventas.devuelto') }}</th>
                            <th class="num">{{ __('mus.campos.precio') }}</th>
                            <th class="num">{{ __('mus.campos.impuesto') }}</th>
                            <th class="num">{{ __('mus.campos.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->items as $item)
                            <tr>
                                <td>
                                    <b>{{ $item->name }}</b>
                                    @if ($item->sku)<span class="sub">{{ $item->sku }}</span>@endif
                                </td>
                                <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="num">
                                    @if ((float) $item->returned_quantity > 0)
                                        <span style="color:var(--bad)">
                                            {{ rtrim(rtrim(number_format((float) $item->returned_quantity, 2, ',', '.'), '0'), ',') }}
                                        </span>
                                    @else — @endif
                                </td>
                                <td class="num"><i class="moneda">$</i>{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                <td class="num">
                                    @if ((float) $item->tax_amount > 0)
                                        ${{ number_format((float) $item->tax_amount, 0, ',', '.') }}
                                        <span class="sub">{{ $item->tax_name }}</span>
                                    @else — @endif
                                </td>
                                <td class="num"><b><i class="moneda">$</i>{{ number_format((float) $item->total, 0, ',', '.') }}</b></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="vtot">
                <div><span>{{ __('mus.campos.subtotal') }}</span><b><i class="moneda">$</i>{{ number_format((float) $sale->subtotal, 0, ',', '.') }}</b></div>
                @if ((float) $sale->discount_total > 0)
                    <div><span>{{ __('mus.campos.descuentos') }}</span><b>−${{ number_format((float) $sale->discount_total, 0, ',', '.') }}</b></div>
                @endif
                <div><span>{{ __('mus.campos.impuestos') }}</span><b><i class="moneda">$</i>{{ number_format((float) $sale->tax_total, 0, ',', '.') }}</b></div>
                <div class="vtot__big"><span>{{ __('mus.campos.total') }}</span><b><i class="moneda">$</i>{{ number_format((float) $sale->total, 0, ',', '.') }}</b></div>
            </div>
        </x-mus.panel>

        <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1fr);align-content:start">
            <x-mus.panel :title="__('mus.ventas.como_se_pago')" :sub="$sale->payments->count() . ' ' . __('mus.ventas.medios')">
                <ul class="vpag">
                    @foreach ($sale->payments as $p)
                        <li>
                            <span>{{ $p->method_name }}</span>
                            <b><i class="moneda">$</i>{{ number_format((float) $p->amount, 0, ',', '.') }}</b>
                            @if ($p->reference)<em>{{ $p->reference }}</em>@endif
                        </li>
                    @endforeach
                </ul>
                <dl class="mdl" style="margin-top:10px">
                    <div><dt>{{ __('mus.pos.recibido') }}</dt><dd><i class="moneda">$</i>{{ number_format((float) $sale->paid_total, 0, ',', '.') }}</dd></div>
                    <div><dt>{{ __('mus.pos.cambio') }}</dt><dd><i class="moneda">$</i>{{ number_format((float) $sale->change_amount, 0, ',', '.') }}</dd></div>
                </dl>
            </x-mus.panel>

            <x-mus.panel :title="__('mus.ventas.datos')">
                <dl class="mdl">
                    <div>
                        <dt>{{ __('mus.campos.cliente') }}</dt>
                        <dd>
                            @if ($sale->customer)
                                <a href="{{ route('customers.show', $sale->customer) }}">{{ $sale->customer->full_name }}</a>
                            @else {{ __('mus.vacio.consumidor_final') }} @endif
                        </dd>
                    </div>
                    <div><dt>{{ __('mus.ventas.vendedor') }}</dt><dd>{{ $sale->user->name ?? '—' }}</dd></div>
                    <div>
                        <dt>{{ __('mus.ventas.turno_caja') }}</dt>
                        <dd>
                            @if ($sale->cashSession)
                                <a href="{{ route('cash.show', $sale->cashSession) }}">
                                    #{{ $sale->cashSession->id }}
                                </a>
                            @else {{ __('mus.vacio.sin_turno') }} @endif
                        </dd>
                    </div>
                    <div><dt>{{ __('mus.ventas.costo_vendido') }}</dt><dd><i class="moneda">$</i>{{ number_format((float) $sale->cost_total, 0, ',', '.') }}</dd></div>
                    <div>
                        <dt>{{ __('mus.ventas.utilidad') }}</dt>
                        <dd style="color:var(--ok);font-weight:700">
                            ${{ number_format((float) $sale->profit_total, 0, ',', '.') }}
                            <span style="color:var(--muted);font-weight:500">
                                ({{ number_format($sale->margin, 1) }}%)
                            </span>
                        </dd>
                    </div>
                    @if ($sale->notes)
                        <div><dt>{{ __('mus.ventas.nota') }}</dt><dd>{{ $sale->notes }}</dd></div>
                    @endif
                </dl>

                <x-slot name="foot">
                    <x-mus.btn href="{{ route('sales.index') }}" icon="back" :block="true">
                        {{ __('mus.ventas.volver_listado') }}
                    </x-mus.btn>
                </x-slot>
            </x-mus.panel>
        </div>
    </div>

    {{-- Formulario de anulación --}}
    @can('ventas.anular')
        @unless ($sale->anulada)
            <div class="vmodal" id="modalAnular" role="dialog" aria-modal="true">
                <form method="POST" action="{{ route('sales.anular', $sale) }}" class="vmodal__box">
                    @csrf
                    <h3>{{ __('mus.ventas.anular_titulo') }} {{ $sale->number }}</h3>
                    <p>{{ __('mus.ventas.anular_aviso') }}</p>
                    <label>
                        {{ __('mus.ventas.anular_motivo') }}
                        <textarea name="motivo" rows="3" required minlength="5"
                                  placeholder="{{ __('mus.ventas.anular_motivo_ph') }}"></textarea>
                    </label>
                    <div class="vmodal__foot">
                        <button type="button" class="mb mb--ghost" id="btnCancelarAnular">{{ __('mus.acciones.cancelar') }}</button>
                        <button type="submit" class="mb mb--danger">{{ __('mus.ventas.anular_si') }}</button>
                    </div>
                </form>
            </div>
        @endunless
    @endcan

    @push('styles')
    <style>
        .vgrid{ display:grid; gap:16px; grid-template-columns:minmax(0,1.55fr) minmax(0,1fr);
                align-items:start; }
        @media (max-width:980px){ .vgrid{ grid-template-columns:minmax(0,1fr); } }

        .vanul{ display:flex; gap:12px; align-items:flex-start; margin-bottom:16px;
                padding:14px 16px; border:1px solid rgba(150,80,79,.35); border-radius:12px;
                background:rgba(150,80,79,.06); }
        .vanul > span{ display:grid; place-items:center; width:32px; height:32px; flex:none;
                       border-radius:9px; background:var(--bad); color:#fff; }
        .vanul b{ display:block; font-size:13.6px; color:var(--ink); }
        .vanul p{ margin:3px 0 0; font-size:12.6px; color:var(--muted); line-height:1.5; }
        .vanul q{ display:block; margin-top:6px; font-size:12.6px; color:var(--ink-2); font-style:italic; }

        .vtot{ padding:13px 18px 16px; border-top:1px solid var(--line-2); background:var(--paper); }
        .vtot > div{ display:flex; justify-content:space-between; align-items:baseline;
                     font-size:12.8px; color:var(--muted); padding:3px 0; }
        .vtot > div b{ color:var(--ink-2); font-weight:600; font-variant-numeric:tabular-nums; }
        .vtot__big{ margin-top:7px; padding-top:9px !important; border-top:1px solid var(--line);
                    font-size:14px !important; color:var(--ink) !important; }
        .vtot__big b{ font-size:22px !important; font-weight:700 !important; letter-spacing:-.03em;
                      color:var(--a-600) !important; }

        .vdev{ display:flex; gap:12px; align-items:flex-start; margin-bottom:16px;
               padding:14px 16px; border:1px solid rgba(150,80,79,.28); border-radius:12px;
               background:rgba(150,80,79,.045); }
        .vdev > span{ display:grid; place-items:center; width:32px; height:32px; flex:none;
                      border-radius:9px; background:var(--bad); color:#fff; }
        .vdev b{ display:block; font-size:13.4px; color:var(--ink); }
        .vdev p{ margin:3px 0 0; font-size:12.5px; color:var(--muted); }
        .vdev ul{ margin:7px 0 0; padding-left:18px; font-size:12.3px; color:var(--muted); }
        .vdev li{ margin-bottom:3px; }
        .vdev a{ font-weight:600; }

        .vpag{ list-style:none; margin:0; padding:0; }
        .vpag li{ display:grid; grid-template-columns:1fr auto; gap:2px 10px;
                  padding:9px 0; border-bottom:1px solid var(--line-2); }
        .vpag li:last-child{ border-bottom:0; }
        .vpag span{ font-size:12.9px; color:var(--ink-2); }
        .vpag b{ font-size:13.4px; font-weight:700; color:var(--ink);
                 font-variant-numeric:tabular-nums; }
        .vpag em{ grid-column:1/-1; font-style:normal; font-size:11.3px; color:var(--muted-2); }

        .vmodal{ position:fixed; inset:0; z-index:120; display:grid; place-items:center; padding:20px;
                 background:rgba(7,13,20,.62); backdrop-filter:blur(4px);
                 opacity:0; pointer-events:none; transition:opacity .26s var(--e-soft); }
        .vmodal.is-on{ opacity:1; pointer-events:auto; }
        .vmodal__box{ width:min(430px,100%); padding:22px; background:var(--card); border-radius:15px;
                      box-shadow:0 40px 90px -40px rgba(0,0,0,.6);
                      transform:translateY(14px) scale(.98); transition:transform .3s var(--e-back); }
        .vmodal.is-on .vmodal__box{ transform:none; }
        .vmodal__box h3{ margin:0 0 8px; font-size:16px; font-weight:600; color:var(--ink); }
        .vmodal__box p{ margin:0 0 14px; font-size:12.9px; color:var(--muted); line-height:1.6; }
        .vmodal__box label{ display:block; font-size:12.4px; font-weight:600; color:var(--ink-2); }
        .vmodal__box textarea{ width:100%; margin-top:6px; padding:9px 11px; border:1px solid var(--line);
                               border-radius:9px; font:inherit; font-size:13px; color:var(--ink);
                               resize:vertical; }
        .vmodal__foot{ display:flex; gap:9px; justify-content:flex-end; margin-top:16px; }

        /* Aviso de ventana emergente bloqueada. */
        .rec-aviso{ display:flex; align-items:center; gap:12px; flex-wrap:wrap;
                    margin-bottom:14px; padding:12px 15px; border-radius:11px;
                    background:rgba(150,112,60,.07); border:1px solid rgba(150,112,60,.32);
                    font-size:13.2px; color:var(--ink-2); }
        .rec-aviso span{ flex:1 1 200px; min-width:0; }
    </style>
    @endpush

    @push('scripts')
    <script>
    /* ══════════════════════════════════════════════════════════════
       Al terminar una venta en el POS se llega con ?imprimir=1.

       Antes esto llamaba window.open() y ya. El problema es que el
       navegador bloquea las ventanas emergentes que no salen de un clic
       del usuario —y esta sale de una redirección después de un POST—,
       así que en el mostrador pasaba lo peor posible: el cajero cobraba,
       no salía nada, y no había manera de saber si el recibo se había
       generado o no.

       Ahora se intenta abrir, y si el navegador lo bloquea aparece un
       aviso con el botón para abrirlo a mano. Un clic de más es mucho
       mejor que un cliente esperando el papel.
       ══════════════════════════════════════════════════════════════ */
    (function () {
        var url = new URL(window.location.href);
        if (url.searchParams.get('imprimir') !== '1') return;

        var recibo = document.getElementById('btnRecibo');
        if (!recibo) return;

        // Se limpia el parámetro primero: si algo falla más abajo, al menos
        // recargar la página no vuelve a disparar todo esto.
        url.searchParams.delete('imprimir');
        window.history.replaceState({}, '', url.toString());

        var ventana = null;

        try {
            ventana = window.open(recibo.href + '?imprimir=1', 'musRecibo', 'width=430,height=780');
        } catch (e) {
            ventana = null;
        }

        if (ventana && ! ventana.closed) return;

        /* Bloqueada: se avisa sin asustar y con la salida a la mano. */
        var aviso = document.createElement('div');
        aviso.className = 'rec-aviso';
        aviso.innerHTML =
            '<span>El navegador bloqueó la ventana del recibo.</span>' +
            '<a class="mb mb--primary mb--sm" target="_blank" rel="noopener">Abrir el recibo</a>';
        aviso.querySelector('a').href = recibo.href;

        var destino = document.querySelector('.mp') || document.body;
        destino.parentNode.insertBefore(aviso, destino);
    })();

    (function () {
        var m = document.getElementById('modalAnular');
        var abrir = document.getElementById('btnAnular');
        var cerrar = document.getElementById('btnCancelarAnular');
        if (!m || !abrir) return;

        abrir.addEventListener('click', function () {
            m.classList.add('is-on');
            setTimeout(function () { var t = m.querySelector('textarea'); if (t) t.focus(); }, 240);
        });
        if (cerrar) cerrar.addEventListener('click', function () { m.classList.remove('is-on'); });
        m.addEventListener('click', function (e) { if (e.target === m) m.classList.remove('is-on'); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') m.classList.remove('is-on');
        });
    })();
    </script>
    @endpush
</x-mus.page>
