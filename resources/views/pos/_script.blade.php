<script>
/* ══════════════════════════════════════════════════════════════
   Punto de venta. Todo el carrito vive en memoria; al confirmar se
   escriben inputs ocultos y el formulario se envía normal.
   ══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    /* Los textos del mostrador, traídos de lang/. Sin esto el guion
       imprimía «producto» y «Guardando…» en español encima de un panel
       en inglés — y son justo los que más se leen. */
    var T = @json([
        'producto'    => __('mus.pos.js_producto'),
        'productos'   => __('mus.pos.js_productos'),
        'unidad'      => __('mus.pos.js_unidad'),
        'unidades'    => __('mus.pos.js_unidades'),
        'sinStock'    => __('mus.pos.js_sin_stock'),
        'soloQuedan'  => __('mus.pos.js_solo_quedan'),
        'guardando'   => __('mus.pos.js_guardando'),
        'sinProductos'=> __('mus.pos.sin_productos'),
    ]);

    /** Reemplaza :clave dentro de un texto de lang/, como hace __() en PHP. */
    function t(plantilla, repl) {
        return Object.keys(repl || {}).reduce(function (txt, k) {
            return txt.split(':' + k).join(repl[k]);
        }, plantilla);
    }

    // Blade puede entregar la lista como objeto si las claves no son
    // consecutivas; se normaliza a array para poder filtrarla.
    var CRUDO = window.POS_PRODUCTOS || [];
    var PROD = Array.isArray(CRUDO) ? CRUDO : Object.keys(CRUDO).map(function (k) { return CRUDO[k]; });
    var carrito = [];                     // [{id, nombre, precio, cant, stock, unidad, imp}]

    var $ = function (id) { return document.getElementById(id); };

    var grid    = $('posGrid');
    var vacio   = $('posVacio');
    var buscar  = $('posBuscar');
    var cuenta  = $('posCuenta');
    var lineas  = $('posLineas');
    var ticVac  = $('posTicVacio');
    var resumen = $('posResumen');
    var modal   = $('posModal');
    var form    = $('posForm');
    var ocultos = $('posOcultos');
    var barra   = $('posMovil');
    var tiquete = document.querySelector('.pos-tic');

    if (!grid || !form) return;

    /* ---------- utilidades ---------- */
    function pesos(n) {
        return '$' + Math.round(n).toLocaleString('es-CO');
    }

    function sinTildes(s) {
        return (s || '').toString().toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    /* ---------- catálogo ---------- */
    function pintarCatalogo(filtro) {
        var t = sinTildes(filtro).trim();
        var lista = PROD;

        if (t) {
            lista = PROD.filter(function (p) {
                return sinTildes(p.nombre).indexOf(t) !== -1
                    || sinTildes(p.sku).indexOf(t) !== -1
                    || sinTildes(p.codigo).indexOf(t) !== -1;
            });
        }

        grid.innerHTML = '';
        vacio.hidden = lista.length > 0;
        cuenta.textContent = lista.length + ' ' + (lista.length === 1 ? T.producto : T.productos);

        lista.slice(0, 240).forEach(function (p) {
            var enCarro = carrito.find(function (l) { return l.id === p.id; });
            var libre = p.stock - (enCarro ? enCarro.cant : 0);
            var agotado = libre <= 0;

            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'pos-item' + (agotado ? ' is-off' : '');
            b.dataset.id = p.id;
            if (agotado) b.disabled = true;

            var cls = libre <= 0 ? 'cero' : (libre <= 5 ? 'bajo' : '');

            b.innerHTML =
                '<span class="pos-item__st ' + cls + '">' + libre + ' ' + p.unidad + '</span>' +
                '<span class="pos-item__n"></span>' +
                '<span class="pos-item__s"></span>' +
                '<span class="pos-item__p">' + pesos(p.precio) + '</span>';

            b.querySelector('.pos-item__n').textContent = p.nombre;
            b.querySelector('.pos-item__s').textContent = p.sku || p.codigo || '';

            grid.appendChild(b);
        });
    }

    /* ---------- carrito ---------- */
    function agregar(id) {
        var p = PROD.find(function (x) { return x.id === id; });
        if (!p) return;

        var l = carrito.find(function (x) { return x.id === id; });

        if (l) {
            if (l.cant + 1 > p.stock) { avisar(p.nombre, p.stock); return; }
            l.cant += 1;
        } else {
            if (p.stock < 1) { avisar(p.nombre, p.stock); return; }
            carrito.push({
                id: p.id, nombre: p.nombre, precio: p.precio,
                cant: 1, stock: p.stock, unidad: p.unidad, imp: p.imp
            });
        }
        pintarTodo();
    }

    function avisar(nombre, stock) {
        if (window.musToast) {
            window.musToast('bad', T.sinStock, t(T.soloQuedan, { que: nombre, n: stock }));
        }
    }

    function fijarCantidad(id, n) {
        var l = carrito.find(function (x) { return x.id === id; });
        if (!l) return;

        n = Math.floor(Number(n) || 0);

        if (n <= 0) {
            carrito = carrito.filter(function (x) { return x.id !== id; });
        } else if (n > l.stock) {
            avisar(l.nombre, l.stock);
            l.cant = l.stock;
        } else {
            l.cant = n;
        }
        pintarTodo();
    }

    function impuestoDe(l) {
        if (!l.imp) return 0;
        if (l.imp.fijo) return l.imp.tasa * l.cant;
        return (l.precio * l.cant) * l.imp.tasa / 100;
    }

    function totales() {
        var sub = 0, imp = 0;
        carrito.forEach(function (l) {
            sub += l.precio * l.cant;
            imp += impuestoDe(l);
        });
        return { sub: sub, imp: imp, desc: 0, total: sub + imp };
    }

    function pintarTicket() {
        lineas.innerHTML = '';
        ticVac.hidden = carrito.length > 0;

        carrito.forEach(function (l) {
            var d = document.createElement('div');
            d.className = 'pos-l';
            d.innerHTML =
                '<span class="pos-l__n"></span>' +
                '<button type="button" class="pos-l__x" data-quitar="' + l.id + '" aria-label="Quitar">&times;</button>' +
                '<span class="pos-l__row">' +
                    '<span class="pos-cant">' +
                        '<button type="button" data-menos="' + l.id + '">−</button>' +
                        '<input type="number" min="0" value="' + l.cant + '" data-cant="' + l.id + '">' +
                        '<button type="button" data-mas="' + l.id + '">+</button>' +
                    '</span>' +
                    '<span>' +
                        '<span class="pos-l__t">' + pesos(l.precio * l.cant + impuestoDe(l)) + '</span><br>' +
                        '<span class="pos-l__u">' + pesos(l.precio) + ' c/u</span>' +
                    '</span>' +
                '</span>';
            d.querySelector('.pos-l__n').textContent = l.nombre;
            lineas.appendChild(d);
        });

        var t = totales();
        $('totSub').textContent   = pesos(t.sub);
        $('totDesc').textContent  = pesos(t.desc);
        $('totImp').textContent   = pesos(t.imp);
        $('totTotal').textContent = pesos(t.total);

        var n = carrito.reduce(function (a, l) { return a + l.cant; }, 0);
        resumen.textContent = carrito.length
            ? carrito.length + ' ' + (carrito.length === 1 ? T.producto : T.productos)
              + ' · ' + n + ' ' + (n === 1 ? T.unidad : T.unidades)
            : T.sinProductos;

        var btn = $('posCobrar');
        btn.disabled = carrito.length === 0;
        $('posCobrarTot').textContent = carrito.length ? pesos(t.total) : '';

        pintarBarraMovil(t, n);
    }

    /* ---------- barra fija de celular ----------
       Solo aparece cuando hay algo en el carrito; muestra el total y
       cobra sin obligar a subir hasta el tiquete. */
    function pintarBarraMovil(t, unidades) {
        if (!barra) return;

        var hay = carrito.length > 0;

        barra.classList.toggle('is-on', hay);
        document.body.classList.toggle('pos-con-barra', hay);

        if (!hay) return;

        $('posMovilN').textContent = carrito.length
            + ' ' + (carrito.length === 1 ? T.producto : T.productos) + ' · '
            + unidades + ' ' + (unidades === 1 ? T.unidad : T.unidades);
        $('posMovilTot').textContent = pesos(t.total);
    }

    function pintarTodo() {
        pintarTicket();
        pintarCatalogo(buscar.value);
    }

    /* ---------- eventos del catálogo ---------- */
    grid.addEventListener('click', function (e) {
        var b = e.target.closest('.pos-item');
        if (b && !b.disabled) agregar(Number(b.dataset.id));
    });

    /* ---------- eventos del ticket ---------- */
    lineas.addEventListener('click', function (e) {
        var q = e.target.closest('[data-quitar]');
        if (q) { fijarCantidad(Number(q.dataset.quitar), 0); return; }

        var mas = e.target.closest('[data-mas]');
        if (mas) { agregar(Number(mas.dataset.mas)); return; }

        var menos = e.target.closest('[data-menos]');
        if (menos) {
            var id = Number(menos.dataset.menos);
            var l = carrito.find(function (x) { return x.id === id; });
            if (l) fijarCantidad(id, l.cant - 1);
        }
    });

    lineas.addEventListener('change', function (e) {
        var i = e.target.closest('[data-cant]');
        if (i) fijarCantidad(Number(i.dataset.cant), i.value);
    });

    $('posVaciar').addEventListener('click', function () {
        if (!carrito.length) return;
        carrito = [];
        pintarTodo();
    });

    /* ---------- búsqueda ---------- */
    var tmr;
    buscar.addEventListener('input', function () {
        clearTimeout(tmr);
        tmr = setTimeout(function () { pintarCatalogo(buscar.value); }, 90);
    });

    // Enter con un solo resultado = agregar (sirve con lector de código de barras)
    buscar.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var visibles = grid.querySelectorAll('.pos-item:not(.is-off)');
        if (visibles.length >= 1) {
            agregar(Number(visibles[0].dataset.id));
            buscar.value = '';
            pintarCatalogo('');
        }
    });

    /* ---------- modal de cobro ---------- */
    function abrirModal() {
        if (!carrito.length) return;
        var t = totales();
        $('modalTotal').textContent = pesos(t.total);
        modal.classList.add('is-on');
        var primero = modal.querySelector('[data-pago]');
        setTimeout(function () { if (primero) primero.focus(); }, 240);
        recalcularPagos();
    }

    function cerrarModal() {
        modal.classList.remove('is-on');
        buscar.focus();
    }

    $('posCobrar').addEventListener('click', abrirModal);
    $('posCerrar').addEventListener('click', cerrarModal);
    $('posVolver').addEventListener('click', cerrarModal);

    if (barra) {
        $('posMovilCobrar').addEventListener('click', abrirModal);
        $('posMovilVer').addEventListener('click', function () {
            if (tiquete) tiquete.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    modal.addEventListener('click', function (e) {
        if (e.target === modal) cerrarModal();
    });

    function recalcularPagos() {
        var t = totales().total;
        var recibido = 0;

        modal.querySelectorAll('[data-pago]').forEach(function (i) {
            recibido += Number(i.value) || 0;
        });

        var falta  = Math.max(0, t - recibido);
        var cambio = Math.max(0, recibido - t);

        $('modalRecibido').textContent = pesos(recibido);
        $('modalFalta').textContent    = pesos(falta);
        $('modalCambio').textContent   = pesos(cambio);

        $('posConfirmar').disabled = falta > 0.009 || carrito.length === 0;
    }

    modal.addEventListener('input', function (e) {
        if (e.target.matches('[data-pago]')) recalcularPagos();
    });

    $('posExacto').addEventListener('click', function () {
        var campos = modal.querySelectorAll('[data-pago]');
        campos.forEach(function (i, n) { i.value = n === 0 ? Math.round(totales().total) : ''; });
        recalcularPagos();
    });

    /* ---------- envío ---------- */
    form.addEventListener('submit', function (e) {
        if (!carrito.length) { e.preventDefault(); return; }

        ocultos.innerHTML = '';

        carrito.forEach(function (l, i) {
            ocultos.insertAdjacentHTML('beforeend',
                '<input type="hidden" name="items[' + i + '][product_id]" value="' + l.id + '">' +
                '<input type="hidden" name="items[' + i + '][quantity]" value="' + l.cant + '">' +
                '<input type="hidden" name="items[' + i + '][unit_price]" value="' + l.precio + '">');
        });

        // Evita enviar medios de pago en blanco.
        modal.querySelectorAll('[data-pago]').forEach(function (i) {
            if (!Number(i.value)) {
                i.disabled = true;
                var oculto = i.parentNode.querySelector('input[type="hidden"]');
                if (oculto) oculto.disabled = true;
            }
        });

        $('posConfirmar').disabled = true;
        $('posConfirmar').textContent = T.guardando;
    });

    /* ---------- atajos ---------- */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'F2') { e.preventDefault(); buscar.focus(); buscar.select(); }
        if (e.key === 'F9') { e.preventDefault(); abrirModal(); }
        if (e.key === 'Escape' && modal.classList.contains('is-on')) cerrarModal();
    });

    /* ---------- arranque ---------- */
    pintarTodo();
})();
</script>
