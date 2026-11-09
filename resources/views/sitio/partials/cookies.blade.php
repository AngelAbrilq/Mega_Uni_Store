{{--
    Aviso y consentimiento de cookies + carga condicionada de GA4.

    Regla (Ley 1581 + guía SIC sobre cookies): ninguna cookie NO esencial
    se escribe antes de que el visitante diga que sí. Por eso GA4 no está
    en el <head>: este script lo inyecta solo con consentimiento "todas".

    La elección se guarda en la cookie técnica `mus_cookies`
    (necesarias | todas) por 180 días. "Configurar cookies" en el pie la
    vuelve a preguntar; si retira el permiso se borran las cookies _ga.
--}}
@php($ga = config('sitio.ga4_id'))
<div class="ck" id="ck" role="dialog" aria-modal="false" aria-labelledby="ck-titulo" aria-describedby="ck-texto" hidden>
    <div class="ck__caja">
        <h2 id="ck-titulo" class="ck__t">Usamos cookies</h2>
        <p id="ck-texto" class="ck__p">
            Las necesarias mantienen tu sesión y la seguridad del sitio.
            @if ($ga) Con tu permiso también usamos cookies de analítica (Google Analytics) para saber qué páginas sirven y mejorar el sitio. @endif
            Más detalle en la <a href="{{ route('sitio.cookies') }}">política de cookies</a>.
        </p>
        <div class="ck__btns">
            @if ($ga)
                <button type="button" class="ck__b ck__b--s" data-ck="necesarias">Solo necesarias</button>
                <button type="button" class="ck__b ck__b--p" data-ck="todas">Aceptar analítica</button>
            @else
                <button type="button" class="ck__b ck__b--p" data-ck="necesarias">Entendido</button>
            @endif
        </div>
    </div>
</div>
<style>
    .ck{position:fixed;left:0;right:0;bottom:0;z-index:70;padding:16px}
    .ck__caja{max-width:860px;margin:0 auto;background:#fff;color:#0E1723;border:1px solid #DCE4EE;border-radius:16px;
              box-shadow:0 18px 48px rgba(10,17,25,.25);padding:20px 22px;display:grid;gap:12px}
    .ck__t{margin:0;font-size:1.05rem}
    .ck__p{margin:0;color:#3F4F65;font-size:.95rem}
    .ck__btns{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end}
    .ck__b{min-height:44px;padding:10px 20px;border-radius:999px;font:inherit;font-weight:700;cursor:pointer}
    .ck__b--p{background:#2E6EA8;color:#fff;border:0}
    .ck__b--p:hover{background:#215480}
    .ck__b--s{background:#fff;color:#0E1723;border:1.5px solid #8CA0B8}
    .ck__b--s:hover{border-color:#0E1723}
</style>
<script>
(function () {
    'use strict';
    var NOMBRE = 'mus_cookies', DIAS = 180, GA_ID = @json($ga);
    var caja = document.getElementById('ck');

    /** @returns {string|null} 'necesarias' | 'todas' | null */
    function leer() {
        var m = document.cookie.match(/(?:^|;\s*)mus_cookies=([^;]+)/);
        return m ? decodeURIComponent(m[1]) : null;
    }

    /** @param {string} valor */
    function guardar(valor) {
        var seguro = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = NOMBRE + '=' + encodeURIComponent(valor) + '; Max-Age=' + (DIAS * 86400) + '; Path=/; SameSite=Lax' + seguro;
    }

    function borrarAnalitica() {
        document.cookie.split(';').forEach(function (c) {
            var n = c.split('=')[0].trim();
            if (n.indexOf('_ga') === 0) {
                var dominio = location.hostname.replace(/^www\./, '');
                document.cookie = n + '=; Max-Age=0; Path=/';
                document.cookie = n + '=; Max-Age=0; Path=/; Domain=.' + dominio;
            }
        });
    }

    /** Inyecta GA4 una sola vez, con IP anonimizada y sin señales publicitarias. */
    function cargarAnalitica() {
        if (!GA_ID || window.__musGa) return;
        window.__musGa = true;
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', GA_ID, { anonymize_ip: true, allow_google_signals: false, allow_ad_personalization_signals: false });
        var s = document.createElement('script');
        s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA_ID);
        document.head.appendChild(s);
    }

    function mostrar() {
        caja.hidden = false;
        document.body.classList.add('cookies-visibles');
        document.documentElement.style.setProperty('--alto-cookies', caja.offsetHeight + 'px');
    }

    function ocultar() {
        caja.hidden = true;
        document.body.classList.remove('cookies-visibles');
    }

    caja.addEventListener('click', function (e) {
        var b = e.target.closest('[data-ck]');
        if (!b) return;
        var valor = b.getAttribute('data-ck');
        guardar(valor);
        if (valor === 'todas') { cargarAnalitica(); } else { borrarAnalitica(); }
        ocultar();
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-cookies-abrir]')) { mostrar(); caja.querySelector('button').focus(); }
    });

    var actual = leer();
    if (actual === 'todas') { cargarAnalitica(); }
    else if (actual === null) { mostrar(); }
})();
</script>
