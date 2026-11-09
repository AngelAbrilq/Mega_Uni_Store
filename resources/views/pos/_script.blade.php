<script>
/* ══════════════════════════════════════════════════════════════
   Punto de venta. Todo el carrito vive en memoria; al confirmar se
   escriben inputs ocultos y el formulario se envía normal.
   ══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    /* Los textos del mostrador, traídos de lang/. Sin esto el guion
       imprimía «producto» y «Guardando…» en español encima de un panel
       en inglés — y son justo los que más se leen.

       El array se arma arriba, en un bloque de PHP, y NO dentro de la
       directiva que lo vuelve JSON. Esa directiva no es una función:
       Blade parte lo que le llega por las comas para sacar sus tres
       argumentos —valor, opciones y profundidad—, así que un array
       escrito ahí adentro se corta en la primera coma y el PHP compilado
       queda con un corchete sin cerrar. Ese fue el error «Unclosed '['
       on line 12 does not match ')'». Con la variable ya hecha no hay
       comas que partir.

       Y por eso este comentario no escribe esas directivas con su arroba:
       Blade compila las directivas también dentro de un comentario de
       JavaScript, así que nombrarlas aquí habría vuelto a romper la
       vista — esta vez desde la explicación del arreglo. */
    @php
        $T = [
            'producto'     => __('mus.pos.js_producto'),
            'productos'    => __('mus.pos.js_productos'),
            'unidad'       => __('mus.pos.js_unidad'),
            'unidades'     => __('mus.pos.js_unidades'),
            'sinStock'     => __('mus.pos.js_sin_stock'),
            'soloQuedan'   => __('mus.pos.js_solo_quedan'),
            'guardando'    => __('mus.pos.js_guardando'),
            'sinProductos' => __('mus.pos.sin_productos'),
            'cuponValidando' => __('mus.pos.cupon_validando'),
            'cuponMinimo'    => __('mus.pos.cupon_minimo'),
        ];
    @endphp

    var T = @json($T);

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
                cant: 1, stock: p.stock, unidad: p.unidad, imps: p.imps || []
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

    // AT-PRI-001: suma de todos los impuestos del producto (igual que Tax::sobreRenglon).
    function impuestoDe(l) {
        return (l.imps || []).reduce(function (total, t) {
            return total + (t.fijo ? t.tasa * l.cant : (l.precio * l.cant) * t.tasa / 100);
        }, 0);
    }

    /* ---------- VD-CUP-001 · cupón ----------
       Mismo cálculo que Coupon::calcularDescuento y CouponService::repartir:
       el descuento sale de la base antes de impuestos y se reparte entre los
       renglones, así el IVA se calcula sobre lo que de verdad se paga. Es
       solo una vista previa: el servidor lo vuelve a calcular al cobrar. */
    var cupon = null;

    function descuentoCupon(base) {
        if (!cupon || base <= 0) return 0;
        if (cupon.minimo != null && base + 0.009 < cupon.minimo) return 0;
        var d = cupon.tipo === 'fixed' ? cupon.valor : base * cupon.valor / 100;
        if (cupon.tipo !== 'fixed' && cupon.tope != null) d = Math.min(d, cupon.tope);
        return Math.round(Math.max(0, Math.min(d, base)) * 100) / 100;
    }

    function impuestoSobre(l, base) {
        return (l.imps || []).reduce(function (total, t) {
            return total + (t.fijo ? t.tasa * l.cant : base * t.tasa / 100);
        }, 0);
    }

    function totales() {
        var sub = 0, imp = 0;
        carrito.forEach(function (l) { sub += l.precio * l.cant; });
        var desc = descuentoCupon(sub);
        carrito.forEach(function (l) {
            var bruto = l.precio * l.cant;
            var parte = sub > 0 ? desc * bruto / sub : 0;
            imp += impuestoSobre(l, bruto - parte);
        });
        return { sub: sub, imp: imp, desc: desc, total: sub - desc + imp };
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

    /* ══════════════════════════════════════════════════════════════
       Cajón monedero
       ══════════════════════════════════════════════════════════════
       Dos caminos hacia el mismo pulso:

         · automático — al confirmar la venta. Dispara y olvida, con
           keepalive:true, porque medio segundo después el navegador ya está
           yendo al recibo y sin eso Chrome cancela la petición al descargar
           la página. Ese es EL bug clásico de esta integración.

         · manual — el botón «Abrir caja» de la barra superior, para dar
           cambio o guardar un billete. Ese sí espera respuesta y sí queda
           anotado en la bitácora, porque una caja que se abre sin venta es
           justo lo que un arqueo no puede explicar solo.

       Todos los errores se tragan a propósito: que el hardware falle nunca
       debe pintar un error rojo encima de una venta que sí se guardó.
       ══════════════════════════════════════════════════════════════ */
    /*
       Modo agente (servidor en la nube): el pulso NO pasa por el servidor.
       El navegador le habla directo al agente del PC de caja en 127.0.0.1,
       con el token del local. Va como text/plain para que sea una petición
       «simple» (sin preflight), que es la única que keepalive garantiza.
       Al servidor solo se le avisa de las aperturas manuales, para auditoría.
    */
    var CAJON = {
        pulso:  '{{ route('pos.cajon') }}',
        estado: '{{ route('pos.cajon.estado') }}',
        activo: @json($cajon['activo']),
        agente: @json($cajon['agente'])
    };

    function alAgente(ruta, opciones) {
        var a = CAJON.agente;
        opciones = opciones || {};

        if ((opciones.method || 'GET') === 'GET') {
            return fetch(a.url + ruta + '?token=' + encodeURIComponent(a.token), { mode: 'cors' });
        }

        return fetch(a.url + ruta, {
            method:    'POST',
            mode:      'cors',
            keepalive: !!opciones.keepalive,
            headers:   { 'Content-Type': 'text/plain' },
            body:      JSON.stringify({ token: a.token })
        });
    }

    function cabecerasCajon() {
        return {
            'X-CSRF-TOKEN':     '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept':           'application/json'
        };
    }

    /* Camino automático. Sin await, sin then: sale y se olvida. */
    function abrirCajon() {
        if (!CAJON.activo) return;

        if (CAJON.agente) {
            try { alAgente('/cajon', { method: 'POST', keepalive: true }).catch(function () {}); }
            catch (err) { /* sin agente: la venta sigue */ }
            return;
        }

        try {
            fetch(CAJON.pulso, {
                method:      'POST',
                headers:     cabecerasCajon(),
                keepalive:   true,
                credentials: 'same-origin'
            }).catch(function () {});
        } catch (err) { /* navegador viejo sin fetch: se ignora */ }
    }

    /* ---------- botón manual + semáforo ---------- */
    (function cajonManual() {
        var boton = $('posCajon');
        if (!CAJON.activo || !boton) return;

        var luz   = $('posCajonLuz');
        var texto = $('posCajonTxt');
        var base  = texto.textContent;

        function pintar(estado) {
            luz.dataset.nivel = estado.nivel || 'caido';

            // El detalle largo va al title del botón: no roba espacio en la
            // barra y está ahí para el momento en que algo falla.
            boton.title = estado.mensaje + (estado.detalle ? ' — ' + estado.detalle : '');
        }

        function consultar(forzar) {
            luz.dataset.nivel = 'probando';

            if (CAJON.agente) {
                alAgente('/estado')
                    .then(function (r) { return r.json(); })
                    .then(pintar)
                    .catch(function () {
                        pintar({ nivel: 'caido', mensaje: @json(__('mus.pos.cajon_sin_agente')) });
                    });
                return;
            }

            fetch(CAJON.estado + (forzar ? '?forzar=1' : ''), {
                headers:     cabecerasCajon(),
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(pintar)
                .catch(function () {
                    pintar({ nivel: 'caido', mensaje: @json(__('mus.pos.cajon_caido')) });
                });
        }

        boton.addEventListener('click', function () {
            boton.disabled   = true;
            texto.textContent = @json(__('mus.pos.cajon_enviando'));

            // Con agente: primero el pulso en el PC de caja, después el aviso
            // al servidor (manual=1 + ok) para que quede en auditoría.
            var pulso = CAJON.agente
                ? alAgente('/cajon', { method: 'POST' })
                    .then(function (r) { return r.json(); })
                    .catch(function () { return { abierto: false }; })
                    .then(function (d) {
                        return fetch(CAJON.pulso, {
                            method:      'POST',
                            headers:     cabecerasCajon(),
                            credentials: 'same-origin',
                            body:        new URLSearchParams({ manual: '1', ok: d.abierto ? '1' : '0' })
                        }).then(function () { return d; });
                    })
                : fetch(CAJON.pulso, {
                    method:      'POST',
                    headers:     cabecerasCajon(),
                    credentials: 'same-origin',
                    // manual=1 es lo que distingue esta apertura de la de una
                    // venta: solo estas quedan escritas en auditoría.
                    body:        new URLSearchParams({ manual: '1' })
                }).then(function (r) { return r.json(); });

            pulso
                .then(function (d) {
                    texto.textContent = d.abierto
                        ? @json(__('mus.pos.cajon_enviado'))
                        : @json(__('mus.pos.cajon_fallo'));
                    if (d.estado) pintar(d.estado);
                })
                .catch(function () {
                    texto.textContent = @json(__('mus.pos.cajon_fallo'));
                    pintar({ nivel: 'caido', mensaje: @json(__('mus.pos.cajon_caido')) });
                })
                .then(function () {
                    // Devolver la etiqueta al cabo de un momento, para que el
                    // botón no quede congelado diciendo «Pulso enviado».
                    setTimeout(function () {
                        texto.textContent = base;
                        boton.disabled    = false;
                    }, 1400);
                });
        });

        consultar(false);

        // Cada minuto, y solo con la pestaña a la vista: sondear una cola de
        // impresión de Windows cuesta cientos de milisegundos y no tiene
        // sentido hacerlo contra un POS que nadie está mirando.
        setInterval(function () {
            if (!document.hidden) consultar(false);
        }, 60000);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) consultar(false);
        });
    })();

    /* ---------- cupón: aplicar / quitar ---------- */
    (function () {
        var caja = $('posCupon'), campo = $('posCuponCodigo'), boton = $('posCuponAplicar');
        var activo = $('posCuponActivo'), texto = $('posCuponTexto'), msg = $('posCuponMsg');
        if (!caja) return;

        function quitar() {
            cupon = null; caja.classList.remove('is-on'); activo.hidden = true;
            campo.value = ''; msg.textContent = ''; pintarTodo();
        }

        function aplicar() {
            var codigo = campo.value.trim();
            if (!codigo) { campo.focus(); return; }
            boton.disabled = true; msg.textContent = ''; boton.textContent = T.cuponValidando;
            var url = @json(route('pos.cupon')) + '?codigo=' + encodeURIComponent(codigo)
                    + '&base=' + encodeURIComponent(totales().sub);
            fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.ok) { msg.textContent = d.mensaje || ''; return; }
                    cupon = d; texto.textContent = d.mensaje;
                    caja.classList.add('is-on'); activo.hidden = false;
                    pintarTodo();
                })
                .catch(function () { msg.textContent = '—'; })
                .finally(function () { boton.disabled = false; boton.textContent = @json(__('mus.pos.cupon_aplicar')); });
        }

        boton.addEventListener('click', aplicar);
        campo.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); aplicar(); } });
        $('posCuponQuitar').addEventListener('click', quitar);
    })();

    /* ---------- envío ---------- */
    form.addEventListener('submit', function (e) {
        if (!carrito.length) { e.preventDefault(); return; }

        // Un cupón que dejó de cumplir el mínimo (se quitaron productos) se avisa antes de enviar.
        if (cupon && cupon.minimo != null && totales().sub + 0.009 < cupon.minimo) {
            e.preventDefault();
            $('posCuponMsg').textContent = t(T.cuponMinimo, { codigo: cupon.codigo, minimo: pesos(cupon.minimo) });
            return;
        }

        abrirCajon();

        ocultos.innerHTML = '';

        carrito.forEach(function (l, i) {
            ocultos.insertAdjacentHTML('beforeend',
                '<input type="hidden" name="items[' + i + '][product_id]" value="' + l.id + '">' +
                '<input type="hidden" name="items[' + i + '][quantity]" value="' + l.cant + '">' +
                '<input type="hidden" name="items[' + i + '][unit_price]" value="' + l.precio + '">');
        });

        if (cupon) {
            ocultos.insertAdjacentHTML('beforeend', '<input type="hidden" name="cupon">');
            ocultos.lastElementChild.value = cupon.codigo;
        }

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
        // F4 abre la caja sin vender. F1..F3 y F5 los pisa el navegador; F4
        // está libre en Chrome, Edge y Firefox.
        if (e.key === 'F4') { e.preventDefault(); var b = $('posCajon'); if (b) b.click(); }
    });

    /* ---------- arranque ---------- */
    pintarTodo();
})();
</script>
