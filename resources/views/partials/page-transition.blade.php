{{--
    Transición de salida hacia la pantalla de autenticación
    + aviso de sesión cerrada al aterrizar en el inicio.
    Se incluye al final de welcome.blade.php.
--}}

@if (session('mus_bye'))
    <div id="mus-toast" role="status">
        <span class="t-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 6 9 17l-5-5"/>
            </svg>
        </span>
        <span class="t-txt">
            <b>Sesión cerrada</b>
            <em>{{ session('mus_bye') }}</em>
        </span>
        <span class="t-bar"><i></i></span>
    </div>
@endif

<div id="mus-wipe" aria-hidden="true">
    <span class="mus-wipe__sheet"></span>
    <span class="mus-wipe__sheet"></span>
    <span class="mus-wipe__sheet"></span>
    <span class="mus-wipe__sheet"></span>
    <div class="mus-wipe__mark">
        <svg viewBox="0 0 48 48" fill="none" stroke="#4A8FC9" stroke-width="2.2"
             stroke-linejoin="round" stroke-linecap="round">
            <path class="p1" d="M8 16 L24 7 L40 16 L24 25 Z"/>
            <path class="p2" d="M8 16 v16 L24 41 V25"/>
            <path class="p3" d="M40 16 v16 L24 41"/>
        </svg>
        <span class="mus-wipe__txt">Abriendo acceso seguro</span>
    </div>
</div>

<style>
    /* ══════════ Barrido de salida ══════════ */
    #mus-wipe{
        position:fixed; inset:0; z-index:99999;
        pointer-events:none; display:flex;
    }
    #mus-wipe .mus-wipe__sheet{
        flex:1; height:100%;
        background:linear-gradient(165deg,#070D14,#142033);
        transform:translateY(-101%);
        transition:transform .78s cubic-bezier(.65,0,.35,1);
    }
    #mus-wipe .mus-wipe__sheet:nth-child(2){ transition-delay:.09s; }
    #mus-wipe .mus-wipe__sheet:nth-child(3){ transition-delay:.18s; }
    #mus-wipe .mus-wipe__sheet:nth-child(4){ transition-delay:.27s; }

    #mus-wipe .mus-wipe__mark{
        position:absolute; inset:0;
        display:flex; flex-direction:column; align-items:center; justify-content:center; gap:20px;
        opacity:0; transform:scale(.72);
        transition:opacity .4s ease .48s, transform .7s cubic-bezier(.34,1.56,.64,1) .48s;
    }
    #mus-wipe .mus-wipe__mark svg{ width:88px; height:88px; }
    #mus-wipe .mus-wipe__mark svg path{
        stroke-dasharray:240; stroke-dashoffset:240;
    }
    #mus-wipe .mus-wipe__txt{
        color:rgba(148,162,182,.5); font-size:11px;
        letter-spacing:.3em; text-transform:uppercase;
        font-family:'Inter',ui-sans-serif,system-ui,sans-serif;
        opacity:0;
    }

    #mus-wipe.is-on{ pointer-events:all; }
    #mus-wipe.is-on .mus-wipe__sheet{ transform:translateY(0); }
    #mus-wipe.is-on .mus-wipe__mark{ opacity:1; transform:none; }
    #mus-wipe.is-on .mus-wipe__mark svg path{
        animation:musWipeDraw .95s cubic-bezier(.22,1,.36,1) forwards;
    }
    #mus-wipe.is-on .mus-wipe__mark svg .p1{ animation-delay:.55s; }
    #mus-wipe.is-on .mus-wipe__mark svg .p2{ animation-delay:.75s; }
    #mus-wipe.is-on .mus-wipe__mark svg .p3{ animation-delay:.95s; }
    #mus-wipe.is-on .mus-wipe__txt{ animation:musWipeFade .5s ease 1.05s forwards; }
    @keyframes musWipeDraw{ to{ stroke-dashoffset:0; } }
    @keyframes musWipeFade{ to{ opacity:1; } }

    /* ══════════ Aviso de sesión cerrada ══════════ */
    #mus-toast{
        position:fixed; z-index:99998; right:22px; bottom:22px;
        display:flex; align-items:center; gap:12px;
        padding:13px 16px 13px 13px; border-radius:10px;
        background:rgba(10,17,25,.94);
        backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
        box-shadow:0 24px 50px -20px rgba(5,10,18,.9), 0 0 0 1px rgba(127,178,222,.16);
        font-family:'Inter',ui-sans-serif,system-ui,sans-serif;
        overflow:hidden;
        transform:translateY(26px) scale(.95); opacity:0;
        animation:toastIn .65s cubic-bezier(.34,1.56,.64,1) .35s forwards,
                  toastOut .5s cubic-bezier(.65,0,.35,1) 5.4s forwards;
    }
    @keyframes toastIn{ to{ transform:none; opacity:1; } }
    @keyframes toastOut{ to{ transform:translateY(20px) scale(.96); opacity:0; } }

    #mus-toast .t-ico{
        width:34px; height:34px; flex:none; border-radius:8px;
        display:grid; place-items:center; color:#fff;
        background:linear-gradient(135deg,#3E7D5C,#6BA487);
        box-shadow:0 8px 18px -8px rgba(16,185,129,.95);
    }
    #mus-toast .t-ico svg{ width:16px; height:16px;
                           stroke-dasharray:26; stroke-dashoffset:26;
                           animation:musWipeDraw .5s cubic-bezier(.22,1,.36,1) .75s forwards; }
    #mus-toast .t-txt b{ display:block; color:#fff; font-size:13px; font-weight:600; }
    #mus-toast .t-txt em{ display:block; font-style:normal; margin-top:2px;
                          color:rgba(148,162,182,.62); font-size:11.5px; }
    #mus-toast .t-bar{ position:absolute; left:0; right:0; bottom:0; height:2px;
                       background:rgba(255,255,255,.08); }
    #mus-toast .t-bar i{ display:block; height:100%; width:100%;
                         background:linear-gradient(90deg,#2E6EA8,#4A8FC9);
                         transform-origin:left; transform:scaleX(1);
                         animation:toastBar 5s linear .4s forwards; }
    @keyframes toastBar{ to{ transform:scaleX(0); } }

    @media (max-width:520px){
        #mus-toast{ left:16px; right:16px; bottom:16px; }
    }

    @media (prefers-reduced-motion:reduce){
        #mus-wipe{ display:none; }
        #mus-toast{ animation:none; opacity:1; transform:none; }
    }
</style>

<script>
(function () {
    var wipe = document.getElementById('mus-wipe');
    if (!wipe) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var targets = ['/login', '/register'];

    function isAuthLink(a) {
        if (!a || !a.getAttribute('href')) return false;
        if (a.target === '_blank' || a.hasAttribute('download')) return false;
        var url;
        try { url = new URL(a.href, window.location.origin); } catch (e) { return false; }
        if (url.origin !== window.location.origin) return false;
        for (var i = 0; i < targets.length; i++) {
            if (url.pathname === targets[i] || url.pathname.endsWith(targets[i])) return true;
        }
        return false;
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        var a = e.target.closest ? e.target.closest('a') : null;
        if (!isAuthLink(a)) return;
        e.preventDefault();
        wipe.classList.add('is-on');
        /* El portal mostrará su propia intro completa al llegar. */
        try { sessionStorage.removeItem('mus_intro'); } catch (err) {}
        setTimeout(function () { window.location.href = a.href; }, 1250);
    });

    window.addEventListener('pageshow', function (e) {
        if (e.persisted) wipe.classList.remove('is-on');
    });

    /* Retira el aviso del DOM cuando termina su animación de salida. */
    var toast = document.getElementById('mus-toast');
    if (toast) setTimeout(function () { if (toast.parentNode) toast.remove(); }, 6200);
})();
</script>
