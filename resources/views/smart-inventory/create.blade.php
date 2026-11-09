{{--
    Smart Inventory Entry — foto de la factura → tabla de validación → compra en borrador.

    Seguridad: todo texto que viene de la IA (nombres, observaciones) se
    pinta con textContent, nunca con innerHTML: una factura con «<script>»
    impreso no debe ejecutar nada. Las cifras de esta pantalla son una vista
    previa; al confirmar, el backend las vuelve a calcular desde cero.
--}}
<x-mus.page title="Leer factura con IA" subtitle="Toma la foto, revisa los valores y confirma la compra" icon="receipt"
            :crumbs="['Compras' => route('purchases.index'), 'Factura con IA' => null]">

    {{-- ───────────── Paso 1 · Captura ───────────── --}}
    <x-mus.panel title="1. Foto de la factura" sub="Completa, con buena luz, sin sombras ni reflejos">
        <div class="si-cap">
            <label class="si-drop" for="siFoto">
                <input type="file" id="siFoto" accept="image/jpeg,image/png,image/webp" capture="environment">
                <img id="siPreview" alt="Vista previa de la factura" hidden>
                <span class="si-drop__msg" id="siDropMsg">
                    <x-mus.icon name="receipt" :w="30" stroke-width="1.5" />
                    <b>Tomar foto o elegir imagen</b>
                    <small>JPG, PNG o WEBP · máx. 8 MB</small>
                </span>
            </label>

            <div class="si-cap__side">
                <div>
                    <label class="mfld__lab" for="siMarkup">Ganancia sugerida sobre el costo (%)</label>
                    <input type="number" id="siMarkup" min="0" max="1000" step="0.5" value="{{ $markup }}" class="si-in">
                    <small class="si-hint">Solo se usa cuando ni la factura ni el producto traen precio de venta.</small>
                </div>

                <x-mus.btn variant="primary" icon="search" id="siLeer" :block="true" disabled>Leer factura</x-mus.btn>
                <div class="si-estado" id="siEstado" role="status" aria-live="polite"></div>
            </div>
        </div>
    </x-mus.panel>

    {{-- ───────────── Paso 2 · Validación ───────────── --}}
    <div id="siResultado" hidden>
        <x-mus.panel title="2. Datos de la compra" sub="Verifica el proveedor y el número de factura" :reveal="false">
            <div class="mf"><div class="mf__grid">
                <div>
                    <label class="mfld__lab" for="siProveedor">Proveedor <span class="mfld__req">*</span></label>
                    <select id="siProveedor" class="si-in" required>
                        <option value="">Elige un proveedor</option>
                        @foreach ($proveedores as $prov)
                            <option value="{{ $prov->id }}">{{ $prov->name }}{{ $prov->tax_id ? ' · ' . $prov->tax_id : '' }}</option>
                        @endforeach
                    </select>
                    <small class="si-hint" id="siProvLeido"></small>
                </div>
                <div>
                    <label class="mfld__lab" for="siFactura">Número de factura</label>
                    <input type="text" id="siFactura" maxlength="50" class="si-in">
                </div>
                <div>
                    <label class="mfld__lab" for="siFecha">Fecha</label>
                    <input type="date" id="siFecha" max="{{ now()->format('Y-m-d') }}" class="si-in">
                </div>
                <div id="siTrmBox" hidden>
                    <label class="mfld__lab" for="siTrm">TRM (pesos por <span id="siMonedaCod">USD</span>) <span class="mfld__req">*</span></label>
                    <input type="number" id="siTrm" min="0" step="0.01" inputmode="decimal" class="si-in" placeholder="Ej. 3950">
                    <small class="si-hint">Los costos de la factura se convierten a pesos con este valor. Queda registrado en la compra.</small>
                </div>
            </div></div>
            <div id="siAvisos" class="si-avisos"></div>
        </x-mus.panel>

        <x-mus.panel title="3. Productos leídos" sub="Corrige lo que esté mal: los cálculos se actualizan al instante" :pad="false" :reveal="false">
            <x-slot name="tools">
                <div class="si-kpi" id="siKpi"></div>
            </x-slot>

            <div class="mt-wrap si-scroll">
                <table class="mt" id="siTabla">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Producto</th>
                            <th class="num">Cajas</th>
                            <th class="num">Und/caja</th>
                            <th class="num" id="siThCosto">Costo total</th>
                            <th class="num">Costo und</th>
                            <th class="num">Venta und</th>
                            <th class="num">Venta caja</th>
                            <th class="num">Ganancia caja</th>
                            <th class="num">Ganancia und</th>
                            <th class="num" title="Ganancia de todas las unidades del renglón">Ganancia total</th>
                            <th class="num" title="Ganancia ÷ precio de venta">Margen s/venta</th>
                            <th class="num">IVA %</th>
                            <th>Act. precio</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody id="siCuerpo"></tbody>
                </table>
            </div>

            <div class="si-tot" id="siTot"></div>

            <x-slot name="foot">
                <x-mus.btn icon="back" id="siOtra">Otra foto</x-mus.btn>
                <x-mus.btn variant="primary" icon="save" id="siConfirmar" disabled>Crear compra en borrador</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </div>

    {{-- Alta rápida de producto (renglones que no están en el catálogo). --}}
    <dialog id="siDlg" class="si-dlg" aria-labelledby="siDlgTit">
        <form method="dialog" id="siDlgForm" novalidate>
            <h3 id="siDlgTit">Crear producto</h3>
            <p class="si-hint">Nace con stock 0: sus unidades entran al recibir la compra.</p>
            <label class="mfld__lab" for="siNpNombre">Nombre <span class="mfld__req">*</span></label>
            <input id="siNpNombre" class="si-in" maxlength="200" required>
            <div class="si-dlg__row">
                <div><label class="mfld__lab" for="siNpSku">SKU</label><input id="siNpSku" class="si-in" maxlength="50"></div>
                <div><label class="mfld__lab" for="siNpBarra">Código de barras</label><input id="siNpBarra" class="si-in" maxlength="50"></div>
            </div>
            <div class="si-dlg__row">
                <div><label class="mfld__lab" for="siNpPrecio">Precio de venta (COP) <span class="mfld__req">*</span></label><input id="siNpPrecio" class="si-in" type="number" min="0" step="0.01" required></div>
                <div><label class="mfld__lab" for="siNpCosto">Costo unitario (COP)</label><input id="siNpCosto" class="si-in" type="number" min="0" step="0.01"></div>
            </div>
            <p class="si-dlg__err" id="siNpErr" role="alert"></p>
            <div class="si-dlg__foot">
                <x-mus.btn value="cancel" type="submit" formnovalidate>Cancelar</x-mus.btn>
                <x-mus.btn variant="primary" icon="plus" id="siNpCrear">Crear y asociar</x-mus.btn>
            </div>
        </form>
    </dialog>

    {{-- Plantilla del selector de producto: se clona por renglón. --}}
    <template id="siProdTpl">
        <select class="si-in si-prod" aria-label="Producto del catálogo">
            <option value="">— Asociar a un producto —</option>
            @foreach ($productos as $p)
                <option value="{{ $p['id'] }}" data-precio="{{ $p['precio'] }}" data-costo="{{ $p['costo'] }}">{{ $p['sku'] ? $p['sku'] . ' · ' : '' }}{{ $p['nombre'] }}</option>
            @endforeach
        </select>
    </template>

<style>
    .si-cap{ display:grid; grid-template-columns:minmax(0,1.4fr) minmax(0,1fr); gap:18px; align-items:start; }
    .si-drop{ position:relative; display:grid; place-items:center; min-height:240px; border:2px dashed var(--line);
              border-radius:12px; background:var(--paper); cursor:pointer; overflow:hidden; transition:border-color .2s; }
    .si-drop:hover, .si-drop:focus-within{ border-color:var(--a-400); }
    .si-drop input{ position:absolute; inset:0; opacity:0; cursor:pointer; }
    .si-drop img{ display:block; max-width:100%; max-height:420px; object-fit:contain; }
    .si-drop__msg{ display:grid; justify-items:center; gap:6px; color:var(--muted); text-align:center; padding:20px; pointer-events:none; }
    .si-drop__msg[hidden]{ display:none; }
    .si-drop__msg b{ color:var(--ink); font-size:14px; }
    .si-cap__side{ display:grid; gap:14px; }
    .si-in{ width:100%; height:36px; padding:0 10px; border:1px solid var(--line); border-radius:8px; background:#fff;
            font:inherit; font-size:13px; color:var(--ink); outline:0; }
    .si-in:focus{ border-color:var(--a-400); box-shadow:0 0 0 3px rgba(46,110,168,.12); }
    .si-in[aria-invalid="true"]{ border-color:var(--bad); }
    .si-hint{ display:block; margin-top:4px; font-size:11.5px; color:var(--muted-2); }
    .si-estado{ font-size:12.8px; color:var(--muted); min-height:18px; }
    .si-estado.is-error{ color:var(--bad); }
    .si-estado.is-busy::before{ content:''; display:inline-block; width:12px; height:12px; margin-right:7px; vertical-align:-1px;
              border:2px solid var(--line); border-top-color:var(--a-500); border-radius:50%; animation:si-spin .8s linear infinite; }
    @keyframes si-spin{ to{ transform:rotate(360deg); } }

    .si-avisos{ display:grid; gap:8px; padding:0 18px 16px; }
    .si-avisos:empty{ display:none; }
    .si-aviso{ padding:9px 12px; border-radius:9px; font-size:12.7px; line-height:1.45; }
    .si-aviso--warn{ background:#FAF5EC; color:#7C5C2E; }
    .si-aviso--bad{ background:#F6F1F1; color:#8A5150; }
    .si-aviso--ok{ background:#EDF5F0; color:#31684D; }
    .si-aviso--info{ background:#EDF3F9; color:#215480; }

    .si-kpi{ display:flex; gap:6px; flex-wrap:wrap; }
    .si-scroll{ overflow-x:auto; }
    #siTabla td{ vertical-align:top; }
    #siTabla td.num input{ width:96px; height:32px; text-align:right; font-variant-numeric:tabular-nums; }
    #siTabla .si-prod{ min-width:220px; margin-top:6px; height:32px; font-size:12.5px; }
    .si-leido{ display:block; font-weight:600; color:var(--ink); }
    .si-meta{ display:block; font-size:11.4px; color:var(--muted-2); margin-top:2px; }
    .si-alertas{ display:grid; gap:2px; margin-top:6px; font-size:11.4px; text-align:left; }
    .si-alertas:empty{ display:none; }
    .si-alertas span{ color:#7C5C2E; }
    .si-alertas span.is-bad{ color:var(--bad); }
    .si-calc{ font-variant-numeric:tabular-nums; color:var(--ink-2); white-space:nowrap; }
    .si-neg{ color:var(--bad) !important; }
    .si-quitar{ border:0; background:transparent; cursor:pointer; color:var(--muted-2); font-size:18px; line-height:1; padding:2px 6px; }
    .si-quitar:hover{ color:var(--bad); }
    tr.is-removed{ opacity:.4; }
    tr.is-removed input, tr.is-removed select{ pointer-events:none; }

    .si-tot{ display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; padding:14px 18px; border-top:1px solid var(--line-2); background:var(--paper); }
    .si-tot div{ display:grid; gap:2px; }
    .si-tot span{ font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--muted-2); }
    .si-tot b{ font-size:18px; font-variant-numeric:tabular-nums; color:var(--ink); }

    .si-crear{ margin-top:6px; border:1px dashed var(--a-400); background:transparent; color:var(--a-600); border-radius:7px;
               padding:5px 9px; font:inherit; font-size:12px; cursor:pointer; }
    .si-crear:hover{ background:var(--paper); }
    .si-dlg{ border:0; border-radius:14px; padding:22px; width:min(520px, calc(100vw - 32px)); box-shadow:0 20px 60px rgba(0,0,0,.25); }
    .si-dlg::backdrop{ background:rgba(15,23,42,.45); }
    .si-dlg h3{ margin:0 0 4px; font-size:16px; }
    .si-dlg form{ display:grid; gap:8px; }
    .si-dlg__row{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .si-dlg__err{ margin:0; color:var(--bad); font-size:12.5px; min-height:16px; }
    .si-dlg__foot{ display:flex; justify-content:flex-end; gap:8px; margin-top:6px; }

    @media (max-width:760px){
        .si-dlg__row{ grid-template-columns:1fr; }
        .si-cap{ grid-template-columns:1fr; }
        .si-drop{ min-height:200px; }
        .si-in{ height:42px; font-size:16px; }
        #siTabla td.num input{ width:130px; height:40px; font-size:16px; }
        #siTabla .si-prod{ min-width:0; width:100%; height:42px; font-size:16px; }
        .si-tot{ grid-template-columns:repeat(2,minmax(0,1fr)); padding:14px; }
    }
</style>

<script>
window.SI_CFG = {
    analizar:    @json(route('smart-inventory.analyze')),
    confirmar:   @json(route('smart-inventory.confirm')),
    crear:       @json(route('smart-inventory.products.store')),
    puedeCrear:  @json($puedeCrear),
    puedePrecio: @json($puedePrecio),
    hoy:         @json(now()->format('Y-m-d')),
};
</script>
<script>
/**
 * Smart Inventory Entry (vista). Módulo autocontenido, sin dependencias.
 * La aritmética replica InventoryMathService (centavos enteros) solo como
 * vista previa: el backend recalcula todo en /confirmar.
 */
(function () {
    'use strict';

    var CFG  = window.SI_CFG;
    var CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var $    = function (id) { return document.getElementById(id); };
    var MAX_PX = 2400;            // lado mayor de la foto tras reducirla en el navegador
    var MAX_BYTES = 8 * 1024 * 1024;
    var TIMEOUT_MS = 90000;
    var DUP = 'Este producto está en otro renglón: únelos en uno.';

    /** [mensaje, bloquea_confirmar] */
    var ALERTAS = {
        PRODUCTO_NO_ENCONTRADO:  ['No está en el catálogo: asócialo a un producto', true],
        BAJA_CONFIANZA:          ['La IA no está segura de este renglón', true],
        LECTURA_INCONSISTENTE:   ['Costo de caja × cajas no da el total leído', true],
        VENTA_BAJO_COSTO:        ['El precio de venta es menor que el costo', true],
        DATOS_INCOMPLETOS:       ['Faltan datos legibles: complétalos a mano', true],
        CANTIDAD_DEDUCIDA:       ['Cantidad deducida (no venía impresa): verifícala contra la mercancía', true],
        FALTA_TRM:               ['Falta la TRM para convertir el costo a pesos', true],
        COSTO_ATIPICO:           ['Costo muy distinto al del catálogo: revisa unidades por caja y valores', true],
        REDONDEO_COSTO_UNITARIO: ['El costo unitario se redondeó a centavos', false],
        CANTIDAD_FRACCIONARIA:   ['Cantidad con decimales', false],
        PRECIO_SUGERIDO:         ['Precio sugerido por margen (no venía en la factura)', false],
    };
    var INFORMATIVAS = ['CANTIDAD_FRACCIONARIA', 'PRECIO_SUGERIDO'];
    var ESTADOS = {
        ok:         ['ok', 'OK'],
        corregido:  ['info', 'Corregido'],
        revisar:    ['warn', 'Revisar'],
        incompleto: ['off', 'Incompleto'],
        editado:    ['info', 'Editado'],
    };

    var archivo = null;      // File listo para enviar
    var imagenRef = null;    // ruta del soporte guardado por /analizar
    var moneda = { codigo: 'COP', extranjera: false };
    function tasa() { return moneda.extranjera ? num($('siTrm').value) : 1; }
    var filas = [];          // estado de cada renglón

    // ───────────────────────── utilidades ─────────────────────────

    var fmt = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    function pesos(c) { return c === null || c === undefined ? '—' : '$' + fmt.format(c / 100); }
    function cents(v) { return Math.round(Number(v) * 100); }
    function num(v) { return v === '' || v === null || v === undefined || isNaN(Number(v)) ? null : Number(v); }
    function primero(a, b) { return a !== null && a !== undefined ? a : b; }

    /** Crea un elemento con texto seguro (textContent, nunca innerHTML). */
    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text !== undefined && text !== null) n.textContent = text;
        return n;
    }

    function badge(estado) {
        var e = ESTADOS[estado] || ESTADOS.revisar;
        return el('span', 'mbg mbg--' + e[0], e[1]);
    }

    function estadoMsg(texto, tipo) {
        var s = $('siEstado');
        s.textContent = texto || '';
        s.className = 'si-estado' + (tipo ? ' is-' + tipo : '');
    }

    function leerJson(r) {
        return r.json().catch(function () { return {}; }).then(function (j) { return { status: r.status, json: j }; });
    }

    /**
     * Réplica de InventoryMathService::calculate() para la vista previa.
     * @returns {object|null} null si los datos primarios no son válidos.
     */
    function calcular(cajas, upc, total, venta) {
        if (!(cajas > 0) || !(upc > 0) || total === null || total < 0) return null;
        var unidades = Math.round(cajas * upc * 1000) / 1000;
        var tc = cents(total);
        var r = {
            costoTotal: tc,
            costoCaja: Math.round(tc / cajas),
            costoUnd: Math.round(tc / unidades),
            ventaUnd: null, ventaCaja: null, ventaTotal: null,
            ganCaja: null, ganUnd: null, margen: null,
        };
        if (venta === null || venta < 0) return r;
        r.ventaUnd   = cents(venta);
        r.ventaCaja  = Math.round(r.ventaUnd * upc);
        r.ventaTotal = Math.round(r.ventaUnd * unidades);
        r.ganCaja    = r.ventaCaja - r.costoCaja;
        r.ganUnd     = r.ventaUnd - r.costoUnd;
        r.margen     = r.ventaTotal ? Math.round((r.ventaTotal - tc) / r.ventaTotal * 10000) / 100 : null;
        return r;
    }

    // ───────────────────────── paso 1: foto ─────────────────────────

    /** Reduce la foto a MAX_PX en JPEG 0.85: menos peso y menos costo de IA. */
    function reducir(file) {
        return new Promise(function (resolve) {
            if (!window.createImageBitmap) return resolve(file);
            createImageBitmap(file).then(function (bmp) {
                var escala = Math.min(1, MAX_PX / Math.max(bmp.width, bmp.height));
                if (escala === 1 && file.size < 3 * 1024 * 1024) return resolve(file);
                var c = document.createElement('canvas');
                c.width = Math.round(bmp.width * escala);
                c.height = Math.round(bmp.height * escala);
                c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
                c.toBlob(function (blob) {
                    resolve(blob ? new File([blob], 'factura.jpg', { type: 'image/jpeg' }) : file);
                }, 'image/jpeg', 0.85);
            }).catch(function () { resolve(file); }); // formato que el navegador no decodifica: va tal cual
        });
    }

    $('siFoto').addEventListener('change', function () {
        var f = this.files && this.files[0];
        if (!f) return;
        estadoMsg('Preparando imagen…', 'busy');
        $('siLeer').disabled = true;

        reducir(f).then(function (listo) {
            archivo = listo;
            var img = $('siPreview');
            if (img.src) URL.revokeObjectURL(img.src);
            img.src = URL.createObjectURL(listo);
            img.hidden = false;
            $('siDropMsg').hidden = true;

            if (listo.size > MAX_BYTES) {
                estadoMsg('La imagen supera 8 MB. Toma la foto con menor resolución.', 'error');
                return;
            }
            estadoMsg('Lista para leer (' + Math.round(listo.size / 1024) + ' KB).');
            $('siLeer').disabled = false;
        });
    });

    $('siLeer').addEventListener('click', function () {
        if (!archivo) return;
        var btn = this;
        var datos = new FormData();
        datos.append('imagen', archivo);
        if ($('siMarkup').value !== '') datos.append('markup_porcentaje', $('siMarkup').value);

        var mensajes = ['Enviando la foto…', 'La IA está leyendo la factura…', 'Revisando cantidades y costos…', 'Verificando los cálculos…'];
        var paso = 0;
        estadoMsg(mensajes[0], 'busy');
        var giro = setInterval(function () { paso = Math.min(paso + 1, mensajes.length - 1); estadoMsg(mensajes[paso], 'busy'); }, 4000);

        var ctrl = new AbortController();
        var limite = setTimeout(function () { ctrl.abort(); }, TIMEOUT_MS);
        btn.disabled = true;

        fetch(CFG.analizar, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: datos,
            signal: ctrl.signal,
        })
        .then(leerJson)
        .then(function (res) {
            if (res.status === 429) throw new Error('Demasiadas lecturas seguidas. Espera un minuto.');
            if (res.status === 419) throw new Error('La sesión expiró. Recarga la página.');
            if (!res.json.success) {
                var errs = res.json.errors ? Object.values(res.json.errors).flat().join(' ') : '';
                throw new Error(errs || res.json.message || 'No se pudo leer la factura.');
            }
            estadoMsg(res.json.message);
            pintarResultado(res.json.data);
        })
        .catch(function (e) {
            estadoMsg(e.name === 'AbortError' ? 'La lectura tardó demasiado. Inténtalo de nuevo.' : e.message, 'error');
        })
        .finally(function () { clearInterval(giro); clearTimeout(limite); btn.disabled = false; });
    });

    $('siOtra').addEventListener('click', function () {
        $('siResultado').hidden = true;
        $('siFoto').value = '';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        $('siFoto').click();
    });

    // ───────────────────────── paso 2: tabla ─────────────────────────

    function pintarResultado(d) {
        imagenRef = d.imagen_ref;
        filas = [];

        // Encabezado
        var leido = d.proveedor.leido || {};
        $('siProveedor').value = d.proveedor.coincide ? String(d.proveedor.coincide.id) : '';
        var textoLeido = [leido.nombre, leido.nit].filter(Boolean).join(' · ');
        $('siProvLeido').textContent = textoLeido
            ? 'Leído en la factura: ' + textoLeido + (d.proveedor.coincide ? '' : ' — no coincide con ningún proveedor registrado')
            : '';
        $('siFactura').value = leido.numero_factura || '';
        $('siFecha').value = /^\d{4}-\d{2}-\d{2}$/.test(leido.fecha || '') && leido.fecha <= CFG.hoy ? leido.fecha : CFG.hoy;

        // Moneda: si no es COP, se exige TRM y los costos se convierten.
        moneda = d.moneda || { codigo: 'COP', extranjera: false };
        $('siTrmBox').hidden = !moneda.extranjera;
        $('siMonedaCod').textContent = moneda.codigo;
        $('siTrm').value = '';
        $('siThCosto').textContent = 'Costo total' + (moneda.extranjera ? ' (' + moneda.codigo + ')' : '');

        // Avisos generales
        var av = $('siAvisos');
        av.textContent = '';
        if (moneda.extranjera) {
            // La IA puede equivocarse (p. ej. una plantilla con «€» por defecto):
            // el usuario corrige con un clic y la factura vuelve a pesos.
            var avMon = el('div', 'si-aviso si-aviso--bad', 'La factura parece estar en ' + moneda.codigo +
                (moneda.evidencia ? ' (' + moneda.evidencia + ')' : '') + '. Indica la TRM para convertir los costos a pesos. ');
            var btnCop = el('button', 'si-crear', 'No, está en pesos (COP)');
            btnCop.type = 'button';
            btnCop.addEventListener('click', function () {
                moneda = { codigo: 'COP', extranjera: false };
                $('siTrmBox').hidden = true;
                $('siThCosto').textContent = 'Costo total';
                avMon.remove();
                filas.forEach(refrescarFila);
                refrescarResumen();
            });
            avMon.appendChild(btnCop);
            av.appendChild(avMon);
        }
        if (d.fecha_alerta) av.appendChild(el('div', 'si-aviso si-aviso--warn', d.fecha_alerta));
        var cargos = d.cargos_no_mercancia || {};
        if (cargos.total > 0) av.appendChild(el('div', 'si-aviso si-aviso--info', 'Cargos que no son mercancía (no entran al inventario, sí al cuadre): ' + fmt.format(cargos.total) + (cargos.detalle ? ' — ' + cargos.detalle : '') + '.'));
        if (d.proveedor.duplicada) av.appendChild(el('div', 'si-aviso si-aviso--bad', 'Esta factura ya está registrada para este proveedor. Confirmarla duplicaría la compra.'));
        var cu = d.cuadre_factura || {};
        if (cu.cuadra === true)  av.appendChild(el('div', 'si-aviso si-aviso--ok', 'La suma de los renglones cuadra con el pie de la factura (' + pesos(cents(cu.suma_renglones)) + ').'));
        if (cu.cuadra === false) av.appendChild(el('div', 'si-aviso si-aviso--warn', cu.detalle + ' Diferencia: ' + pesos(cents(cu.diferencia)) + '.'));
        if (cu.cuadra === null && cu.detalle) av.appendChild(el('div', 'si-aviso si-aviso--info', cu.detalle));
        if (d.observaciones) av.appendChild(el('div', 'si-aviso si-aviso--info', 'Nota de la IA: ' + d.observaciones));

        // Renglones
        var cuerpo = $('siCuerpo');
        cuerpo.textContent = '';
        d.items.forEach(function (linea, i) {
            var c = linea.calculado || {};
            var ia = linea.ia || {};
            var sugerido = linea.producto_sugerido;
            var f = {
                i: i,
                estadoIA: linea.estado,
                editado: false,
                quitado: false,
                alertas: linea.alertas || [],
                discrepancias: linea.discrepancias || [],
                productId: sugerido ? String(sugerido.id) : '',
                precioActual: sugerido ? Number(sugerido.price) : null,
                costoCatalogo: sugerido ? Number(sugerido.cost) : null,
                costoRevisado: false,
                cajas: num(primero(c.cantidad_cajas, ia.cantidad_cajas)),
                upc:   num(primero(c.cantidad_unidades, ia.cantidad_unidades)),
                total: num(primero(c.costo_total_compra, ia.costo_total_compra)),
                venta: num(primero(c.precio_venta_unitario, primero(linea.precio_venta_sugerido, ia.precio_venta_unitario))),
                iva:   num(primero(linea.iva_porcentaje, ia.iva_porcentaje)),
                origenPrecio: linea.origen_precio_venta,
                ventaManual: false,
                error: null,
            };
            // Proponer actualizar el precio solo si difiere del que tiene el catálogo.
            f.actPrecio = CFG.puedePrecio && f.venta !== null && f.precioActual !== null && cents(f.venta) !== cents(f.precioActual);
            filas.push(f);
            cuerpo.appendChild(crearFila(f, linea.producto || ia.producto, linea.presentacion_caja || ia.presentacion_caja, linea.codigo || ia.codigo));
        });

        filas.forEach(refrescarFila);
        refrescarResumen();
        $('siResultado').hidden = false;
        if (window.musEtiquetarTablas) window.musEtiquetarTablas();
        $('siResultado').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function alEditar(f) {
        f.editado = true;
        f.error = null;
        refrescarFila(f);
        refrescarResumen();
    }

    function entrada(f, campo, paso, etiqueta) {
        var inp = el('input', 'si-in');
        inp.type = 'number';
        inp.min = '0';
        inp.step = paso;
        inp.inputMode = 'decimal';
        inp.setAttribute('aria-label', etiqueta);
        inp.value = f[campo] === null ? '' : f[campo];
        inp.addEventListener('input', function () {
            f[campo] = num(inp.value);
            if (campo === 'venta') f.ventaManual = true;
            alEditar(f);
        });
        f['in_' + campo] = inp;
        return inp;
    }

    function celda(cls, hijo) {
        var td = el('td', cls);
        if (hijo) td.appendChild(hijo);
        return td;
    }

    function crearFila(f, nombre, presentacion, codigo) {
        var tr = el('tr');

        f.celEstado = celda(); tr.appendChild(f.celEstado);

        // Producto: lo leído + selector del catálogo + alertas
        var tdProd = celda();
        tdProd.appendChild(el('span', 'si-leido', nombre || '(sin nombre legible)'));
        var meta = [presentacion, codigo].filter(Boolean).join(' · ');
        if (meta) tdProd.appendChild(el('span', 'si-meta', meta));
        f.sel = $('siProdTpl').content.firstElementChild.cloneNode(true);
        f.sel.value = f.productId;
        f.sel.addEventListener('change', function () {
            f.productId = f.sel.value;
            var opt = f.sel.selectedOptions[0];
            f.precioActual = opt && opt.dataset.precio ? Number(opt.dataset.precio) : null;
            f.costoCatalogo = opt && opt.dataset.costo ? Number(opt.dataset.costo) : null;
            alEditar(f);
        });
        tdProd.appendChild(f.sel);
        if (CFG.puedeCrear) {
            f.btnCrear = el('button', 'si-crear', '+ Crear producto');
            f.btnCrear.type = 'button';
            f.btnCrear.addEventListener('click', function () { abrirAlta(f, nombre, codigo); });
            tdProd.appendChild(f.btnCrear);
        }
        f.celAlertas = el('div', 'si-alertas');
        tdProd.appendChild(f.celAlertas);
        tr.appendChild(tdProd);

        tr.appendChild(celda('num', entrada(f, 'cajas', '1', 'Cajas')));
        tr.appendChild(celda('num', entrada(f, 'upc', '1', 'Unidades por caja')));
        tr.appendChild(celda('num', entrada(f, 'total', '0.01', 'Costo total del renglón')));
        f.celCostoUnd = celda('num si-calc'); tr.appendChild(f.celCostoUnd);
        tr.appendChild(celda('num', entrada(f, 'venta', '0.01', 'Precio de venta por unidad')));
        f.celVentaCaja = celda('num si-calc'); tr.appendChild(f.celVentaCaja);
        f.celGanCaja   = celda('num si-calc'); tr.appendChild(f.celGanCaja);
        f.celGanUnd    = celda('num si-calc'); tr.appendChild(f.celGanUnd);
        f.celGanTot    = celda('num si-calc'); tr.appendChild(f.celGanTot);
        f.celMargen    = celda('num si-calc'); tr.appendChild(f.celMargen);
        tr.appendChild(celda('num', entrada(f, 'iva', '1', 'IVA %')));

        var chk = el('input');
        chk.type = 'checkbox';
        chk.checked = f.actPrecio;
        chk.disabled = !CFG.puedePrecio;
        chk.title = CFG.puedePrecio ? 'Actualizar el precio de venta del producto' : 'No tienes permiso para cambiar precios';
        chk.setAttribute('aria-label', chk.title);
        chk.addEventListener('change', function () { f.actPrecio = chk.checked; });
        tr.appendChild(celda(null, chk));

        var q = el('button', 'si-quitar', '×');
        q.type = 'button';
        q.title = 'Quitar renglón';
        q.setAttribute('aria-label', 'Quitar renglón');
        q.addEventListener('click', function () {
            f.quitado = !f.quitado;
            tr.classList.toggle('is-removed', f.quitado);
            q.textContent = f.quitado ? '↺' : '×';
            q.title = f.quitado ? 'Restaurar renglón' : 'Quitar renglón';
            q.setAttribute('aria-label', q.title);
            refrescarResumen();
        });
        tr.appendChild(celda('act', q));

        return tr;
    }

    /** Recalcula y repinta un renglón. */
    function refrescarFila(f) {
        var t = tasa();
        // En moneda extranjera sin precio propio: sugerir en pesos cuando ya hay TRM.
        if (t && !f.ventaManual && f.origenPrecio === 'pendiente_trm' && f.total !== null && f.cajas > 0 && f.upc > 0) {
            var cu = f.total * t / (f.cajas * f.upc);
            f.venta = Math.round(cu * (1 + (num($('siMarkup').value) || 0) / 100) * 100) / 100;
            if (f.in_venta) f.in_venta.value = f.venta;
        }
        var totalPesos = f.total === null || !t ? null : Math.round(f.total * t * 100) / 100;
        var r = f.calc = calcular(f.cajas, f.upc, totalPesos, f.venta);
        var negativo = !!(r && r.ganUnd !== null && r.ganUnd < 0);

        f.celCostoUnd.textContent  = r ? pesos(r.costoUnd) : '—';
        // Producto que viene por unidad (1 und/caja): la «caja» es todo lo
        // comprado en el renglón (ej. 2 teclados → venta y ganancia de los 2).
        var porUnidad = r && f.upc === 1;
        f.celVentaCaja.textContent = r ? pesos(porUnidad ? r.ventaTotal : r.ventaCaja) : '—';
        f.celGanCaja.textContent   = r ? pesos(porUnidad && r.ventaTotal !== null ? r.ventaTotal - r.costoTotal : r.ganCaja) : '—';
        f.celGanUnd.textContent    = r ? pesos(r.ganUnd) : '—';
        f.celGanTot.textContent    = r && r.ventaTotal !== null ? pesos(r.ventaTotal - r.costoTotal) : '—';
        f.celMargen.textContent    = r && r.margen !== null ? fmt.format(r.margen) + ' %' : '—';
        [f.celGanCaja, f.celGanUnd, f.celGanTot, f.celMargen].forEach(function (c) { c.classList.toggle('si-neg', negativo); });

        // Alertas vivas: al editar, las de lectura de la IA se dan por revisadas.
        var alertas = f.editado
            ? f.alertas.filter(function (a) { return INFORMATIVAS.indexOf(a) !== -1; })
            : f.alertas.filter(function (a) { return a !== 'PRODUCTO_NO_ENCONTRADO' && a !== 'DATOS_INCOMPLETOS' && a !== 'VENTA_BAJO_COSTO'; });
        if (!f.productId) alertas.push('PRODUCTO_NO_ENCONTRADO');
        if (moneda.extranjera && !t) alertas.push('FALTA_TRM');
        else if (!r) alertas.push('DATOS_INCOMPLETOS');
        if (negativo) alertas.push('VENTA_BAJO_COSTO');
        // Redondeo y costo atípico se recalculan en vivo (el usuario pudo corregir la cantidad).
        alertas = alertas.filter(function (a) { return a !== 'COSTO_ATIPICO' && a !== 'REDONDEO_COSTO_UNITARIO'; });
        if (r && Math.round(r.costoUnd * f.cajas * f.upc) !== r.costoTotal) alertas.push('REDONDEO_COSTO_UNITARIO');
        var ratio = r && f.costoCatalogo > 0 ? (r.costoUnd / 100) / f.costoCatalogo : null;
        var atipico = ratio !== null && (ratio < 0.5 || ratio > 2);
        if (atipico) alertas.push('COSTO_ATIPICO');

        f.celAlertas.textContent = '';
        alertas.forEach(function (a) {
            var def = ALERTAS[a] || [a, false];
            var txt = '• ' + def[0];
            if (a === 'COSTO_ATIPICO') txt += ' (leído ' + pesos(r.costoUnd) + ' vs. catálogo ' + pesos(cents(f.costoCatalogo)) + ')';
            var linea = el('span', def[1] && !(a === 'COSTO_ATIPICO' && f.costoRevisado) ? 'is-bad' : '', txt);
            if (a === 'COSTO_ATIPICO' && !f.costoRevisado) {
                var ok = el('button', 'si-crear', 'El costo es correcto');
                ok.type = 'button';
                ok.style.marginLeft = '6px';
                ok.addEventListener('click', function () { f.costoRevisado = true; refrescarFila(f); refrescarResumen(); });
                linea.appendChild(ok);
            }
            f.celAlertas.appendChild(linea);
        });
        if (!f.editado) {
            f.discrepancias.forEach(function (d) {
                f.celAlertas.appendChild(el('span', '', '• Corregido ' + d.campo.replace('margen_ganancia.', '').replace(/_/g, ' ') +
                    ': la IA calculó ' + fmt.format(d.valor_ia) + ', el valor correcto es ' +
                    (d.valor_calculado === null ? '—' : fmt.format(d.valor_calculado))));
            });
        }
        if (f.error) f.celAlertas.appendChild(el('span', 'is-bad', '• ' + f.error));

        // Bloquean: datos inválidos o sin producto. Lo demás es advertencia.
        var bloqueaCosto = atipico && !f.costoRevisado;
        f.valida = !!r && !!f.productId && !bloqueaCosto;
        var estado = !r ? 'incompleto'
            : (!f.productId || negativo || bloqueaCosto) ? 'revisar'
            : f.editado ? 'editado'
            : f.estadoIA;
        f.celEstado.textContent = '';
        f.celEstado.appendChild(badge(estado));
        f.sel.setAttribute('aria-invalid', f.productId ? 'false' : 'true');
        if (f.btnCrear) f.btnCrear.hidden = !!f.productId;
    }

    /** Totales, duplicados, contadores y habilitación del botón Confirmar. */
    function refrescarResumen() {
        var activas = filas.filter(function (f) { return !f.quitado; });

        // Mismo producto en dos renglones: el backend lo rechaza (regla distinct).
        var conteo = {};
        activas.forEach(function (f) { if (f.productId) conteo[f.productId] = (conteo[f.productId] || 0) + 1; });
        filas.forEach(function (f) {
            var dup = !f.quitado && f.productId && conteo[f.productId] > 1;
            var tenia = f.error === DUP;
            if (dup && !tenia) { f.error = DUP; refrescarFila(f); }
            if (!dup && tenia) { f.error = null; refrescarFila(f); }
        });

        var costo = 0, venta = 0, conVenta = true;
        activas.forEach(function (f) {
            if (!f.calc) return;
            costo += f.calc.costoTotal;
            if (f.calc.ventaTotal === null) conVenta = false; else venta += f.calc.ventaTotal;
        });
        var pendientes = activas.filter(function (f) { return !f.valida || f.error === DUP; }).length;

        var tot = $('siTot');
        tot.textContent = '';
        [['Costo de la compra', pesos(costo)],
         ['Venta proyectada', conVenta ? pesos(venta) : '—'],
         ['Ganancia proyectada', conVenta ? pesos(venta - costo) : '—'],
         ['Margen global', conVenta && venta ? fmt.format(Math.round((venta - costo) / venta * 10000) / 100) + ' %' : '—'],
        ].forEach(function (p) {
            var d = el('div');
            d.appendChild(el('span', null, p[0]));
            d.appendChild(el('b', null, p[1]));
            tot.appendChild(d);
        });

        var kpi = $('siKpi');
        kpi.textContent = '';
        kpi.appendChild(el('span', 'mbg mbg--soft', activas.length + ' renglón(es)'));
        kpi.appendChild(el('span', 'mbg mbg--' + (pendientes ? 'warn' : 'ok'), pendientes ? pendientes + ' por resolver' : 'Todo listo'));

        $('siConfirmar').disabled = !activas.length || pendientes > 0 || !$('siProveedor').value || (moneda.extranjera && !tasa());
    }

    $('siProveedor').addEventListener('change', refrescarResumen);
    $('siTrm').addEventListener('input', function () { filas.forEach(refrescarFila); refrescarResumen(); });

    // ───────────────────────── alta rápida de producto ─────────────────────────

    var filaAlta = null;

    function abrirAlta(f, nombre, codigo) {
        filaAlta = f;
        $('siNpNombre').value = nombre || '';
        // Un código numérico largo (EAN/UPC) es código de barras; si no, SKU del proveedor.
        var esBarras = /^\d{8,14}$/.test(codigo || '');
        $('siNpBarra').value = esBarras ? codigo : '';
        $('siNpSku').value = !esBarras && codigo ? codigo : '';
        $('siNpPrecio').value = f.venta !== null ? f.venta : '';
        $('siNpCosto').value = f.calc ? f.calc.costoUnd / 100 : '';
        $('siNpErr').textContent = '';
        $('siDlg').showModal();
        $('siNpNombre').focus();
    }

    $('siNpCrear').addEventListener('click', function (ev) {
        ev.preventDefault();
        var btn = this;
        var datos = {
            name: $('siNpNombre').value.trim(),
            sku: $('siNpSku').value.trim() || null,
            barcode: $('siNpBarra').value.trim() || null,
            price: num($('siNpPrecio').value),
            cost: num($('siNpCosto').value),
        };
        if (!datos.name || datos.price === null) { $('siNpErr').textContent = 'Nombre y precio de venta son obligatorios.'; return; }

        btn.disabled = true;
        fetch(CFG.crear, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(datos),
        })
        .then(leerJson)
        .then(function (res) {
            if (res.status !== 201 || !res.json.success) {
                var e = res.json.errors ? Object.values(res.json.errors).flat().join(' ') : '';
                throw new Error(e || res.json.message || 'No se pudo crear el producto.');
            }
            var p = res.json.data;
            var texto = (p.sku ? p.sku + ' · ' : '') + p.name;
            // Disponible en la plantilla y en todos los selectores abiertos.
            [$('siProdTpl').content.firstElementChild].concat(filas.map(function (x) { return x.sel; })).forEach(function (sel) {
                var o = document.createElement('option');
                o.value = String(p.id);
                o.dataset.precio = p.price;
                o.dataset.costo = p.cost;
                o.textContent = texto;
                sel.appendChild(o);
            });
            filaAlta.sel.value = String(p.id);
            filaAlta.productId = String(p.id);
            filaAlta.precioActual = Number(p.price);
            filaAlta.costoCatalogo = Number(p.cost);
            if (filaAlta.venta === null) { filaAlta.venta = Number(p.price); if (filaAlta.in_venta) filaAlta.in_venta.value = p.price; }
            alEditar(filaAlta);
            $('siDlg').close();
            estadoMsg(res.json.message);
        })
        .catch(function (e) { $('siNpErr').textContent = e.message; })
        .finally(function () { btn.disabled = false; });
    });

    // ───────────────────────── paso 3: confirmar ─────────────────────────

    $('siConfirmar').addEventListener('click', function () {
        var btn = this;
        var activas = filas.filter(function (f) { return !f.quitado; });

        var payload = {
            supplier_id: Number($('siProveedor').value),
            numero_factura: $('siFactura').value.trim() || null,
            fecha: $('siFecha').value || null,
            imagen_ref: imagenRef,
            moneda: moneda.codigo,
            trm: moneda.extranjera ? tasa() : null,
            items: activas.map(function (f) {
                return {
                    product_id: Number(f.productId),
                    cantidad_cajas: f.cajas,
                    cantidad_unidades: f.upc,
                    costo_total_compra: f.total,
                    precio_venta_unitario: f.venta,
                    iva_porcentaje: f.iva,
                    actualizar_costo: true,
                    actualizar_precio: !!(CFG.puedePrecio && f.actPrecio),
                };
            }),
        };

        var redirigiendo = false;
        btn.disabled = true;
        estadoMsg('Creando la compra…', 'busy');

        fetch(CFG.confirmar, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
        .then(leerJson)
        .then(function (res) {
            if (res.status === 201 && res.json.success) {
                redirigiendo = true;
                estadoMsg(res.json.message);
                window.location.href = res.json.data.url;
                return;
            }
            if (res.status === 419) throw new Error('La sesión expiró. Recarga la página.');

            // 422: errores por campo. items.N.* → renglón N del payload.
            var errores = (res.json.data && res.json.data.errors) || res.json.errors || {};
            var generales = [];
            Object.keys(errores).forEach(function (k) {
                var m = /^items\.(\d+)/.exec(k);
                var texto = [].concat(errores[k]).join(' ');
                if (m && activas[Number(m[1])]) activas[Number(m[1])].error = texto;
                else generales.push(texto);
            });
            activas.forEach(refrescarFila);
            refrescarResumen();
            var msg = generales.join(' ') || res.json.message || 'No se pudo crear la compra.';
            $('siAvisos').prepend(el('div', 'si-aviso si-aviso--bad', msg));
            estadoMsg(msg, 'error');
        })
        .catch(function (e) {
            estadoMsg(e.message || 'Error de red al crear la compra. Inténtalo de nuevo.', 'error');
        })
        .finally(function () {
            if (!redirigiendo) refrescarResumen();   // re-evalúa si se puede volver a confirmar
        });
    });
})();
</script>
</x-mus.page>
