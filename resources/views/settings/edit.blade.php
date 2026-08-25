@php
    use Illuminate\Support\Facades\Storage;
    use App\Support\Formato;

    $logoUrl = ! empty($ajustes['negocio.logo'])
        ? Storage::url($ajustes['negocio.logo'])
        : null;

    /* Las pestañas, en orden. La clave es la que viaja en ?t= */
    $pestanas = [
        'negocio'    => ['icon' => 'tag',     'txt' => __('settings.tabs.negocio')],
        'regional'   => ['icon' => 'globe',   'txt' => __('settings.tabs.regional')],
        'recibo'     => ['icon' => 'receipt', 'txt' => __('settings.tabs.recibo')],
        'ventas'     => ['icon' => 'money',   'txt' => __('settings.tabs.ventas')],
        'inventario' => ['icon' => 'box',     'txt' => __('settings.tabs.inventario')],
        'apariencia' => ['icon' => 'eye',     'txt' => __('settings.tabs.apariencia')],
    ];

    $activa = array_key_exists($pestana, $pestanas) ? $pestana : 'negocio';

    /* Muestra de cómo quedan los números con lo que hay guardado ahora.
       Es lo que convierte «separador de miles» en algo que se entiende. */
    $muestra = Formato::moneda(1234567.5) . '  ·  ' . Formato::fechaHora(now());
@endphp

<x-mus.page :title="__('settings.titulo')" :subtitle="__('settings.subtitulo')" icon="gear">

    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')

        {{-- Recuerda en qué pestaña estaba el usuario para devolverlo ahí
             después de guardar. Sin esto, guardar desde «Recibo» lo deja
             mirando «Negocio» y parece que no pasó nada. --}}
        <input type="hidden" name="pestana" id="pestanaActual" value="{{ $activa }}">

        {{-- ══════════ Pestañas ══════════ --}}
        <div class="stabs" role="tablist" data-reveal>
            @foreach ($pestanas as $clave => $p)
                <button type="button" class="stabs__b {{ $activa === $clave ? 'is-on' : '' }}"
                        role="tab" data-tab="{{ $clave }}"
                        aria-selected="{{ $activa === $clave ? 'true' : 'false' }}">
                    <x-mus.icon :name="$p['icon']" :w="15" />
                    <span>{{ $p['txt'] }}</span>
                </button>
            @endforeach
        </div>

        {{-- ══════════ 1. Negocio ══════════ --}}
        <div class="spane" data-pane="negocio" @if ($activa !== 'negocio') hidden @endif>
            <x-mus.panel :title="__('settings.negocio.titulo')" :sub="__('settings.negocio.sub')">
                <div class="mf">
                    <div class="mf__grid">
                        <x-mus.field name="negocio_nombre" :label="__('settings.negocio.nombre')" :required="true"
                                     :value="$ajustes['negocio.nombre'] ?? ''" max="120" />

                        <x-mus.field name="negocio_lema" :label="__('settings.negocio.lema')"
                                     :value="$ajustes['negocio.lema'] ?? ''" max="120"
                                     :hint="__('settings.negocio.lema_hint')" />

                        <x-mus.field name="negocio_nit" :label="__('settings.negocio.nit')"
                                     :value="$ajustes['negocio.nit'] ?? ''" max="30"
                                     :hint="__('settings.negocio.nit_hint')" />

                        <x-mus.field name="negocio_telefono" :label="__('settings.negocio.telefono')"
                                     :value="$ajustes['negocio.telefono'] ?? ''" max="30" />

                        <x-mus.field name="negocio_correo" :label="__('settings.negocio.correo')" type="email"
                                     :value="$ajustes['negocio.correo'] ?? ''" max="120" />

                        <x-mus.field name="negocio_ciudad" :label="__('settings.negocio.ciudad')"
                                     :value="$ajustes['negocio.ciudad'] ?? ''" max="80" />

                        <x-mus.field name="negocio_direccion" :label="__('settings.negocio.direccion')" :wide="true"
                                     :value="$ajustes['negocio.direccion'] ?? ''" max="150" />

                        <x-mus.imagen name="negocio_logo" :label="__('settings.negocio.logo')"
                                      :actual="$logoUrl" :hint="__('settings.negocio.logo_hint')" />
                    </div>
                </div>
            </x-mus.panel>
        </div>

        {{-- ══════════ 2. Regional ══════════ --}}
        <div class="spane" data-pane="regional" @if ($activa !== 'regional') hidden @endif>
            <x-mus.panel :title="__('settings.regional.titulo')" :sub="__('settings.regional.sub')">
                <div class="mf">
                    <div class="mf__grid">
                        <x-mus.select name="regional_idioma" :label="__('settings.regional.idioma')" :required="true"
                                      :options="$listas['idiomas']"
                                      :value="$ajustes['regional.idioma'] ?? 'es'"
                                      :hint="__('settings.regional.idioma_hint')" />

                        <x-mus.select name="regional_zona_horaria" :label="__('settings.regional.zona_horaria')" :required="true"
                                      :options="$listas['zonas']"
                                      :value="$ajustes['regional.zona_horaria'] ?? 'America/Bogota'"
                                      :hint="__('settings.regional.zona_hint')" />

                        <x-mus.field name="regional_moneda_codigo" :label="__('settings.regional.moneda_codigo')" :required="true"
                                     :value="$ajustes['regional.moneda_codigo'] ?? 'COP'" max="3"
                                     :hint="__('settings.regional.moneda_codigo_hint')"
                                     style="text-transform:uppercase" />

                        <x-mus.field name="regional_moneda_simbolo" :label="__('settings.regional.moneda_simbolo')"
                                     :value="$ajustes['regional.moneda_simbolo'] ?? '$'" max="5"
                                     :hint="__('settings.regional.moneda_simbolo_hint')" />

                        <x-mus.select name="regional_simbolo_posicion" :label="__('settings.regional.simbolo_posicion')" :required="true"
                                      :options="[
                                          'antes'   => __('settings.regional.simbolo_antes'),
                                          'despues' => __('settings.regional.simbolo_despues'),
                                      ]"
                                      :value="$ajustes['regional.simbolo_posicion'] ?? 'antes'" />

                        <x-mus.field name="regional_decimales" :label="__('settings.regional.decimales')" type="number"
                                     :required="true" step="1" :value="$ajustes['regional.decimales'] ?? '0'"
                                     :hint="__('settings.regional.decimales_hint')" min="0" max="4" />

                        <x-mus.select name="regional_sep_miles" :label="__('settings.regional.sep_miles')" :required="true"
                                      :options="[
                                          'punto'   => __('settings.regional.punto'),
                                          'coma'    => __('settings.regional.coma'),
                                          'espacio' => __('settings.regional.espacio'),
                                          'ninguno' => __('settings.regional.ninguno'),
                                      ]"
                                      :value="$ajustes['regional.sep_miles'] ?? 'punto'" />

                        <x-mus.select name="regional_sep_decimal" :label="__('settings.regional.sep_decimal')" :required="true"
                                      :options="[
                                          'coma'  => __('settings.regional.coma'),
                                          'punto' => __('settings.regional.punto'),
                                      ]"
                                      :value="$ajustes['regional.sep_decimal'] ?? 'coma'" />

                        <x-mus.select name="regional_formato_fecha" :label="__('settings.regional.formato_fecha')" :required="true"
                                      :options="$listas['fechas']"
                                      :value="$ajustes['regional.formato_fecha'] ?? 'dmy_slash'" :wide="true" />
                    </div>

                    {{-- La muestra usa lo que hay GUARDADO, no lo que está en
                         los campos: cambiar el desplegable no la actualiza
                         hasta guardar, y eso es honesto — muestra el estado
                         real del sistema, no una promesa. --}}
                    <div class="sdemo">
                        <span class="sdemo__lab">{{ __('settings.regional.ejemplo') }}</span>
                        <b class="sdemo__val">{{ $muestra }}</b>
                    </div>
                </div>
            </x-mus.panel>
        </div>

        {{-- ══════════ 3. Recibo ══════════ --}}
        <div class="spane" data-pane="recibo" @if ($activa !== 'recibo') hidden @endif>
            <x-mus.panel :title="__('settings.recibo.titulo')" :sub="__('settings.recibo.sub')">
                <div class="mf">
                    <div class="mf__grid">
                        <x-mus.select name="recibo_formato" :label="__('settings.recibo.formato')" :required="true"
                                      :options="[
                                          '80' => __('settings.recibo.f80'),
                                          '58' => __('settings.recibo.f58'),
                                          'a4' => __('settings.recibo.fa4'),
                                      ]"
                                      :value="$ajustes['recibo.formato'] ?? '80'"
                                      :hint="__('settings.recibo.formato_hint')" :wide="true" />

                        <x-mus.field name="recibo_mensaje" :label="__('settings.recibo.mensaje')"
                                     :value="$ajustes['recibo.mensaje'] ?? ''" max="150" :wide="true"
                                     :hint="__('settings.recibo.mensaje_hint')" />

                        <x-mus.textarea name="recibo_pie" :label="__('settings.recibo.pie')" :rows="2"
                                        :value="$ajustes['recibo.pie'] ?? ''"
                                        :hint="__('settings.recibo.pie_hint')" />

                        <x-mus.field name="recibo_copias" :label="__('settings.recibo.copias')" type="number"
                                     :required="true" step="1" min="1" max="5"
                                     :value="$ajustes['recibo.copias'] ?? '1'"
                                     :hint="__('settings.recibo.copias_hint')" />
                    </div>

                    <div class="sopts">
                        <x-mus.toggle name="recibo_mostrar_logo" :label="__('settings.recibo.mostrar_logo')"
                                      :hint="__('settings.recibo.mostrar_logo_hint')"
                                      :checked="($ajustes['recibo.mostrar_logo'] ?? '1') === '1'" />

                        <x-mus.toggle name="recibo_mostrar_qr" :label="__('settings.recibo.mostrar_qr')"
                                      :hint="__('settings.recibo.mostrar_qr_hint')"
                                      :checked="($ajustes['recibo.mostrar_qr'] ?? '0') === '1'" />

                        <x-mus.toggle name="recibo_mostrar_impuestos" :label="__('settings.recibo.mostrar_impuestos')"
                                      :hint="__('settings.recibo.mostrar_impuestos_hint')"
                                      :checked="($ajustes['recibo.mostrar_impuestos'] ?? '0') === '1'" />

                        <x-mus.toggle name="recibo_mostrar_cajero" :label="__('settings.recibo.mostrar_cajero')"
                                      hint="" :checked="($ajustes['recibo.mostrar_cajero'] ?? '1') === '1'" />

                        <x-mus.toggle name="recibo_mostrar_ahorro" :label="__('settings.recibo.mostrar_ahorro')"
                                      :hint="__('settings.recibo.mostrar_ahorro_hint')"
                                      :checked="($ajustes['recibo.mostrar_ahorro'] ?? '1') === '1'" />

                        <x-mus.toggle name="recibo_auto" :label="__('settings.recibo.auto')"
                                      :hint="__('settings.recibo.auto_hint')"
                                      :checked="($ajustes['recibo.auto'] ?? '1') === '1'" />
                    </div>
                </div>
            </x-mus.panel>
        </div>

        {{-- ══════════ 4. Ventas ══════════ --}}
        <div class="spane" data-pane="ventas" @if ($activa !== 'ventas') hidden @endif>
            <x-mus.panel :title="__('settings.ventas.titulo')" :sub="__('settings.ventas.sub')">
                <div class="mf">
                    <div class="mf__grid">
                        <x-mus.field name="venta_descuento_max" :label="__('settings.ventas.descuento_max')" type="number"
                                     :required="true" step="1" min="0" max="100"
                                     :value="$ajustes['venta.descuento_max'] ?? '100'"
                                     :hint="__('settings.ventas.descuento_max_hint')" />

                        <x-mus.select name="venta_redondeo" :label="__('settings.ventas.redondeo')" :required="true"
                                      :options="[
                                          '0'   => __('settings.ventas.r_no'),
                                          '50'  => __('settings.ventas.r_50'),
                                          '100' => __('settings.ventas.r_100'),
                                      ]"
                                      :value="$ajustes['venta.redondeo'] ?? '0'"
                                      :hint="__('settings.ventas.redondeo_hint')" />
                    </div>

                    <div class="sopts">
                        <x-mus.toggle name="venta_stock_negativo" :label="__('settings.ventas.stock_negativo')"
                                      :hint="__('settings.ventas.stock_negativo_hint')"
                                      :checked="($ajustes['venta.stock_negativo'] ?? '0') === '1'" />

                        <x-mus.toggle name="venta_cliente_obligatorio" :label="__('settings.ventas.cliente_obligatorio')"
                                      :hint="__('settings.ventas.cliente_obligatorio_hint')"
                                      :checked="($ajustes['venta.cliente_obligatorio'] ?? '0') === '1'" />
                    </div>
                </div>
            </x-mus.panel>
        </div>

        {{-- ══════════ 5. Inventario ══════════ --}}
        <div class="spane" data-pane="inventario" @if ($activa !== 'inventario') hidden @endif>
            <x-mus.panel :title="__('settings.inventario.titulo')" :sub="__('settings.inventario.sub')">
                <div class="mf">
                    <div class="mf__grid">
                        <x-mus.field name="inventario_dias_rotacion" :label="__('settings.inventario.dias_rotacion')"
                                     type="number" :required="true" step="1" min="7" max="365"
                                     :value="$ajustes['inventario.dias_rotacion'] ?? '30'"
                                     :hint="__('settings.inventario.dias_rotacion_hint')" />

                        <x-mus.select name="inventario_costo_metodo" :label="__('settings.inventario.costo_metodo')" :required="true"
                                      :options="[
                                          'promedio' => __('settings.inventario.promedio'),
                                          'ultimo'   => __('settings.inventario.ultimo'),
                                      ]"
                                      :value="$ajustes['inventario.costo_metodo'] ?? 'promedio'"
                                      :hint="__('settings.inventario.costo_metodo_hint')" />
                    </div>

                    <div class="sopts">
                        <x-mus.toggle name="inventario_alerta_activa" :label="__('settings.inventario.alerta_activa')"
                                      :hint="__('settings.inventario.alerta_activa_hint')"
                                      :checked="($ajustes['inventario.alerta_activa'] ?? '1') === '1'" />
                    </div>
                </div>
            </x-mus.panel>
        </div>

        {{-- ══════════ 6. Apariencia ══════════ --}}
        <div class="spane" data-pane="apariencia" @if ($activa !== 'apariencia') hidden @endif>
            <x-mus.panel :title="__('settings.apariencia.titulo')" :sub="__('settings.apariencia.sub')">
                <div class="mf">
                    <div class="mf__grid">
                        <x-mus.select name="apariencia_acento" :label="__('settings.apariencia.acento')" :required="true"
                                      :options="[
                                          'azul'     => __('settings.apariencia.azul'),
                                          'verde'    => __('settings.apariencia.verde'),
                                          'violeta'  => __('settings.apariencia.violeta'),
                                          'ambar'    => __('settings.apariencia.ambar'),
                                          'grafito'  => __('settings.apariencia.grafito'),
                                      ]"
                                      :value="$ajustes['apariencia.acento'] ?? 'azul'"
                                      :hint="__('settings.apariencia.acento_hint')" />

                        <x-mus.select name="apariencia_densidad" :label="__('settings.apariencia.densidad')" :required="true"
                                      :options="[
                                          'comoda'   => __('settings.apariencia.comoda'),
                                          'compacta' => __('settings.apariencia.compacta'),
                                      ]"
                                      :value="$ajustes['apariencia.densidad'] ?? 'comoda'"
                                      :hint="__('settings.apariencia.densidad_hint')" />
                    </div>

                    <div class="sopts">
                        <x-mus.toggle name="apariencia_animaciones" :label="__('settings.apariencia.animaciones')"
                                      :hint="__('settings.apariencia.animaciones_hint')"
                                      :checked="($ajustes['apariencia.animaciones'] ?? '1') === '1'" />
                    </div>
                </div>
            </x-mus.panel>
        </div>

        {{-- ══════════ Barra de guardado ══════════
             Va pegada abajo y por fuera de las pestañas: guarda TODAS, no
             solo la que se está viendo. --}}
        <div class="sbar">
            <x-mus.btn href="{{ route('dashboard') }}" icon="back">{{ __('mus.acciones.volver_panel') }}</x-mus.btn>
            <span class="sbar__sp"></span>
            <x-mus.btn type="submit" variant="primary" icon="save">{{ __('settings.guardar') }}</x-mus.btn>
        </div>
    </form>

    @push('styles')
    <style>
        .stabs{ display:flex; gap:4px; margin-bottom:16px; padding:4px;
                background:var(--paper); border:1px solid var(--line); border-radius:12px;
                overflow-x:auto; scrollbar-width:none; }
        .stabs::-webkit-scrollbar{ display:none; }
        .stabs__b{ display:inline-flex; align-items:center; gap:8px; flex:none;
                   padding:9px 15px; border:0; border-radius:9px; background:transparent;
                   font:inherit; font-size:13px; font-weight:600; color:var(--muted);
                   cursor:pointer; white-space:nowrap;
                   transition:background .18s var(--e-soft), color .18s var(--e-soft); }
        .stabs__b:hover{ color:var(--ink-2); background:var(--line-2); }
        .stabs__b.is-on{ background:var(--card); color:var(--a-600);
                         box-shadow:0 1px 2px rgba(16,24,37,.07), 0 6px 16px -12px rgba(16,24,37,.4); }
        .stabs__b svg{ flex:none; }

        /* Cada interruptor con su propio renglón y una línea que lo separa
           del siguiente: apilados sin aire, la pista de uno se lee como si
           fuera el título del de abajo. */
        .sopts{ display:grid; margin-top:10px; }
        .sopts > div{ padding:12px 0; border-top:1px solid var(--line-2); }
        .sopts > div:first-child{ border-top:0; padding-top:4px; }
        .sopts > div:last-child{ padding-bottom:2px; }

        .sdemo{ display:flex; align-items:center; gap:12px; flex-wrap:wrap;
                margin-top:14px; padding:12px 15px; border-radius:11px;
                background:var(--paper); border:1px dashed var(--line); }
        .sdemo__lab{ font-size:11.4px; letter-spacing:.08em; text-transform:uppercase;
                     color:var(--muted); }
        .sdemo__val{ font-size:15px; font-weight:700; color:var(--ink);
                     font-variant-numeric:tabular-nums; }

        .sbar{ display:flex; align-items:center; gap:10px; flex-wrap:wrap;
               position:sticky; bottom:0; z-index:5; margin-top:16px;
               padding:13px 16px; border:1px solid var(--line); border-radius:12px;
               background:var(--card);
               box-shadow:0 -8px 26px -22px rgba(16,24,37,.55); }
        .sbar__sp{ flex:1 1 auto; }

        @media (max-width:760px){
            .sbar{ padding:11px 12px; }
            .sbar .mb{ flex:1 1 auto; justify-content:center; }
            .sbar__sp{ display:none; }
        }
    </style>
    @endpush

    @push('scripts')
    <script>
    /* ══════════════════════════════════════════════════════════════
       Pestañas de configuración.

       No recargan la página: los seis paneles ya están en el HTML y solo
       se esconde el que no toca. Así el formulario sigue siendo UNO —si
       cada pestaña fuera una petición, cambiar algo en dos pestañas
       distintas antes de guardar perdería lo de la primera.

       La dirección sí se actualiza (?t=recibo) para que al recargar o al
       volver de guardar el usuario caiga donde estaba.
       ══════════════════════════════════════════════════════════════ */
    (function () {
        'use strict';

        var tabs   = document.querySelectorAll('.stabs__b');
        var panes  = document.querySelectorAll('.spane');
        var oculto = document.getElementById('pestanaActual');

        if (!tabs.length) return;

        function mostrar(clave, empujarUrl) {
            Array.prototype.forEach.call(tabs, function (t) {
                var on = t.getAttribute('data-tab') === clave;
                t.classList.toggle('is-on', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });

            Array.prototype.forEach.call(panes, function (p) {
                p.hidden = p.getAttribute('data-pane') !== clave;
            });

            if (oculto) oculto.value = clave;

            if (empujarUrl && window.history && window.history.replaceState) {
                var u = new URL(window.location.href);
                u.searchParams.set('t', clave);
                window.history.replaceState({}, '', u);
            }

            // Los paneles nuevos entran con data-reveal en opacidad 0 y el
            // observador ya pasó: se les marca visto a mano o quedan en blanco.
            var vis = document.querySelector('[data-pane="' + clave + '"]');
            if (vis) {
                Array.prototype.forEach.call(vis.querySelectorAll('[data-reveal]'), function (el) {
                    el.classList.add('is-seen');
                });
            }
        }

        Array.prototype.forEach.call(tabs, function (t) {
            t.addEventListener('click', function () {
                mostrar(t.getAttribute('data-tab'), true);
            });
        });

        /* Si el servidor devolvió errores de validación, se salta a la
           primera pestaña que los tenga: de nada sirve marcar en rojo un
           campo que está escondido detrás de otra pestaña. */
        var primerError = document.querySelector('.mfld--err');
        if (primerError) {
            var pane = primerError.closest('.spane');
            if (pane) mostrar(pane.getAttribute('data-pane'), true);
        }
    })();
    </script>
    @endpush
</x-mus.page>
