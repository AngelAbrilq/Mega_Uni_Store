@php
    /* Datos puestos en sesión por los controladores de autenticación. */
    $musWelcome = session('mus_welcome');
    $musIsNew   = session('mus_welcome_new', false);

    /**
     * Petición hecha desde un modal.
     *
     * Cuando el JS abre un formulario dentro de una ventana flotante manda
     * la cabecera X-Mus-Modal. En ese caso no tiene sentido devolver la
     * página entera —menú, barra superior, animaciones—: se devuelve solo
     * el contenido, que es lo único que se va a inyectar.
     */
    $musEnModal = request()->hasHeader('X-Mus-Modal');

    /**
     * Apariencia, desde Configuración › Apariencia.
     *
     * El color de acento se aplica como variables CSS en línea sobre <html>
     * en vez de con una clase por color. Con la clase habría que escribir
     * cinco bloques de paleta y acordarse de tocarlos todos cada vez que se
     * agregue una variable; así la paleta vive en un array de PHP y el CSS
     * no se entera de que hay más de un color.
     */
    $musPaletas = [
        'azul'    => ['#1B4870', '#215480', '#2E6EA8', '#4A8FC9'],
        'verde'   => ['#174A36', '#1D5B42', '#2A7A58', '#48A37C'],
        'violeta' => ['#3D2A63', '#4A3378', '#5F4497', '#8268BF'],
        'ambar'   => ['#6B4A12', '#805A18', '#A3771F', '#C99C41'],
        'grafito' => ['#242B35', '#2E3641', '#414B59', '#5F6B7C'],
    ];

    $musAcento  = \App\Models\Setting::obtener('apariencia.acento', 'azul');
    $musTonos   = $musPaletas[$musAcento] ?? $musPaletas['azul'];
    $musEstilo  = '--a-700:' . $musTonos[0] . ';--a-600:' . $musTonos[1]
                . ';--a-500:' . $musTonos[2] . ';--a-400:' . $musTonos[3] . ';';

    $musClases = [];

    if (\App\Models\Setting::obtener('apariencia.densidad', 'comoda') === 'compacta') {
        $musClases[] = 'dens-compacta';
    }

    // La misma clase que ya usa la reducción de movimiento del sistema:
    // apagar las animaciones a mano debe hacer exactamente lo mismo que
    // pedirlas apagadas desde el computador.
    if (! \App\Models\Setting::activo('apariencia.animaciones')) {
        $musClases[] = 'no-motion';
    }
@endphp

@if ($musEnModal)
    <div class="mmod__contenido">
        @isset($header)
            <div class="mmod__enc">{{ $header }}</div>
        @endisset
        {{ $slot }}
    </div>
    @stack('styles')
    @stack('scripts')
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      @class($musClases) style="{{ $musEstilo }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Con qué negocio y qué local se dibujó esta pantalla. Lo lee el
         JS de más abajo para sellar cada formulario y que una pestaña
         vieja no escriba en el sitio equivocado. --}}
    <meta name="mus-ctx" content="{{ \App\Support\Contexto::firma() }}">
    <meta name="theme-color" content="#0E1723">

    <title>{{ config('app.name', 'MEGA U|I STORE') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Estado del sidebar aplicado ANTES de pintar, para que no haya salto. --}}
    <script>
        (function () {
            try {
                if (localStorage.getItem('mus_side') !== 'fijo') {
                    document.documentElement.classList.add('side-rail');
                }
            } catch (e) {
                document.documentElement.classList.add('side-rail');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* ══════════════════════════════════════════════════════════════
           MEGA UNI STORE · Shell del panel
           Paleta: navy profundo + azul acero. Degradados monocromáticos,
           bordes definidos, acento usado con avaricia.
           Todo el CSS es propio: no depende de recompilar Tailwind.
           ══════════════════════════════════════════════════════════════ */

        :root{
            /* Oscuros */
            --n-950:#070D14;
            --n-900:#0A1119;
            --n-850:#0E1723;
            --n-800:#142033;
            --n-750:#1A2A42;
            --n-700:#22334C;
            --n-600:#2C3F5C;

            /* Acento: azul acero sobrio */
            --a-700:#1B4870;
            --a-600:#215480;
            --a-500:#2E6EA8;
            --a-400:#4A8FC9;
            --a-300:#7FB2DE;

            /* Claros */
            --paper:#F1F4F8;
            --card:#FFFFFF;
            --line:#DFE5EC;
            --line-2:#EDF1F6;
            --ink:#101825;
            --ink-2:#33415A;
            --muted:#66768F;
            --muted-2:#94A2B6;

            /* Semánticos */
            --ok:#3E7D5C;
            --warn:#96703C;
            --bad:#96504F;

            --side-w:264px;
            --side-rail:78px;
            --side-cur:var(--side-w);
            --top-h:64px;

            --e-soft:cubic-bezier(.22,1,.36,1);
            --e-inout:cubic-bezier(.65,0,.35,1);
            --e-back:cubic-bezier(.34,1.4,.64,1);
        }

        html.side-rail{ --side-cur:var(--side-rail); }

        *,*::before,*::after{ box-sizing:border-box; }

        body.mus-body{
            margin:0;
            font-family:'Inter',ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;
            background:var(--paper);
            color:var(--ink);
            -webkit-font-smoothing:antialiased;
            overflow-x:hidden;
        }

        /* Grano fino sobre todo: rompe el aspecto plano y digital de los
           degradados y da textura de impresión. */
        body.mus-body::after{
            content:''; position:fixed; inset:0; z-index:9996;
            pointer-events:none; opacity:.05; mix-blend-mode:multiply;
            background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='140' height='140'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/></filter><rect width='140' height='140' filter='url(%23n)' opacity='.5'/></svg>");
        }

        /* ══════════ Estructura ══════════ */
        .mus-shell{ min-height:100vh; }

        .mus-main{
            margin-left:var(--side-cur);
            min-width:0; min-height:100vh;
            display:flex; flex-direction:column;
            transition:margin-left .4s var(--e-soft);
        }

        /* ══════════ Barra superior ══════════ */
        .mus-top{
            position:sticky; top:0; z-index:30;
            min-height:var(--top-h);
            display:flex; align-items:center; gap:16px;
            padding:12px 28px;
            background:rgba(241,244,248,.92);
            backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
            border-bottom:1px solid var(--line);
        }
        .mus-top__burger{
            display:none; width:38px; height:38px; flex:none;
            border:1px solid var(--line); border-radius:9px; background:var(--card);
            cursor:pointer; color:var(--ink-2);
            align-items:center; justify-content:center;
            transition:background .2s ease, border-color .2s ease;
        }
        .mus-top__burger:hover{ background:var(--line-2); border-color:var(--muted-2); }
        .mus-top__burger svg{ width:18px; height:18px; }
        .mus-top__slot{ flex:1; min-width:0; }

        /* Progreso de lectura */
        .mus-progress{
            position:absolute; left:0; bottom:-1px; height:2px; width:0;
            background:var(--a-500);
            transition:width .12s linear;
        }

        .mus-page{ flex:1; padding:28px; }

        @media (max-width:1024px){
            .mus-main{ margin-left:0; }
            .mus-top__burger{ display:inline-flex; }
            .mus-top{ padding:11px 16px; }
            .mus-page{ padding:18px 14px 40px; }
        }

        /* ══════════════════════════════════════════════════════════════
           SIDEBAR — riel de iconos que se despliega al pasar el mouse
           ══════════════════════════════════════════════════════════════ */
        .mus-side{
            position:fixed; inset:0 auto 0 0; z-index:60;
            width:var(--side-w);
            display:flex; flex-direction:column;
            background:linear-gradient(180deg,var(--n-900) 0%,var(--n-850) 60%,var(--n-800) 100%);
            border-right:1px solid rgba(255,255,255,.05);
            transition:width .38s var(--e-soft), transform .38s var(--e-soft),
                       box-shadow .38s ease;
            overflow:hidden;
        }
        html.side-rail .mus-side{ width:var(--side-rail); }
        html.side-rail.side-peek .mus-side{
            width:var(--side-w);
            box-shadow:22px 0 60px -26px rgba(0,0,0,.85);
        }

        /* Línea vertical de acento que respira, en vez de halos difusos */
        .mus-side__edge{
            position:absolute; right:0; top:0; bottom:0; width:1px;
            background:linear-gradient(180deg,transparent,var(--a-600) 22%,var(--a-500) 50%,var(--a-600) 78%,transparent);
            opacity:.5;
            animation:edgeBreathe 7s var(--e-inout) infinite;
        }
        @keyframes edgeBreathe{ 50%{ opacity:.95; } }

        /* --- Cabecera --- */
        .mus-side__head{
            position:relative; z-index:2;
            height:var(--top-h); flex:none;
            display:flex; align-items:center; gap:12px;
            padding:0 17px;
            border-bottom:1px solid rgba(255,255,255,.06);
        }
        .mus-logo{ display:flex; align-items:center; gap:12px; text-decoration:none; min-width:0; flex:1; }
        .mus-logo__mark{
            width:36px; height:36px; flex:none; border-radius:9px;
            display:grid; place-items:center;
            background:var(--a-600);
            border:1px solid var(--a-500);
            transition:background .3s ease, transform .4s var(--e-back);
        }
        .mus-logo:hover .mus-logo__mark{ background:var(--a-500); transform:translateY(-2px); }
        .mus-logo__mark svg{ width:20px; height:20px; }
        .mus-logo__text{
            min-width:0; overflow:hidden; white-space:nowrap;
            transition:opacity .22s ease, transform .3s var(--e-soft);
        }
        .mus-logo__text b{ display:block; font-size:11.4px; font-weight:700; letter-spacing:.1em;
                           color:#fff; overflow:hidden; text-overflow:ellipsis; }
        .mus-logo__text i{ display:block; font-style:normal; font-size:9.5px; letter-spacing:.18em;
                           text-transform:uppercase; color:var(--muted); margin-top:3px; }

        .mus-pin{
            width:28px; height:28px; flex:none; border-radius:7px; cursor:pointer;
            border:1px solid rgba(255,255,255,.1); background:transparent;
            color:var(--muted-2);
            display:grid; place-items:center;
            transition:background .2s ease, color .2s ease, border-color .2s ease,
                       opacity .22s ease;
        }
        .mus-pin:hover{ background:rgba(255,255,255,.07); color:#fff; border-color:rgba(255,255,255,.22); }
        .mus-pin svg{ width:14px; height:14px; transition:transform .38s var(--e-soft); }
        html.side-rail .mus-pin svg{ transform:rotate(180deg); }
        html:not(.side-rail) .mus-pin{ color:var(--a-400); border-color:var(--a-700); background:rgba(46,110,168,.14); }

        /* --- Navegación --- */
        .mus-nav{
            position:relative; z-index:2;
            flex:1; overflow-y:auto; overflow-x:hidden;
            padding:14px 11px 8px;
            scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.13) transparent;
        }
        .mus-nav::-webkit-scrollbar{ width:4px; }
        .mus-nav::-webkit-scrollbar-thumb{ background:rgba(255,255,255,.13); border-radius:2px; }

        .mus-nav__label{
            padding:0 11px; margin:18px 0 7px;
            font-size:9px; font-weight:700; letter-spacing:.24em; text-transform:uppercase;
            color:var(--muted); white-space:nowrap; overflow:hidden;
            transition:opacity .2s ease;
        }
        .mus-nav__label:first-child{ margin-top:2px; }
        .mus-nav__label span{ display:block; }

        /* En riel el título se sustituye por una regla corta */
        .mus-nav__rule{
            display:none; height:1px; margin:16px 14px 10px;
            background:rgba(255,255,255,.09);
        }

        .mus-item{
            position:relative;
            display:flex; align-items:center; gap:13px;
            height:42px; padding:0 11px; margin-bottom:2px;
            border-radius:8px; text-decoration:none; white-space:nowrap;
            color:var(--muted-2); font-size:13.2px; font-weight:500;
            transition:color .2s ease, background .2s ease;
        }
        .mus-item::before{
            content:''; position:absolute; left:-11px; top:50%;
            width:2px; height:0; border-radius:0 2px 2px 0;
            background:var(--a-400); transform:translateY(-50%);
            transition:height .3s var(--e-back);
        }
        .mus-item:hover{ color:#fff; background:rgba(255,255,255,.045); }
        .mus-item:hover::before{ height:15px; }

        .mus-item.is-active{ color:#fff; background:rgba(46,110,168,.16); }
        .mus-item.is-active::before{ height:22px; background:var(--a-500); }
        .mus-item.is-active .mus-item__ico{
            background:var(--a-600); border-color:var(--a-500); color:#fff;
        }

        .mus-item__ico{
            width:30px; height:30px; flex:none; border-radius:8px;
            display:grid; place-items:center;
            background:rgba(255,255,255,.05);
            border:1px solid rgba(255,255,255,.07);
            color:var(--muted-2);
            transition:background .22s ease, color .22s ease, border-color .22s ease;
        }
        .mus-item:hover .mus-item__ico{ color:#fff; border-color:rgba(255,255,255,.16); }
        .mus-item__ico svg{ width:15px; height:15px; }

        .mus-item__txt{ flex:1; min-width:0; overflow:hidden;
                        transition:opacity .2s ease, transform .3s var(--e-soft); }

        /* --- Usuario, arriba del menú --- */
        .mus-side__me{
            position:relative; z-index:4; flex:none;
            padding:11px; border-bottom:1px solid rgba(255,255,255,.06);
        }
        .mus-user{
            width:100%; display:flex; align-items:center; gap:11px;
            padding:8px 9px; border-radius:9px; cursor:pointer;
            border:1px solid rgba(255,255,255,.08); background:rgba(255,255,255,.035);
            color:#fff; font-family:inherit; text-align:left;
            transition:background .22s ease, border-color .22s ease;
        }
        .mus-user:hover{ background:rgba(255,255,255,.08); border-color:rgba(255,255,255,.18); }
        /* Redondo, con un aro fino para que la foto no se pegue al fondo */
        .mus-user__av{
            box-shadow:0 0 0 1px rgba(255,255,255,.16), 0 4px 12px -6px rgba(0,0,0,.8);
        }
        .mus-user__info{ flex:1; min-width:0; overflow:hidden;
                         transition:opacity .2s ease, transform .3s var(--e-soft); }
        .mus-user__info b{ display:block; font-size:12.4px; font-weight:600;
                           white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mus-user__info span{ display:block; font-size:10.4px; color:var(--muted);
                              white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:1px; }
        .mus-user__caret{ flex:none; width:13px; height:13px; color:var(--muted);
                          transition:transform .3s var(--e-soft), opacity .2s ease; }
        .mus-user.is-open .mus-user__caret{ transform:rotate(180deg); }

        /* El menú ahora cuelga hacia abajo, porque el botón está arriba */
        .mus-menu{
            position:absolute; left:11px; right:11px; top:calc(100% - 4px);
            background:var(--card); border-radius:10px; padding:5px;
            border:1px solid var(--line);
            box-shadow:0 22px 48px -20px rgba(5,15,30,.75);
            opacity:0; transform:translateY(-8px); pointer-events:none;
            transition:opacity .22s ease, transform .28s var(--e-soft);
        }
        .mus-menu.is-open{ opacity:1; transform:none; pointer-events:auto; }
        .mus-menu a, .mus-menu button{
            width:100%; display:flex; align-items:center; gap:10px;
            padding:9px 10px; border-radius:7px;
            border:none; background:none; cursor:pointer;
            font-family:inherit; font-size:12.8px; color:var(--ink-2); text-align:left; text-decoration:none;
            transition:background .16s ease, color .16s ease;
        }
        .mus-menu a:hover, .mus-menu button:hover{ background:var(--line-2); color:var(--ink); }
        .mus-menu svg{ width:14px; height:14px; flex:none; color:var(--muted-2); }
        .mus-menu .danger{ color:var(--bad); }
        .mus-menu .danger svg{ color:var(--bad); }
        .mus-menu .danger:hover{ background:#FBF0F0; }
        .mus-menu hr{ border:none; border-top:1px solid var(--line); margin:4px 8px; }

        /* ── Selector de idioma dentro del menú de usuario ──
           Los botones son diminutos a propósito: es un ajuste que se toca
           una vez y no debe competir con «Mi perfil» ni con «Cerrar
           sesión», que son las opciones que la gente viene a buscar. */
        .mus-lang{ display:flex; align-items:center; justify-content:space-between;
                   gap:10px; padding:5px 10px 6px; }
        .mus-lang__lab{ font-size:11.6px; color:var(--muted); }
        .mus-lang__ops{ display:flex; gap:3px; padding:2px;
                        background:var(--line-2); border-radius:8px; }
        .mus-lang__ops form{ margin:0; }
        /* Gana en especificidad a «.mus-menu button», que los estiraría al
           100% del ancho y los dejaría uno encima de otro. */
        .mus-menu .mus-lang__b{
            width:auto; padding:4px 9px; border-radius:6px;
            font-size:11px; font-weight:700; letter-spacing:.06em;
            color:var(--muted); background:transparent;
        }
        .mus-menu .mus-lang__b:hover{ background:var(--card); color:var(--ink); }
        .mus-menu .mus-lang__b.is-on{ background:var(--card); color:var(--a-600);
                                      box-shadow:0 1px 2px rgba(16,24,37,.09); }

        /* --- Modo riel (contraído) --- */
        html.side-rail:not(.side-peek) .mus-logo__text,
        html.side-rail:not(.side-peek) .mus-item__txt,
        html.side-rail:not(.side-peek) .mus-user__info,
        html.side-rail:not(.side-peek) .mus-user__caret,
        html.side-rail:not(.side-peek) .mus-nav__label{
            opacity:0; pointer-events:none;
        }
        html.side-rail:not(.side-peek) .mus-nav__label{ height:0; margin:0; }
        html.side-rail:not(.side-peek) .mus-nav__rule{ display:block; }
        html.side-rail:not(.side-peek) .mus-side__head{ padding:0 20px; }
        html.side-rail:not(.side-peek) .mus-pin{ opacity:0; pointer-events:none; }
        html.side-rail:not(.side-peek) .mus-item{ padding:0 12px; }
        /* En riel solo queda la foto, sola y centrada en los 78px: sin
           recuadro (se veía cortado contra el borde) y sin el hueco que
           dejaban el nombre y la flecha aunque estuvieran invisibles. */
        html.side-rail:not(.side-peek) .mus-side__me{ padding:11px 0; }
        html.side-rail:not(.side-peek) .mus-user{
            width:auto; margin:0 auto; padding:4px; gap:0;
            justify-content:center;
            border-color:transparent; background:transparent;
        }
        html.side-rail:not(.side-peek) .mus-user:hover{
            border-color:transparent; background:transparent;
        }
        html.side-rail:not(.side-peek) .mus-user:hover .mus-user__av{
            box-shadow:0 0 0 2px var(--a-500), 0 4px 12px -6px rgba(0,0,0,.8);
        }
        html.side-rail:not(.side-peek) .mus-user__info,
        html.side-rail:not(.side-peek) .mus-user__caret{ display:none; }
        html.side-rail:not(.side-peek) .mus-menu{ opacity:0 !important; pointer-events:none !important; }

        /* --- Móvil: cajón --- */
        .mus-backdrop{
            position:fixed; inset:0; z-index:55;
            background:rgba(7,13,20,.62);
            opacity:0; pointer-events:none; transition:opacity .3s ease;
        }
        body.side-open .mus-backdrop{ opacity:1; pointer-events:auto; }

        @media (max-width:1024px){
            html.side-rail{ --side-cur:0px; }
            .mus-side, html.side-rail .mus-side, html.side-rail.side-peek .mus-side{
                width:264px; transform:translateX(-100%); box-shadow:0 0 50px rgba(0,0,0,.5);
            }
            body.side-open .mus-side{ transform:translateX(0); }
            html.side-rail:not(.side-peek) .mus-logo__text,
            html.side-rail:not(.side-peek) .mus-item__txt,
            html.side-rail:not(.side-peek) .mus-user__info,
            html.side-rail:not(.side-peek) .mus-user__caret,
            html.side-rail:not(.side-peek) .mus-nav__label{ opacity:1; pointer-events:auto; }
            /* En el cajón del celular el bloque de usuario va completo */
            html.side-rail:not(.side-peek) .mus-side__me{ padding:11px; }
            html.side-rail:not(.side-peek) .mus-user{
                width:100%; margin:0; padding:8px 9px; gap:11px;
                justify-content:flex-start;
                border-color:rgba(255,255,255,.08); background:rgba(255,255,255,.035);
            }
            html.side-rail:not(.side-peek) .mus-user__info{ display:block; }
            html.side-rail:not(.side-peek) .mus-user__caret{ display:block; }
            html.side-rail:not(.side-peek) .mus-nav__label{ height:auto; margin:18px 0 7px; }
            html.side-rail:not(.side-peek) .mus-nav__rule{ display:none; }
            html.side-rail:not(.side-peek) .mus-menu{ opacity:0; }
            html.side-rail:not(.side-peek) .mus-menu.is-open{ opacity:1 !important; pointer-events:auto !important; }
            .mus-pin{ display:none; }
        }

        /* Entrada escalonada del sidebar */
        .mus-item, .mus-nav__label{ opacity:0; transform:translateX(-10px); }
        body.mus-in .mus-item, body.mus-in .mus-nav__label{
            animation:sideIn .45s var(--e-soft) forwards;
        }
        @keyframes sideIn{ to{ opacity:1; transform:none; } }
        html.side-rail:not(.side-peek) body.mus-in .mus-nav__label{ opacity:0; }

        /* ══════════════════════════════════════════════════════════════
           PUERTA DE BIENVENIDA
           ══════════════════════════════════════════════════════════════ */
        #mus-gate{
            position:fixed; inset:0; z-index:9999;
            display:grid; place-items:center; overflow:hidden;
            background:var(--n-950);
        }
        /* Rejilla en perspectiva, sin orbes difusos */
        #mus-gate .g-grid{
            position:absolute; inset:-40%;
            background-image:
                linear-gradient(rgba(46,110,168,.14) 1px, transparent 1px),
                linear-gradient(90deg, rgba(46,110,168,.14) 1px, transparent 1px);
            background-size:58px 58px;
            transform:perspective(700px) rotateX(62deg) translateY(-6%);
            mask-image:radial-gradient(ellipse 52% 48% at 50% 46%, #000 8%, transparent 72%);
            -webkit-mask-image:radial-gradient(ellipse 52% 48% at 50% 46%, #000 8%, transparent 72%);
            animation:gGrid 20s linear infinite;
        }
        @keyframes gGrid{ to{ background-position:0 58px, 58px 0; } }

        #mus-gate .g-wash{
            position:absolute; inset:0;
            background:
                radial-gradient(ellipse 44% 38% at 50% 48%, rgba(46,110,168,.42), transparent 72%),
                radial-gradient(ellipse 76% 64% at 50% 50%, rgba(27,72,112,.55), transparent 74%);
            animation:washBreathe 6s var(--e-inout) infinite;
        }
        @keyframes washBreathe{ 50%{ opacity:.72; transform:scale(1.06); } }

        #mus-gate .g-ring{
            position:absolute; border-radius:50%; pointer-events:none;
            border:1px solid rgba(122,170,214,.16);
        }
        #mus-gate .g-ring.r1{ width:300px; height:300px; animation:gPulse 3.8s var(--e-soft) infinite; }
        #mus-gate .g-ring.r2{ width:480px; height:480px; animation:gPulse 3.8s var(--e-soft) .6s infinite; }
        #mus-gate .g-ring.r3{ width:680px; height:680px; animation:gPulse 3.8s var(--e-soft) 1.2s infinite; }
        @keyframes gPulse{
            0%{ transform:scale(.88); opacity:0; }
            32%{ opacity:.9; }
            100%{ transform:scale(1.16); opacity:0; }
        }

        #mus-gate .g-in{ position:relative; z-index:8; text-align:center; padding:0 24px;
                         transition:opacity .5s ease, transform .6s var(--e-soft); }

        .mus-check{ width:98px; height:98px; margin:0 auto 30px; display:block; }
        .mus-check .ring{
            fill:none; stroke:rgba(122,170,214,.28); stroke-width:2;
            stroke-dasharray:283; stroke-dashoffset:283;
            animation:musDraw .95s var(--e-soft) .25s forwards;
        }
        .mus-check .tick{
            fill:none; stroke:var(--a-400); stroke-width:3.4;
            stroke-linecap:round; stroke-linejoin:round;
            stroke-dasharray:52; stroke-dashoffset:52;
            animation:musDraw .6s var(--e-soft) 1.05s forwards;
        }
        @keyframes musDraw{ to{ stroke-dashoffset:0; } }

        #mus-gate h2{
            margin:0; color:#fff; font-size:clamp(22px,4.4vw,31px); font-weight:600;
            letter-spacing:-.015em;
            opacity:0; transform:translateY(18px);
            animation:musUp .75s var(--e-soft) 1.5s forwards;
        }
        #mus-gate .g-sub{
            margin:12px 0 0; color:var(--muted-2); font-size:13.5px; font-weight:400;
            opacity:0; transform:translateY(14px);
            animation:musUp .7s var(--e-soft) 1.75s forwards;
        }
        @keyframes musUp{ to{ opacity:1; transform:none; } }

        #mus-gate .g-bar{
            width:220px; height:1.5px; margin:32px auto 0;
            background:rgba(255,255,255,.1); overflow:hidden;
            opacity:0; animation:musFade .5s ease 1.9s forwards;
        }
        #mus-gate .g-bar i{
            display:block; height:100%; width:0; background:var(--a-400);
            animation:musLoad 1.75s var(--e-inout) 1.95s forwards;
        }
        @keyframes musLoad{ to{ width:100%; } }
        @keyframes musFade{ to{ opacity:1; } }

        #mus-gate .g-status{
            margin:14px 0 0; height:14px;
            color:var(--muted); font-size:10.5px;
            letter-spacing:.26em; text-transform:uppercase;
            opacity:0; animation:musFade .5s ease 2s forwards;
        }
        #mus-gate .g-skip{
            position:absolute; bottom:30px; left:0; right:0; text-align:center; z-index:8;
            color:rgba(148,162,182,.42); font-size:10.5px; letter-spacing:.18em; text-transform:uppercase;
            opacity:0; animation:musFade .5s ease 2.9s forwards;
        }

        #mus-gate.is-gone{ pointer-events:none; }
        #mus-gate.is-gone .g-in{ opacity:0; transform:scale(1.08); }
        #mus-gate .g-sheet{
            position:absolute; left:0; width:100%; height:50.5%; z-index:6;
            background:var(--n-950); opacity:0;
            transition:transform 1.05s var(--e-soft) .18s;
        }
        #mus-gate .g-sheet.t{ top:0; }
        #mus-gate .g-sheet.b{ bottom:0; }
        #mus-gate.is-gone .g-sheet{ opacity:1; }
        #mus-gate.is-gone .g-sheet.t{ transform:translateY(-100%); }
        #mus-gate.is-gone .g-sheet.b{ transform:translateY(100%); }

        /* ══════════════════════════════════════════════════════════════
           CIERRE DE SESIÓN — desmontaje del sistema
           ══════════════════════════════════════════════════════════════ */
        #mus-bye{
            position:fixed; inset:0; z-index:9998;
            display:grid; place-items:center; overflow:hidden;
            background:var(--n-950);
            opacity:0; pointer-events:none;
            transition:opacity .4s ease;
        }
        #mus-bye.show{ opacity:1; pointer-events:auto; }

        #mus-bye .b-wash{
            position:absolute; inset:0;
            background:
                radial-gradient(ellipse 40% 36% at 50% 46%, rgba(46,110,168,.4), transparent 72%),
                radial-gradient(ellipse 74% 62% at 50% 50%, rgba(27,72,112,.5), transparent 74%);
            opacity:0;
        }
        #mus-bye.show .b-wash{ animation:byeWashOut 2.6s var(--e-inout) forwards; }
        @keyframes byeWashOut{
            0%{ opacity:1; }
            70%{ opacity:.85; }
            100%{ opacity:.15; }
        }

        #mus-bye .b-grid{
            position:absolute; inset:-40%;
            background-image:
                linear-gradient(rgba(74,143,201,.2) 1px, transparent 1px),
                linear-gradient(90deg, rgba(74,143,201,.2) 1px, transparent 1px);
            background-size:52px 52px;
            transform:perspective(700px) rotateX(62deg);
            mask-image:radial-gradient(ellipse 54% 50% at 50% 44%, #000 6%, transparent 74%);
            -webkit-mask-image:radial-gradient(ellipse 54% 50% at 50% 44%, #000 6%, transparent 74%);
            opacity:0;
        }
        #mus-bye.show .b-grid{ animation:byeGridOut 2.6s var(--e-inout) forwards; }
        @keyframes byeGridOut{
            0%{ opacity:.9; transform:perspective(700px) rotateX(62deg) scale(1); }
            100%{ opacity:0; transform:perspective(700px) rotateX(62deg) scale(1.5); }
        }

        #mus-bye .b-stage{ position:relative; width:200px; height:200px; margin:0 auto 8px; }

        /* Arcos contrarrotantes */
        #mus-bye .b-arc{
            position:absolute; inset:0; margin:auto; border-radius:50%;
            border:1.5px solid transparent;
        }
        #mus-bye .b-arc.a1{ width:150px; height:150px; border-top-color:var(--a-300);
                            border-width:2px; }
        #mus-bye .b-arc.a2{ width:186px; height:186px; border-bottom-color:var(--a-400);
                            border-left-color:rgba(74,143,201,.5); }
        #mus-bye .b-arc.a3{ width:116px; height:116px; border-right-color:rgba(127,178,222,.85); }
        #mus-bye.show .b-arc.a1{ animation:spinCW 2.4s linear infinite; }
        #mus-bye.show .b-arc.a2{ animation:spinCCW 3.6s linear infinite; }
        #mus-bye.show .b-arc.a3{ animation:spinCW 1.7s linear infinite; }
        @keyframes spinCW{ to{ transform:rotate(360deg); } }
        @keyframes spinCCW{ to{ transform:rotate(-360deg); } }

        /* Marca que se dibuja y luego se desintegra */
        #mus-bye .b-mark{
            position:absolute; inset:0; margin:auto; width:66px; height:66px;
        }
        #mus-bye .b-mark path{
            fill:none; stroke:var(--a-300); stroke-width:2;
            stroke-linecap:round; stroke-linejoin:round;
            stroke-dasharray:120; stroke-dashoffset:120;
        }
        #mus-bye.show .b-mark path{ animation:musDraw .7s var(--e-soft) forwards; }
        #mus-bye.show .b-mark .m2{ animation-delay:.16s; }
        #mus-bye.show .b-mark .m3{ animation-delay:.32s; }
        #mus-bye.show .b-mark{ animation:markOut .9s var(--e-soft) 1.05s forwards; }
        @keyframes markOut{
            0%{ opacity:1; transform:scale(1); filter:blur(0); }
            100%{ opacity:0; transform:scale(1.35); filter:blur(4px); }
        }

        /* Línea de barrido que "corta" la sesión */
        #mus-bye .b-scan{
            position:absolute; left:-12%; right:-12%; top:50%; height:1px;
            background:linear-gradient(90deg,transparent,var(--a-300),transparent);
            opacity:0;
        }
        #mus-bye.show .b-scan{ animation:scanSweep .85s var(--e-inout) .95s forwards; }
        @keyframes scanSweep{
            0%{ opacity:0; transform:scaleX(.1); }
            35%{ opacity:1; transform:scaleX(1); }
            100%{ opacity:0; transform:scaleX(1); }
        }

        /* Partículas que salen despedidas */
        #mus-bye .b-dot{
            position:absolute; left:50%; top:50%; width:3px; height:3px; margin:-1.5px;
            border-radius:50%; background:var(--a-300); opacity:0;
        }
        #mus-bye.show .b-dot{ animation:dotFly 1.1s var(--e-soft) 1.15s forwards; }
        @keyframes dotFly{
            0%{ opacity:0; transform:rotate(var(--a)) translateX(18px) scale(.4); }
            25%{ opacity:1; }
            100%{ opacity:0; transform:rotate(var(--a)) translateX(110px) scale(1); }
        }

        #mus-bye .b-title{
            margin:0; color:#fff; font-size:19px; font-weight:600; letter-spacing:.01em;
            display:flex; justify-content:center; gap:.02em; flex-wrap:wrap;
        }
        #mus-bye .b-title span{ display:inline-block; opacity:0; transform:translateY(14px); }
        #mus-bye .b-title .sp{ width:.32em; }
        #mus-bye.show .b-title span{ animation:musUp .55s var(--e-soft) forwards; }

        #mus-bye .b-sub{
            margin:10px 0 0; color:var(--muted); font-size:10.5px;
            letter-spacing:.28em; text-transform:uppercase;
            opacity:0;
        }
        #mus-bye.show .b-sub{ animation:musFade .5s ease 1.35s forwards; }

        #mus-bye .b-bar{ width:170px; height:1.5px; margin:26px auto 0;
                         background:rgba(255,255,255,.1); overflow:hidden; }
        #mus-bye .b-bar i{ display:block; height:100%; width:0; background:var(--a-400); }
        #mus-bye.show .b-bar i{ animation:musLoad 1.5s var(--e-inout) .2s forwards; }

        /* ══════════════════════════════════════════════════════════════
           REVELADO POR SCROLL
           ══════════════════════════════════════════════════════════════ */
        [data-reveal]{
            opacity:0;
            transform:translateY(24px);
            transition:opacity .7s var(--e-soft), transform .8s var(--e-soft),
                       filter .7s var(--e-soft), clip-path .9s var(--e-soft);
            will-change:opacity, transform;
        }
        [data-reveal="left"]{ transform:translateX(-26px); }
        [data-reveal="right"]{ transform:translateX(26px); }
        [data-reveal="zoom"]{ transform:scale(.955); }
        [data-reveal="blur"]{ transform:translateY(16px); filter:blur(9px); }
        [data-reveal="wipe"]{ transform:none; clip-path:inset(0 0 100% 0); }
        [data-reveal].is-seen{
            opacity:1; transform:none; filter:none; clip-path:inset(0 0 0 0);
        }

        html.no-motion [data-reveal]{ opacity:1 !important; transform:none !important;
                                      filter:none !important; clip-path:none !important; }

        /* Animaciones apagadas desde Configuración: además de mostrar los
           paneles ya revelados, se cortan las transiciones. Sin esto el
           panel aparece de golpe pero los botones siguen animándose y la
           sensación es de algo a medio apagar. */
        html.no-motion *, html.no-motion *::before, html.no-motion *::after{
            animation-duration:.01ms !important; animation-iteration-count:1 !important;
            transition-duration:.01ms !important;
        }

        /* ── Densidad compacta ──
           Solo cambia el aire vertical. Reducir también el tamaño de letra
           haría la tabla más difícil de leer, que es lo contrario de lo que
           busca quien pide ver más filas de una. */
        html.dens-compacta .mt thead th{ padding-top:7px; padding-bottom:7px; }
        html.dens-compacta .mt tbody td{ padding-top:7px; padding-bottom:7px; }
        html.dens-compacta .mp__body{ padding-top:14px; padding-bottom:14px; }

        @media (prefers-reduced-motion:reduce){
            *,*::before,*::after{
                animation-duration:.01ms !important; animation-iteration-count:1 !important;
                transition-duration:.01ms !important;
            }
            [data-reveal]{ opacity:1 !important; transform:none !important;
                           filter:none !important; clip-path:none !important; }
        }

        /* ══════════════════════════════════════════════════════════════
           SISTEMA DE INTERFAZ — botones, tablas, formularios, avisos
           ══════════════════════════════════════════════════════════════ */

        /* --- Foco visible: señal de software serio, no decorativa --- */
        :focus{ outline:none; }
        :focus-visible{
            outline:2px solid var(--a-500);
            outline-offset:2px;
            border-radius:4px;
        }
        .mus-side :focus-visible{ outline-color:var(--a-300); }

        ::selection{ background:rgba(46,110,168,.22); }

        /* --- Encabezado de página --- */
        .mh{ display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between;
             gap:14px; width:100%; }
        .mh__l{ display:flex; align-items:center; gap:13px; min-width:0; }
        .mh__ico{
            width:38px; height:38px; flex:none; border-radius:9px;
            display:grid; place-items:center;
            background:var(--card); border:1px solid var(--line); color:var(--a-500);
        }
        .mh__ico svg{ width:18px; height:18px; }
        .mh__t{ min-width:0; }
        .mh__t h1{ margin:0; font-size:19px; font-weight:700; letter-spacing:-.02em; color:var(--ink);
                   white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mh__t p{ margin:2px 0 0; font-size:12.6px; color:var(--muted); }
        .mh__r{ display:flex; align-items:center; gap:9px; flex:none; }

        /* Migas de pan */
        .mcrumb{ display:flex; align-items:center; gap:7px; font-size:11.5px; color:var(--muted-2);
                 margin-bottom:3px; }
        .mcrumb a{ color:var(--muted); text-decoration:none; transition:color .18s ease; }
        .mcrumb a:hover{ color:var(--a-500); }
        .mcrumb svg{ width:11px; height:11px; opacity:.6; }

        /* --- Botones --- */
        .mb{
            position:relative; overflow:hidden;
            display:inline-flex; align-items:center; justify-content:center; gap:8px;
            height:38px; padding:0 16px; border-radius:8px;
            border:1px solid transparent; cursor:pointer; text-decoration:none;
            font-family:inherit; font-size:13px; font-weight:600; letter-spacing:.005em;
            white-space:nowrap;
            transition:background .2s ease, border-color .2s ease, color .2s ease,
                       transform .16s var(--e-soft), box-shadow .2s ease;
        }
        .mb svg{ width:15px; height:15px; flex:none; transition:transform .3s var(--e-back); }
        .mb:active{ transform:translateY(1px) scale(.985); }

        .mb--primary{ background:var(--a-500); color:#fff;
                      box-shadow:0 1px 2px rgba(16,24,37,.14), 0 6px 16px -10px rgba(46,110,168,.9); }
        .mb--primary:hover{ background:var(--a-600); box-shadow:0 2px 4px rgba(16,24,37,.16), 0 10px 22px -12px rgba(46,110,168,1); }

        .mb--ghost{ background:var(--card); color:var(--ink-2); border-color:var(--line); }
        .mb--ghost:hover{ background:var(--line-2); border-color:var(--muted-2); color:var(--ink); }

        .mb--quiet{ background:transparent; color:var(--muted); }
        .mb--quiet:hover{ background:var(--line-2); color:var(--ink); }

        .mb--danger{ background:var(--card); color:var(--bad); border-color:#E9D7D7; }
        .mb--danger:hover{ background:#FBF0F0; border-color:#DCBCBC; }

        .mb--sm{ height:32px; padding:0 11px; font-size:12.2px; border-radius:7px; }
        .mb--icon{ width:32px; height:32px; padding:0; border-radius:7px; }
        .mb--icon svg{ width:15px; height:15px; }
        .mb--block{ width:100%; }

        /* Onda al pulsar, del mismo lenguaje que el login */
        .mb__wave{
            position:absolute; width:12px; height:12px; border-radius:50%;
            background:currentColor; opacity:.22; pointer-events:none;
            transform:translate(-50%,-50%) scale(0);
            animation:mbWave .62s var(--e-soft) forwards;
        }
        @keyframes mbWave{ to{ transform:translate(-50%,-50%) scale(22); opacity:0; } }

        /* --- Panel / tarjeta --- */
        .mp{
            background:var(--card); border:1px solid var(--line); border-radius:12px;
            transition:box-shadow .35s ease, border-color .3s ease;
        }
        .mp--pad{ padding:20px; }
        .mp__head{
            display:flex; align-items:center; justify-content:space-between; gap:14px;
            padding:15px 18px; border-bottom:1px solid var(--line-2);
        }
        /* --- Avatar: foto o iniciales ---
           El cuadro es siempre cuadrado y la foto va con object-fit:cover,
           así que se recorta y se centra en vez de estirarse. Da igual si
           la suben vertical, horizontal, JPG, PNG o WEBP. */
        .mav{
            position:relative; display:inline-grid; place-items:center;
            width:var(--av,34px); height:var(--av,34px); flex:none;
            border-radius:var(--avr,9px); overflow:hidden;
            background:var(--avc,var(--a-600)); color:#fff;
            font-weight:700; line-height:1; user-select:none;
            font-size:calc(var(--av,34px) * .38);
        }
        /* La foto se ancla a los cuatro lados del cuadro. Si se dejara en
           width/height 100% dentro del grid, una foto muy alta estiraba la
           casilla y se salía; anclada, la caja manda siempre. */
        .mav img{
            position:absolute; inset:0; width:100%; height:100%; display:block;
            object-fit:cover; object-position:center;
        }
        .mav i{ font-style:normal; letter-spacing:.02em; }

        /* ══════════════════════════════════════════════════════════════
           CIFRAS Y CÓDIGOS — la firma tipográfica del sistema
           Los números van con cifras de ancho fijo: el 1 ocupa lo mismo
           que el 8, así una columna de precios queda perfectamente
           alineada. Los códigos (SKU, número de venta, NIT) van en
           monoespaciada, que es como se leen los códigos en todos lados.
           ══════════════════════════════════════════════════════════════ */
        .cifra,
        .mt thead th.num, .mt tbody td.num,
        .mt__id{
            font-variant-numeric:tabular-nums;
            font-feature-settings:'tnum' 1, 'lnum' 1;
        }
        .cifra{ letter-spacing:-.02em; }

        /* El signo de peso es contexto, no dato: va más pequeño y en gris */
        .moneda{
            font-size:.66em; font-weight:500; color:var(--muted-2);
            margin-right:1px; letter-spacing:0;
        }

        .codigo, .mt__id{
            font-family:ui-monospace,'SF Mono',SFMono-Regular,Menlo,Consolas,monospace;
            font-size:.93em; letter-spacing:-.01em;
        }

        /* ══════════════════════════════════════════════════════════════
           MODALES — abrir un formulario sin salir de la lista
           El contenido llega por fetch desde la misma ruta de siempre
           (products.create, customers.create…). El servidor lo detecta por
           la cabecera X-Mus-Modal y devuelve solo el formulario.
           ══════════════════════════════════════════════════════════════ */
        .mmod{
            position:fixed; inset:0; z-index:9500;
            display:flex; align-items:flex-start; justify-content:center;
            padding:52px 20px 30px; overflow-y:auto;
            background:rgba(7,13,20,.52);
            backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px);
            opacity:0; pointer-events:none;
            transition:opacity .24s ease;
        }
        .mmod.is-on{ opacity:1; pointer-events:auto; }

        .mmod__caja{
            position:relative; width:100%; max-width:var(--mmw,760px);
            background:var(--paper); border:1px solid var(--line); border-radius:14px;
            box-shadow:0 40px 100px -34px rgba(7,13,20,.7);
            transform:translateY(14px) scale(.985);
            transition:transform .34s var(--e-back);
            overflow:hidden;
        }
        .mmod.is-on .mmod__caja{ transform:none; }

        .mmod__barra{
            display:flex; align-items:center; gap:14px;
            padding:14px 18px; background:var(--card);
            border-bottom:1px solid var(--line);
        }
        .mmod__barra h2{
            margin:0; flex:1; min-width:0;
            font-size:15px; font-weight:650; letter-spacing:-.015em; color:var(--ink);
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
        }
        .mmod__x{
            flex:none; width:32px; height:32px; display:grid; place-items:center;
            border:1px solid var(--line); border-radius:8px; background:var(--card);
            color:var(--muted); cursor:pointer;
            transition:background .18s ease, color .18s ease, border-color .18s ease;
        }
        .mmod__x:hover{ background:#FBF0F0; color:var(--bad); border-color:#E9D7D7; }
        .mmod__x svg{ width:15px; height:15px; }

        .mmod__cuerpo{ padding:18px; max-height:calc(100vh - 190px); overflow-y:auto; }

        /* Dentro del modal la ficha ya no necesita su propia cabecera:
           el título vive en la barra de arriba. */
        .mmod__cuerpo .mmod__enc{ display:none; }
        .mmod__cuerpo .mp{ border:none; background:transparent; }
        .mmod__cuerpo .mp__head{ padding:0 0 12px; }
        .mmod__cuerpo .mp__body{ padding:0; }
        .mmod__cuerpo .mp + .mp{ margin-top:20px; padding-top:20px; border-top:1px solid var(--line); }
        .mmod__cuerpo .mp__foot{ display:none; }

        /* Barra de acciones propia, siempre visible al pie del modal */
        .mmod__pie{
            display:flex; align-items:center; justify-content:flex-end; gap:9px;
            padding:13px 18px; background:var(--card); border-top:1px solid var(--line);
        }
        .mmod__pie .mmod__aviso{ margin-right:auto; font-size:12.4px; color:var(--muted); }

        .mmod__cargando{
            display:grid; place-items:center; gap:12px; padding:64px 0; color:var(--muted-2);
        }
        .mmod__spin{
            width:26px; height:26px; border-radius:50%;
            border:2px solid var(--line); border-top-color:var(--a-500);
            animation:mmodSpin .75s linear infinite;
        }
        @keyframes mmodSpin{ to{ transform:rotate(360deg); } }
        .mmod__cargando p{ margin:0; font-size:12.8px; }

        @media (max-width:760px){
            .mmod{ padding:0; align-items:stretch; }
            .mmod__caja{ max-width:none; border-radius:0; border:0; min-height:100%;
                         display:flex; flex-direction:column; }
            .mmod__barra{ position:sticky; top:0; z-index:2; }
            .mmod__cuerpo{ flex:1; max-height:none; padding:16px 14px; }
            .mmod__pie{ position:sticky; bottom:0; }
            .mmod__pie .mb{ flex:1; justify-content:center; }
            .mmod__pie .mmod__aviso{ display:none; }
        }

        /* Cabecera con foto en las fichas de detalle */
        .mficha{
            display:flex; align-items:center; gap:14px;
            padding-bottom:15px; margin-bottom:15px;
            border-bottom:1px solid var(--line-2);
        }
        .mficha__t{ min-width:0; }
        .mficha__t b{ display:block; font-size:15.5px; font-weight:700; color:var(--ink);
                      letter-spacing:-.015em; overflow-wrap:anywhere; }
        .mficha__t span{ display:block; margin-top:2px; font-size:12.4px; color:var(--muted); }

        .mp__tit{ min-width:0; }
        .mp__tools{ display:flex; align-items:center; gap:9px; min-width:0; }
        .mp__head h3{ margin:0; font-size:14px; font-weight:700; color:var(--ink); letter-spacing:-.01em; }
        .mp__head p{ margin:2px 0 0; font-size:12px; color:var(--muted-2); }
        .mp__body{ padding:18px; }
        .mp__foot{ padding:14px 18px; border-top:1px solid var(--line-2);
                   display:flex; align-items:center; justify-content:flex-end; gap:9px; }

        /* --- Barra de herramientas de la tabla --- */
        .mtool{
            display:flex; flex-wrap:wrap; align-items:center; gap:10px;
            padding:13px 16px; border-bottom:1px solid var(--line-2);
        }
        .mtool__search{ position:relative; flex:1; min-width:190px; max-width:340px; }
        .mtool__search input{
            width:100%; height:36px; padding:0 12px 0 34px;
            font-family:inherit; font-size:13px; color:var(--ink);
            background:var(--paper); border:1px solid transparent; border-radius:8px; outline:none;
            transition:border-color .2s ease, background .2s ease, box-shadow .2s ease;
        }
        .mtool__search input:focus{ background:var(--card); border-color:var(--a-400);
                                    box-shadow:0 0 0 3px rgba(46,110,168,.12); }
        .mtool__search svg{ position:absolute; left:11px; top:10px; width:15px; height:15px; color:var(--muted-2); }
        .mtool__count{ font-size:12px; color:var(--muted-2); font-variant-numeric:tabular-nums; }
        .mtool__sp{ flex:1; }

        /* --- Tabla --- */
        .mt-wrap{ overflow-x:auto; }
        .mt{ width:100%; border-collapse:separate; border-spacing:0; font-size:13.2px; }
        .mt thead th{
            position:sticky; top:0; z-index:1;
            padding:10px 16px; text-align:left; white-space:nowrap;
            background:var(--paper);
            font-size:10.5px; font-weight:700; letter-spacing:.11em; text-transform:uppercase;
            color:var(--muted); border-bottom:1px solid var(--line);
        }
        .mt thead th.num, .mt tbody td.num{ text-align:right; font-variant-numeric:tabular-nums; }
        .mt thead th.act, .mt tbody td.act{ text-align:right; width:1%; white-space:nowrap; }
        .mt tbody td{
            padding:12px 16px; border-bottom:1px solid var(--line-2); color:var(--ink-2);
            vertical-align:middle;
        }
        .mt tbody tr{ transition:background .18s ease; }
        .mt tbody tr:hover{ background:var(--paper); }
        .mt tbody tr:last-child td{ border-bottom:none; }
        .mt tbody td b{ color:var(--ink); font-weight:600; }
        .mt tbody td .sub{ display:block; font-size:11.4px; color:var(--muted-2); margin-top:2px; }
        .mt__id{ font-variant-numeric:tabular-nums; color:var(--muted-2); font-size:12px; }

        .mt__ent{ display:flex; align-items:center; gap:11px; min-width:0; }
        .mt__av{
            width:32px; height:32px; flex:none; border-radius:8px;
            display:grid; place-items:center; color:#fff; font-size:12px; font-weight:700;
            background:var(--a-600);
        }
        .mt__acts{ display:inline-flex; gap:5px; opacity:.55; transition:opacity .2s ease; }
        .mt tbody tr:hover .mt__acts{ opacity:1; }

        /* Entrada escalonada de filas */
        .mt tbody tr[data-row]{ opacity:0; transform:translateY(7px); }
        body.mus-in .mt tbody tr[data-row]{ animation:rowIn .45s var(--e-soft) forwards; }
        @keyframes rowIn{ to{ opacity:1; transform:none; } }

        /* --- Insignias --- */
        .mbg{
            display:inline-flex; align-items:center; gap:5px;
            height:22px; padding:0 9px; border-radius:6px;
            font-size:11.3px; font-weight:600; white-space:nowrap;
            background:var(--line-2); color:var(--muted);
        }
        .mbg i{ width:5px; height:5px; border-radius:50%; background:currentColor; }
        .mbg--ok{ background:#EDF5F0; color:#31684D; }
        .mbg--off{ background:#F6F1F1; color:#8A5150; }
        .mbg--info{ background:#EDF3F9; color:#215480; }
        .mbg--warn{ background:#FAF5EC; color:#7C5C2E; }

        /* --- Formularios --- */
        /* `minmax(0,1fr)` y no `1fr`: si no, un <select> con opciones
           largas ensancha el formulario y saca la página de la pantalla. */
        .mf{ display:grid; gap:16px; grid-template-columns:minmax(0,1fr); }
        .mf__grid{ display:grid; gap:16px; grid-template-columns:repeat(2,minmax(0,1fr)); }
        .mf__grid--3{ grid-template-columns:repeat(3,minmax(0,1fr)); }
        .mf__wide{ grid-column:1 / -1; }
        @media (max-width:760px){ .mf__grid, .mf__grid--3{ grid-template-columns:1fr; } }

        .mfld__lab{
            display:flex; align-items:center; gap:6px;
            font-size:12.2px; font-weight:600; color:var(--ink-2); margin-bottom:6px;
        }
        .mfld__req{ color:var(--bad); font-size:13px; line-height:1; }
        .mfld__box{ position:relative; }
        .mfld__in{
            width:100%; min-height:40px; padding:9px 12px;
            font-family:inherit; font-size:13.4px; color:var(--ink);
            background:var(--card); border:1px solid var(--line); border-radius:8px; outline:none;
            transition:border-color .2s ease, box-shadow .2s ease, background .2s ease;
        }
        textarea.mfld__in{ min-height:92px; resize:vertical; line-height:1.55; }
        select.mfld__in{
            appearance:none; -webkit-appearance:none; padding-right:34px; cursor:pointer;
            background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A2B6' stroke-width='2.4' stroke-linecap='round'><path d='m6 9 6 6 6-6'/></svg>");
            background-repeat:no-repeat; background-position:right 12px center;
        }
        .mfld__in::placeholder{ color:var(--muted-2); }
        .mfld__in:hover{ border-color:var(--muted-2); }
        .mfld__in:focus{ border-color:var(--a-500); box-shadow:0 0 0 3px rgba(46,110,168,.13); }
        .mfld__in:disabled{ background:var(--line-2); color:var(--muted); cursor:not-allowed; }

        /* Línea de acento que barre al enfocar */
        .mfld__box::after{
            content:''; position:absolute; left:9px; right:9px; bottom:0; height:2px;
            background:var(--a-500); border-radius:2px;
            transform:scaleX(0); transform-origin:left;
            transition:transform .42s var(--e-soft);
            pointer-events:none;
        }
        .mfld__box:focus-within::after{ transform:scaleX(1); }

        .mfld--err .mfld__in{ border-color:var(--bad); background:#FDF8F8; }
        .mfld--err .mfld__box::after{ background:var(--bad); }
        .mfld__err{
            display:flex; align-items:flex-start; gap:5px;
            margin-top:6px; font-size:11.8px; color:var(--bad);
            animation:errIn .32s var(--e-soft);
        }
        .mfld__err svg{ width:12px; height:12px; flex:none; margin-top:1.5px; }
        @keyframes errIn{ from{ opacity:0; transform:translateY(-4px); } }
        .mfld__hint{ margin-top:5px; font-size:11.5px; color:var(--muted-2); }
        .mfld__pre{
            position:absolute; left:12px; top:50%; transform:translateY(-50%);
            font-size:13px; color:var(--muted-2); pointer-events:none;
        }
        .mfld__pre ~ .mfld__in{ padding-left:26px; }

        /* Interruptor */
        .msw{ display:inline-flex; align-items:center; gap:11px; cursor:pointer; user-select:none; }
        .msw input{ position:absolute; opacity:0; width:0; height:0; }
        .msw__track{
            width:40px; height:23px; flex:none; border-radius:99px; position:relative;
            background:#CBD3DE; transition:background .28s var(--e-soft);
        }
        .msw__track::after{
            content:''; position:absolute; left:3px; top:3px; width:17px; height:17px;
            border-radius:50%; background:#fff;
            box-shadow:0 1px 3px rgba(16,24,37,.3);
            transition:transform .34s var(--e-back);
        }
        .msw input:checked + .msw__track{ background:var(--a-500); }
        .msw input:checked + .msw__track::after{ transform:translateX(17px); }
        .msw input:focus-visible + .msw__track{ outline:2px solid var(--a-500); outline-offset:2px; }
        .msw__txt b{ display:block; font-size:12.8px; font-weight:600; color:var(--ink-2); }
        .msw__txt span{ display:block; font-size:11.4px; color:var(--muted-2); margin-top:1px; }

        /* --- Lista de definición (detalle) --- */
        .mdl{ display:grid; gap:0; }
        .mdl > div{
            display:flex; align-items:flex-start; justify-content:space-between; gap:18px;
            padding:12px 0; border-bottom:1px dashed var(--line);
        }
        .mdl > div:last-child{ border-bottom:none; }
        .mdl dt{ font-size:12.5px; color:var(--muted); flex:none; }
        .mdl dd{ margin:0; font-size:13.2px; font-weight:500; color:var(--ink); text-align:right;
                 word-break:break-word; }

        /* --- Estado vacío --- */
        .mempty{
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            gap:12px; padding:56px 20px; text-align:center;
        }
        .mempty__ico{
            width:56px; height:56px; border-radius:14px; display:grid; place-items:center;
            background:var(--paper); border:1px solid var(--line); color:var(--muted-2);
            animation:emptyFloat 4.5s var(--e-inout) infinite;
        }
        @keyframes emptyFloat{ 50%{ transform:translateY(-6px); } }
        .mempty__ico svg{ width:24px; height:24px; }
        .mempty h4{ margin:0; font-size:14.5px; font-weight:700; color:var(--ink-2); }
        .mempty p{ margin:0; font-size:12.8px; color:var(--muted-2); max-width:340px; }

        /* --- Paginación --- */
        .mpag{
            display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between;
            gap:12px; padding:13px 16px; border-top:1px solid var(--line-2);
        }
        .mpag__info{ font-size:12.2px; color:var(--muted-2); font-variant-numeric:tabular-nums; }
        .mpag__nav{ display:flex; align-items:center; gap:4px; }
        .mpag__nav a, .mpag__nav span{
            min-width:32px; height:32px; padding:0 9px; border-radius:7px;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:12.5px; font-weight:600; text-decoration:none;
            color:var(--ink-2); border:1px solid transparent;
            font-variant-numeric:tabular-nums;
            transition:background .18s ease, color .18s ease, border-color .18s ease;
        }
        .mpag__nav a:hover{ background:var(--line-2); border-color:var(--line); }
        .mpag__nav .on{ background:var(--a-500); color:#fff; }
        .mpag__nav .off{ color:var(--muted-2); opacity:.5; }
        .mpag__nav svg{ width:14px; height:14px; }

        /* --- Avisos flotantes --- */
        .mtoasts{
            position:fixed; right:20px; bottom:20px; z-index:9500;
            display:flex; flex-direction:column; gap:10px; align-items:flex-end;
            pointer-events:none;
        }
        .mtoast{
            pointer-events:auto; position:relative; overflow:hidden;
            display:flex; align-items:flex-start; gap:11px;
            min-width:260px; max-width:380px;
            padding:12px 14px 12px 12px; border-radius:10px;
            background:var(--card); border:1px solid var(--line);
            box-shadow:0 18px 40px -18px rgba(16,24,37,.45), 0 2px 6px rgba(16,24,37,.06);
            opacity:0; transform:translateX(28px) scale(.96);
            animation:toastIn .55s var(--e-back) forwards;
        }
        .mtoast.out{ animation:toastOut .38s var(--e-inout) forwards; }
        @keyframes toastIn{ to{ opacity:1; transform:none; } }
        @keyframes toastOut{ to{ opacity:0; transform:translateX(24px) scale(.96); } }
        .mtoast__ico{
            width:28px; height:28px; flex:none; border-radius:8px;
            display:grid; place-items:center; color:#fff;
        }
        .mtoast__ico svg{ width:15px; height:15px;
                          stroke-dasharray:30; stroke-dashoffset:30;
                          animation:musDraw .5s var(--e-soft) .28s forwards; }
        .mtoast--ok .mtoast__ico{ background:var(--ok); }
        .mtoast--bad .mtoast__ico{ background:var(--bad); }
        .mtoast--info .mtoast__ico{ background:var(--a-500); }
        .mtoast__txt{ flex:1; min-width:0; padding-top:1px; }
        .mtoast__txt b{ display:block; font-size:13px; font-weight:600; color:var(--ink); }
        .mtoast__txt span{ display:block; font-size:12px; color:var(--muted); margin-top:2px; }
        .mtoast__x{
            flex:none; width:22px; height:22px; border-radius:6px; border:none; cursor:pointer;
            background:transparent; color:var(--muted-2); display:grid; place-items:center;
            transition:background .18s ease, color .18s ease;
        }
        .mtoast__x:hover{ background:var(--line-2); color:var(--ink); }
        .mtoast__x svg{ width:13px; height:13px; }
        .mtoast__bar{ position:absolute; left:0; right:0; bottom:0; height:2px; background:var(--line-2); }
        .mtoast__bar i{ display:block; height:100%; width:100%; transform-origin:left;
                        background:var(--a-500); animation:toastBar 5s linear .3s forwards; }
        .mtoast--ok .mtoast__bar i{ background:var(--ok); }
        .mtoast--bad .mtoast__bar i{ background:var(--bad); }
        @keyframes toastBar{ to{ transform:scaleX(0); } }
        @media (max-width:520px){ .mtoasts{ left:14px; right:14px; } .mtoast{ max-width:none; width:100%; } }

        /* --- Paleta de comandos (Ctrl + K) --- */
        .mcmd{
            position:fixed; inset:0; z-index:9700;
            display:flex; align-items:flex-start; justify-content:center;
            padding:14vh 20px 20px;
            background:rgba(7,13,20,.5);
            backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px);
            opacity:0; pointer-events:none; transition:opacity .26s ease;
        }
        .mcmd.on{ opacity:1; pointer-events:auto; }
        .mcmd__box{
            width:100%; max-width:560px; overflow:hidden;
            background:var(--card); border:1px solid var(--line); border-radius:14px;
            box-shadow:0 40px 90px -30px rgba(7,13,20,.75);
            transform:translateY(-14px) scale(.97);
            transition:transform .34s var(--e-back);
        }
        .mcmd.on .mcmd__box{ transform:none; }
        .mcmd__top{ display:flex; align-items:center; gap:11px; padding:14px 16px;
                    border-bottom:1px solid var(--line-2); }
        .mcmd__top svg{ width:17px; height:17px; color:var(--muted-2); flex:none; }
        .mcmd__in{
            flex:1; border:none; outline:none; background:transparent;
            font-family:inherit; font-size:15px; color:var(--ink);
        }
        .mcmd__in::placeholder{ color:var(--muted-2); }
        .mcmd__kbd{
            flex:none; padding:3px 7px; border-radius:5px;
            background:var(--line-2); border:1px solid var(--line);
            font-size:10.5px; font-weight:600; color:var(--muted);
        }
        .mcmd__list{ max-height:340px; overflow-y:auto; padding:6px; }
        .mcmd__grp{ padding:9px 11px 5px; font-size:9.5px; font-weight:700;
                    letter-spacing:.2em; text-transform:uppercase; color:var(--muted-2); }
        .mcmd__item{
            display:flex; align-items:center; gap:11px;
            padding:9px 11px; border-radius:8px; text-decoration:none; cursor:pointer;
            color:var(--ink-2); font-size:13.4px;
        }
        .mcmd__item .i{
            width:28px; height:28px; flex:none; border-radius:7px; display:grid; place-items:center;
            background:var(--paper); border:1px solid var(--line); color:var(--muted);
        }
        .mcmd__item .i svg{ width:14px; height:14px; }
        .mcmd__item .t{ flex:1; min-width:0; }
        .mcmd__item .t b{ display:block; font-weight:500; color:var(--ink); }
        .mcmd__item .t span{ display:block; font-size:11.4px; color:var(--muted-2); }
        .mcmd__item .go{ opacity:0; color:var(--a-500); }
        .mcmd__item .go svg{ width:14px; height:14px; }
        .mcmd__item.sel{ background:var(--paper); }
        .mcmd__item.sel .i{ background:var(--a-500); border-color:var(--a-500); color:#fff; }
        .mcmd__item.sel .go{ opacity:1; }
        .mcmd__none{ padding:30px 16px; text-align:center; font-size:13px; color:var(--muted-2); }
        .mcmd__foot{ display:flex; gap:14px; padding:10px 16px; border-top:1px solid var(--line-2);
                     font-size:11px; color:var(--muted-2); }
        .mcmd__foot b{ font-weight:600; color:var(--muted); }

        /* Atajo en la barra superior */
        .mkbd-btn{
            display:inline-flex; align-items:center; gap:8px; height:32px; padding:0 10px;
            border-radius:7px; border:1px solid var(--line); background:var(--card);
            color:var(--muted); font-family:inherit; font-size:12.4px; cursor:pointer;
            transition:border-color .2s ease, color .2s ease, background .2s ease;
        }
        .mkbd-btn:hover{ border-color:var(--muted-2); color:var(--ink-2); }
        .mkbd-btn svg{ width:14px; height:14px; }
        .mkbd-btn kbd{
            font-family:inherit; font-size:10.5px; font-weight:600;
            padding:2px 5px; border-radius:4px; background:var(--line-2);
            border:1px solid var(--line); color:var(--muted);
        }
        @media (max-width:760px){ .mkbd-btn kbd{ display:none; } }

        /* --- Transición al navegar entre secciones --- */
        .mus-page{ transition:opacity .26s ease, transform .32s var(--e-soft); }
        body.mus-leaving .mus-page{ opacity:0; transform:translateY(-8px); }
        body.mus-leaving .mus-top__slot{ opacity:.4; transition:opacity .22s ease; }

        /* --- Luz que sigue al cursor sobre tarjetas --- */
        [data-spot]{ position:relative; }
        [data-spot]::before{
            content:''; position:absolute; inset:0; border-radius:inherit; pointer-events:none;
            background:radial-gradient(280px circle at var(--mx,50%) var(--my,50%),
                       rgba(46,110,168,.07), transparent 62%);
            opacity:0; transition:opacity .35s ease;
        }
        [data-spot]:hover::before{ opacity:1; }

        /* --- Tooltip de la gráfica --- */
        .mchart-tip{
            position:absolute; z-index:5; pointer-events:none;
            padding:9px 11px; border-radius:9px; min-width:132px;
            background:var(--n-850); color:#fff;
            box-shadow:0 14px 32px -14px rgba(7,13,20,.8);
            font-size:12px;
            opacity:0; transform:translate(-50%,-124%) scale(.94);
            transition:opacity .16s ease, transform .24s var(--e-soft);
        }
        .mchart-tip.on{ opacity:1; transform:translate(-50%,-118%) scale(1); }
        .mchart-tip b{ display:block; font-size:10.5px; letter-spacing:.16em; text-transform:uppercase;
                       color:var(--muted-2); font-weight:600; margin-bottom:6px; }
        .mchart-tip .r{ display:flex; align-items:center; justify-content:space-between; gap:14px;
                        margin-top:3px; }
        .mchart-tip .r i{ width:7px; height:7px; border-radius:2px; flex:none; }
        .mchart-tip .r span{ flex:1; color:var(--muted-2); font-size:11.5px; }
        .mchart-tip .r em{ font-style:normal; font-weight:700; font-variant-numeric:tabular-nums; }
        .mchart-cross{
            position:absolute; top:0; bottom:26px; width:1px; z-index:4;
            background:linear-gradient(180deg,transparent,rgba(46,110,168,.35),rgba(46,110,168,.12));
            opacity:0; transition:opacity .18s ease;
            pointer-events:none;
        }
        .mchart-cross.on{ opacity:1; }


        /* ══════════ Mirando el negocio de un cliente ══════════ */
        .mmira{ display:flex; align-items:center; gap:10px; flex-wrap:wrap;
                padding:8px 18px; background:#7A3F3E; color:#FBEDEC; font-size:12.4px; }
        .mmira__ico{ display:grid; place-items:center; }
        .mmira__ico svg{ width:15px; height:15px; }
        .mmira b{ font-weight:650; }
        /* Lo primero que sobra cuando falta ancho: el aviso largo. El
           nombre del negocio y el botón de salida tienen que caber siempre. */
        .mmira em{ font-style:normal; opacity:.8; min-width:0; overflow:hidden;
                   text-overflow:ellipsis; white-space:nowrap; }
        .mmira form{ margin-left:auto; }
        .mmira button{ padding:3px 11px; border:1px solid rgba(255,255,255,.42);
                       border-radius:99px; background:transparent; color:inherit;
                       font-size:11.8px; font-family:inherit; cursor:pointer;
                       transition:background .18s; }
        .mmira button:hover{ background:rgba(255,255,255,.14); }
        @media (max-width:760px){ .mmira em{ display:none; } }

        /* ══════════ Reloj de la demostración ══════════ */
        .mdemo-banda{ display:flex; align-items:center; gap:9px; flex-wrap:wrap;
                      padding:8px 18px; background:var(--a-700); color:#EAF2FA;
                      font-size:12.4px; }
        .mdemo-banda.is-poco{ background:#7A3F3E; }
        .mdemo-banda__ico{ display:grid; place-items:center; }
        .mdemo-banda__ico svg{ width:15px; height:15px; }
        .mdemo-banda b{ font-weight:650; }
        .mdemo-banda__t{ padding:1px 9px; border-radius:99px; background:rgba(255,255,255,.16);
                         font-weight:650; font-variant-numeric:tabular-nums; }
        /* El texto explicativo es lo primero que sobra cuando falta ancho:
           el reloj y el nombre tienen que caber siempre. */
        .mdemo-banda em{ font-style:normal; opacity:.78; min-width:0; overflow:hidden;
                         text-overflow:ellipsis; white-space:nowrap; }
        @media (max-width:760px){ .mdemo-banda em{ display:none; } }

        /* ══════════ Negocio y local activos ══════════ */
        .mctx{ position:relative; flex:none; }
        .mctx__btn{ display:flex; align-items:center; gap:9px; max-width:230px;
                    padding:5px 9px 5px 6px; border:1px solid var(--line); border-radius:10px;
                    background:var(--card); color:var(--ink-2); cursor:pointer; text-align:left;
                    transition:border-color .18s var(--e-soft), background .18s; }
        .mctx__btn:hover{ border-color:var(--muted-2); }
        .mctx__ico{ display:grid; place-items:center; width:26px; height:26px; flex:none;
                    border-radius:7px; background:var(--a-500); color:#fff; }
        .mctx__ico svg{ width:15px; height:15px; }
        /* minmax(0,…) no: aquí el que tiene que poder encogerse es el texto,
           y sin min-width:0 un nombre largo empuja la barra de lado. */
        .mctx__txt{ display:grid; min-width:0; line-height:1.18; }
        .mctx__txt b{ font-size:12.4px; font-weight:650; color:var(--ink);
                      overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .mctx__txt i{ font-style:normal; font-size:10.6px; color:var(--muted);
                      overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .mctx__caret{ width:13px; height:13px; flex:none; color:var(--muted);
                      transition:transform .2s var(--e-soft); }
        .mctx.is-on .mctx__caret{ transform:rotate(180deg); }

        .mctx__box{ position:absolute; top:calc(100% + 9px); left:0; width:min(300px,86vw); z-index:70;
                    background:var(--card); border:1px solid var(--line); border-radius:13px;
                    box-shadow:0 26px 60px -30px rgba(16,24,37,.55); overflow:hidden;
                    max-height:min(70vh,460px); overflow-y:auto; padding-bottom:7px;
                    opacity:0; visibility:hidden; transform:translateY(-8px) scale(.98);
                    transition:opacity .22s var(--e-soft), transform .26s var(--e-soft), visibility .22s; }
        .mctx.is-on .mctx__box{ opacity:1; visibility:visible; transform:none; }

        .mctx__box header{ display:grid; gap:2px; padding:12px 15px;
                           border-bottom:1px solid var(--line-2); background:var(--paper); }
        .mctx__box header b{ font-size:13px; color:var(--ink); }
        .mctx__box header span{ font-size:11.4px; color:var(--muted); }

        .mctx__neg{ display:flex; align-items:baseline; gap:7px; padding:11px 15px 5px;
                    font-size:10.6px; font-weight:700; letter-spacing:.07em;
                    text-transform:uppercase; color:var(--muted); }
        .mctx__neg em{ font-style:normal; font-size:9.8px; font-weight:600; letter-spacing:.02em;
                       text-transform:none; padding:1px 6px; border-radius:99px;
                       background:rgba(150,80,79,.12); color:var(--bad); }

        .mctx__op{ display:flex; align-items:center; gap:9px; width:100%; padding:8px 15px;
                   background:none; border:0; cursor:pointer; text-align:left; color:var(--ink-2);
                   font-size:12.8px; transition:background .16s; }
        .mctx__op:hover:not(:disabled){ background:var(--paper); }
        .mctx__op:disabled{ opacity:.45; cursor:not-allowed; }
        .mctx__punto{ width:7px; height:7px; flex:none; border-radius:99px;
                      border:1.6px solid var(--muted-2); }
        .mctx__op.is-on{ color:var(--ink); font-weight:600; }
        .mctx__op.is-on .mctx__punto{ background:var(--a-500); border-color:var(--a-500);
                                      box-shadow:0 0 0 3px rgba(46,110,168,.16); }
        .mctx__nom{ flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .mctx__nom kbd{ margin-left:5px; padding:0 4px; border-radius:4px; font-size:9.6px;
                        font-family:inherit; background:var(--line-2); color:var(--muted); }
        .mctx__op em{ font-style:normal; font-size:10.6px; color:var(--muted); flex:none; }
        .mctx__vacio{ padding:4px 15px 10px; font-size:11.6px; color:var(--muted); }

        /* ══════════ Campana de avisos ══════════ */
        .mbell{ position:relative; flex:none; }
        .mbell__btn{ position:relative; display:grid; place-items:center; width:36px; height:36px;
                     border:1px solid var(--line); border-radius:10px; background:var(--card);
                     color:var(--muted); cursor:pointer;
                     transition:border-color .18s var(--e-soft), color .18s, background .18s; }
        .mbell__btn:hover{ color:var(--ink); border-color:var(--muted-2); }
        .mbell__btn svg{ width:18px; height:18px; }
        .mbell__btn.is-media{ color:var(--warn); border-color:rgba(150,112,60,.34); }
        .mbell__btn.is-alta{ color:var(--bad); border-color:rgba(150,80,79,.34); }
        .mbell__btn.is-alta svg{ animation:mbellSuena 4.5s var(--e-soft) infinite; transform-origin:top center; }
        @keyframes mbellSuena{
            0%,88%,100%{ transform:rotate(0); }
            90%{ transform:rotate(-11deg); } 93%{ transform:rotate(9deg); }
            96%{ transform:rotate(-5deg); } 98%{ transform:rotate(3deg); }
        }
        .mbell__n{ position:absolute; top:-5px; right:-5px; min-width:17px; height:17px; padding:0 4px;
                   display:grid; place-items:center; border-radius:99px; background:var(--bad);
                   color:#fff; font-size:10px; font-weight:700; line-height:1;
                   box-shadow:0 0 0 2px var(--paper); }

        .mbell__box{ position:absolute; top:calc(100% + 9px); right:0; width:min(330px,86vw); z-index:70;
                     background:var(--card); border:1px solid var(--line); border-radius:13px;
                     box-shadow:0 26px 60px -30px rgba(16,24,37,.55); overflow:hidden;
                     opacity:0; visibility:hidden; transform:translateY(-8px) scale(.98);
                     transition:opacity .22s var(--e-soft), transform .26s var(--e-soft), visibility .22s; }
        .mbell.is-on .mbell__box{ opacity:1; visibility:visible; transform:none; }

        .mbell__box header{ display:flex; align-items:baseline; justify-content:space-between; gap:10px;
                            padding:12px 15px; border-bottom:1px solid var(--line-2); background:var(--paper); }
        .mbell__box header b{ font-size:13px; color:var(--ink); }
        .mbell__box header span{ font-size:11.4px; color:var(--muted); }

        .mbell__i{ display:flex; gap:11px; align-items:flex-start; padding:11px 15px;
                   text-decoration:none; color:inherit; border-bottom:1px solid var(--line-2);
                   transition:background .16s; }
        .mbell__i:last-child{ border-bottom:0; }
        .mbell__i:hover{ background:var(--paper); }
        .mbell__ico{ display:grid; place-items:center; width:28px; height:28px; flex:none;
                     border-radius:8px; background:var(--line-2); color:var(--muted); }
        .mbell__i--alta .mbell__ico{ background:rgba(150,80,79,.12); color:var(--bad); }
        .mbell__i--media .mbell__ico{ background:rgba(150,112,60,.13); color:var(--warn); }
        .mbell__i b{ display:block; font-size:12.7px; font-weight:600; color:var(--ink); line-height:1.35; }
        .mbell__i em{ display:block; font-style:normal; font-size:11.4px; color:var(--muted); margin-top:1px; }

        .mbell__ok{ display:grid; place-items:center; gap:7px; padding:26px 0; color:var(--ok); }
        .mbell__ok p{ margin:0; font-size:12.4px; color:var(--muted); }

        @media (max-width:520px){ .mbell__box{ right:-60px; } }

        /* ══════════════════════════════════════════════════════════════
           CELULAR
           El sistema se usa de pie y con una sola mano, así que en
           pantalla angosta las tablas dejan de ser tablas y se vuelven
           tarjetas, los botones crecen hasta el tamaño de un dedo y nada
           obliga a hacer zoom ni a arrastrar la pantalla de lado.
           ══════════════════════════════════════════════════════════════ */

        /* La barra superior se esconde al bajar y vuelve al subir: en un
           celular cada píxel de alto cuenta. */
        @media (max-width:1024px){
            .mus-top{ transition:transform .28s var(--e-soft); }
            body.mus-top-off .mus-top{ transform:translateY(-102%); }
        }

        @media (max-width:760px){
            /* El título de la página baja a su propia línea */
            .mus-top{ flex-wrap:wrap; row-gap:9px; column-gap:10px; padding:10px 14px; }
            .mus-top__burger{ order:1; }
            .mctx{ order:2; margin-left:auto; }
            .mctx__btn{ max-width:150px; }
            .mctx__txt i{ display:none; }
            .mbell{ order:3; }
            .mkbd-btn{ order:4; }
            .mus-top__slot{ order:5; flex:1 0 100%; }

            .mh{ gap:10px; }
            .mh__ico{ width:34px; height:34px; }
            .mh__t h1{ font-size:17px; white-space:normal; }
            .mh__t p{ font-size:12px; }
            /* Los botones de la cabecera se apilan a lo ancho; si la
               etiqueta es larga («Turno abierto · base $300.000») se
               recorta en vez de empujar la página de lado. */
            .mh__r{ flex:1 0 100%; flex-wrap:wrap; }
            .mh__r > *{ flex:1 1 100%; min-width:0; }
            .mh__r .mb{ max-width:100%; text-overflow:ellipsis; }

            /* Una columna de verdad: `1fr` deja crecer al contenido (un
               <select> con opciones largas), `minmax(0,1fr)` no. */
            .mf__grid, .mf__grid--3{ grid-template-columns:minmax(0,1fr); }
            .mfld__box{ min-width:0; }
            .mfld__in{ max-width:100%; }

            /* Menos de 16px en un campo y el navegador hace zoom solo */
            .mfld__in, .mtool__search input, .mcmd__in{ font-size:16px; }

            /* Blancos del tamaño de un dedo */
            .mb{ min-height:40px; }
            .mb--sm{ height:36px; }
            .mb--icon{ width:36px; height:36px; }

            .mp__body{ padding:15px 14px; }
            .mp__head{ padding:13px 14px; flex-wrap:wrap; }
            .mp__tools{ flex:1 0 100%; flex-wrap:wrap; }
            .mp__tools > *{ flex:1 1 auto; min-width:0; }
            .mp__foot{ padding:13px 14px; flex-wrap:wrap; }
            .mp__foot > *{ flex:1 1 auto; }
            .mp--pad{ padding:15px 14px; }

            /* El buscador de la tabla se queda con toda la línea */
            .mtool{ padding:11px 12px; gap:8px; }
            .mtool__search{ flex:1 0 100%; max-width:none; }
            .mtool__sp{ display:none; }
            .mtool__count{ order:9; }

            /* Paginación centrada, con blancos grandes */
            .mpag{ flex-direction:column; align-items:center; gap:11px; }
            .mpag__nav a, .mpag__nav span{ min-width:36px; height:36px; }

            /* ---------- Tablas en modo tarjeta ----------
               Cada fila se vuelve una tarjeta y cada celda una línea
               «etiqueta → valor». La etiqueta la pone el JS copiando el
               encabezado de la columna, así que funciona en las 25 tablas
               del sistema sin tocar ninguna vista. */
            .mt-wrap{ overflow:visible; }
            .mt{ display:block; font-size:13px; }
            .mt thead{ display:none; }
            .mt tbody{ display:block; padding:10px; }
            .mt tbody tr{
                display:block; margin-bottom:10px; padding:11px 13px;
                background:var(--card); border:1px solid var(--line); border-radius:11px;
            }
            .mt tbody tr:last-child{ margin-bottom:0; }
            .mt tbody tr:hover{ background:var(--card); }
            .mt tbody td{
                display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between;
                gap:2px 14px; padding:5px 0; border:none; text-align:right;
            }
            /* El renglón secundario (SKU, teléfono, categoría…) baja a su
               propia línea en vez de pelear el espacio con el valor. */
            .mt tbody td .sub{ flex:1 0 100%; margin-top:1px; }
            .mt tbody td::before{
                content:attr(data-th); flex:none; max-width:44%; text-align:left;
                padding-top:2px;
                font-size:10px; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
                color:var(--muted-2);
            }
            .mt tbody td:not([data-th])::before{ display:none; }
            .mt tbody td:not([data-th]),
            .mt tbody td.act{
                justify-content:flex-end; width:auto; white-space:normal;
                margin-top:6px; padding-top:10px; border-top:1px solid var(--line-2);
            }
            /* Un correo largo no cabe en 320px: se parte antes que
               empujar la tarjeta fuera de la pantalla. */
            .mt tbody td{ overflow-wrap:anywhere; }
            .mt tbody td .sub{ text-align:right; }
            .mt__acts{ opacity:1; gap:7px; }

            /* El número de fila no aporta nada en una tarjeta */
            .mt tbody td[data-th="#"]{ display:none; }

            /* La celda del nombre hace de título de la tarjeta: sin
               etiqueta, alineada a la izquierda y con su propia línea. */
            .mt tbody td:has(.mt__ent){
                display:block; text-align:left; padding:1px 0 9px;
            }
            .mt tbody td:has(.mt__ent)::before{ display:none; }
            .mt tbody td:has(.mt__ent) .sub{ text-align:left; }
            .mt__ent{ justify-content:flex-start; text-align:left; }

            .mt tfoot{ display:block; }
            .mt tfoot tr{ display:block; padding:11px 13px; }
            .mt tfoot td, .mt tfoot th{
                display:flex; justify-content:space-between; gap:14px;
                border:none; padding:4px 0; text-align:right;
            }

            /* Ctrl+K a pantalla completa */
            .mcmd{ padding:0; align-items:stretch; }
            .mcmd__box{ max-width:none; border:0; border-radius:0;
                        display:flex; flex-direction:column; }
            .mcmd__list{ max-height:none; flex:1; }
            .mcmd__foot{ display:none; }

            /* Los avisos ocupan el ancho de la pantalla */
            .mbell__box{ position:fixed; left:10px; right:10px; width:auto;
                         top:calc(var(--top-h) + 8px); max-height:70vh; overflow-y:auto; }
            .mctx__box{ position:fixed; left:10px; right:10px; width:auto;
                        top:calc(var(--top-h) + 8px); max-height:70vh; }
        }

        @media (max-width:480px){
            .mus-page{ padding:14px 11px 44px; }
            .mh__t h1{ font-size:16px; }
            .mcrumb{ font-size:11px; }
            /* El botón de buscar se queda solo con la lupa */
            .mkbd-btn{ width:36px; padding:0; gap:0; justify-content:center; font-size:0; }
            .mt tbody{ padding:8px; }
            .mt tbody tr{ padding:10px 11px; }
        }

        /* Las entradas laterales se vuelven verticales: en una pantalla
           angosta un panel desplazado 26px a la derecha asoma por fuera y
           aparece una barra de desplazamiento horizontal fea. */
        @media (max-width:1024px){
            [data-reveal="left"], [data-reveal="right"]{ transform:translateY(16px); }
        }

    </style>

    @stack('styles')
</head>

<body class="mus-body font-sans antialiased">

    {{-- ══════════ Puerta de bienvenida ══════════ --}}
    @if ($musWelcome)
        @php
            $musFirst = \Illuminate\Support\Str::of($musWelcome)->trim()->explode(' ')->first();
        @endphp
        <div id="mus-gate">
            <span class="g-wash"></span>
            <span class="g-grid"></span>
            <span class="g-ring r1"></span>
            <span class="g-ring r2"></span>
            <span class="g-ring r3"></span>
            <span class="g-sheet t"></span>
            <span class="g-sheet b"></span>

            <div class="g-in">
                <svg class="mus-check" viewBox="0 0 100 100">
                    <circle class="ring" cx="50" cy="50" r="45"/>
                    <path class="tick" d="M31 51.5 L44 64 L70 38"/>
                </svg>
                <h2>{{ $musIsNew ? 'Cuenta creada, ' : 'Hola de nuevo, ' }}{{ $musFirst }}</h2>
                <p class="g-sub">
                    {{ $musIsNew
                        ? 'Tu espacio en Mega Uni Store ya está listo.'
                        : 'Retomamos justo donde lo dejaste.' }}
                </p>
                <div class="g-bar"><i></i></div>
                <p class="g-status" id="musGateStatus">{{ __('mus.puertas.verificando') }}</p>
            </div>
            <div class="g-skip">{{ __('mus.puertas.clic_entrar') }}</div>
        </div>
    @endif

    {{-- ══════════ Cierre de sesión ══════════ --}}
    <div id="mus-bye" aria-hidden="true">
        <span class="b-wash"></span>
        <span class="b-grid"></span>
        <div class="b-in">
            <div class="b-stage">
                <span class="b-arc a1"></span>
                <span class="b-arc a2"></span>
                <span class="b-arc a3"></span>
                <svg class="b-mark" viewBox="0 0 48 48">
                    <path class="m1" d="M8 16 L24 7 L40 16 L24 25 Z"/>
                    <path class="m2" d="M8 16 v16 L24 41 V25"/>
                    <path class="m3" d="M40 16 v16 L24 41"/>
                </svg>
                <span class="b-scan"></span>
                <span class="b-dot" style="--a:0deg"></span>
                <span class="b-dot" style="--a:30deg"></span>
                <span class="b-dot" style="--a:60deg"></span>
                <span class="b-dot" style="--a:90deg"></span>
                <span class="b-dot" style="--a:120deg"></span>
                <span class="b-dot" style="--a:150deg"></span>
                <span class="b-dot" style="--a:180deg"></span>
                <span class="b-dot" style="--a:210deg"></span>
                <span class="b-dot" style="--a:240deg"></span>
                <span class="b-dot" style="--a:270deg"></span>
                <span class="b-dot" style="--a:300deg"></span>
                <span class="b-dot" style="--a:330deg"></span>
            </div>
            <h3 class="b-title" id="musByeTitle"></h3>
            <p class="b-sub">{{ __('mus.puertas.hasta_pronto') }}</p>
            <div class="b-bar"><i></i></div>
        </div>
    </div>

    {{-- ══════════ Shell ══════════ --}}
    <div class="mus-shell">
        <div class="mus-backdrop" id="musBackdrop"></div>

        @include('layouts.navigation')

        <div class="mus-main">
            @php
                /* Cuánto le queda a la demostración. Null = no es demo. */
                $musDemoEmpresa = \App\Support\Contexto::empresa();
                $musDemoMin = $musDemoEmpresa?->es_demo ? $musDemoEmpresa->minutosRestantes() : null;
            @endphp

            @php
                /* ¿Angel está mirando el negocio de un cliente? */
                $musMirando = (session('mus.mirando_empresa')
                    && auth()->user()?->can('sistema.superadmin'))
                    ? $musDemoEmpresa
                    : null;
            @endphp

            @if ($musMirando)
                {{-- ══════════ Estás en casa ajena ══════════
                     Roja y arriba de todo. No es decoración: es lo que evita
                     que alguien registre una venta en el negocio de un
                     cliente creyendo que está en el suyo. --}}
                <div class="mmira">
                    <span class="mmira__ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"/>
                            <circle cx="12" cy="12" r="2.6"/>
                        </svg>
                    </span>
                    <b>{{ __('mus.puertas.mirando', ['negocio' => $musMirando->nombre]) }}</b>
                    <em>{{ __('mus.puertas.mirando_aviso') }}</em>
                    <form method="POST" action="{{ route('sistema.salir') }}" data-sin-ctx>
                        @csrf
                        <button type="submit">{{ __('mus.puertas.volver_a_lo_mio') }}</button>
                    </form>
                </div>
            @endif

            @if ($musDemoMin !== null)
                {{-- ══════════ Reloj de la prueba ══════════
                     Va arriba de todo y se queda ahí. Un contador escondido
                     no cumple su función: la idea no es apurar a nadie, es
                     que cuando el sistema se cierre no sea una sorpresa. --}}
                <div class="mdemo-banda {{ $musDemoMin <= 15 ? 'is-poco' : '' }}"
                     id="musDemoBanda" data-min="{{ $musDemoMin }}">
                    <span class="mdemo-banda__ico" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"/><path d="M12 7.2V12l3.2 2"/>
                        </svg>
                    </span>
                    <b>{{ __('mus.puertas.demo_de', ['negocio' => $musDemoEmpresa->nombre]) }}</b>
                    <span class="mdemo-banda__t" id="musDemoReloj">{{ $musDemoMin }} {{ __('mus.puertas.demo_min') }}</span>
                    <em>{{ __('mus.puertas.demo_aviso') }}</em>
                </div>
            @endif

            <header class="mus-top">
                <button class="mus-top__burger" id="musBurger" aria-label="{{ __('mus.ui.abrir_menu') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>
                <div class="mus-top__slot">
                    @isset($header)
                        {{ $header }}
                    @endisset
                </div>

                {{-- Dónde estoy parado. Va antes de los avisos porque
                     responde una pregunta anterior: los avisos son de este
                     local, y hay que saber cuál es antes de leerlos. --}}
                @include('partials.selector-contexto')

                @php
                    /* Avisos del sistema: stock bajo, compras sin recibir, cajas abiertas. */
                    $musAlertas = ['total' => 0, 'criticas' => 0, 'items' => []];
                    try {
                        $musAlertas = app(\App\Services\AlertService::class)->todas();
                    } catch (\Throwable $e) {
                        // Antes de migrar las tablas todavía no hay nada que avisar.
                    }
                @endphp

                <div class="mbell" id="musBell">
                    <button class="mbell__btn {{ $musAlertas['criticas'] ? 'is-alta' : ($musAlertas['total'] ? 'is-media' : '') }}"
                            type="button" id="musBellBtn"
                            aria-label="{{ __('mus.buscador.avisos_n', ['n' => $musAlertas['total']]) }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8.5a6 6 0 1 0-12 0c0 5-2.2 6.5-2.2 6.5h16.4S18 13.5 18 8.5"/>
                            <path d="M13.7 19a2 2 0 0 1-3.4 0"/>
                        </svg>
                        @if ($musAlertas['total'])
                            <span class="mbell__n">{{ $musAlertas['total'] > 9 ? '9+' : $musAlertas['total'] }}</span>
                        @endif
                    </button>

                    <div class="mbell__box" id="musBellBox">
                        <header>
                            <b>{{ __('mus.buscador.avisos') }}</b>
                            <span>{{ $musAlertas['total'] ? $musAlertas['total'] . ' pendiente(s)' : 'todo en orden' }}</span>
                        </header>

                        @forelse ($musAlertas['items'] as $a)
                            <a href="{{ $a['url'] }}" class="mbell__i mbell__i--{{ $a['nivel'] }}">
                                <span class="mbell__ico"><x-mus.icon :name="$a['icono']" :w="15" /></span>
                                <span>
                                    <b>{{ $a['titulo'] }}</b>
                                    <em>{{ $a['texto'] }}</em>
                                </span>
                            </a>
                        @empty
                            <div class="mbell__ok">
                                <x-mus.icon name="check" :w="22" stroke-width="2" />
                                <p>{{ __('mus.buscador.sin_avisos') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <button class="mkbd-btn" type="button" data-cmd-open aria-label="{{ __('mus.buscador.abrir') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>
                    </svg>
                    {{ __('mus.acciones.buscar') }}
                    <kbd>{{ __('mus.buscador.kbd_ctrl_k') }}</kbd>
                </button>

                <span class="mus-progress" id="musProgress"></span>
            </header>

            {{-- ══════════ Ventana flotante ══════════
                 Los formularios de «Nuevo …» se abren aquí en vez de
                 llevarte a otra página. El contenido llega por fetch desde
                 la misma ruta de siempre. --}}
            <div class="mmod" id="musMod" role="dialog" aria-modal="true" aria-hidden="true">
                <div class="mmod__caja" id="musModCaja">
                    <header class="mmod__barra">
                        <h2 id="musModTitulo">{{ __('mus.puertas.cargando') }}</h2>
                        <button type="button" class="mmod__x" data-mod-cerrar aria-label="{{ __('mus.acciones.cerrar') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.2" stroke-linecap="round">
                                <path d="M6 6 18 18M18 6 6 18"/>
                            </svg>
                        </button>
                    </header>

                    <div class="mmod__cuerpo" id="musModCuerpo"></div>

                    <footer class="mmod__pie" id="musModPie" hidden></footer>
                </div>
            </div>

            <main class="mus-page">
                {{ $slot }}
            </main>
        </div>
    </div>

    <script>
    /* ══════════════════════════════════════════════════════════════
       MEGA UNI STORE · motor de interfaz
       ══════════════════════════════════════════════════════════════ */
    (function () {
        'use strict';

        var root    = document.documentElement;
        var body    = document.body;
        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var fino    = window.matchMedia('(pointer:fine)').matches;
        if (reduced) root.classList.add('no-motion');

        /* ---------- 1. Sidebar: riel + despliegue al pasar el mouse ---------- */
        var side  = document.getElementById('musSide');
        var pin   = document.getElementById('musPin');
        var tPeek = null;

        function anchoGrande() { return window.innerWidth > 1024; }

        function abrirPeek() {
            clearTimeout(tPeek);
            if (!root.classList.contains('side-rail') || !anchoGrande()) return;
            tPeek = setTimeout(function () { root.classList.add('side-peek'); }, 90);
        }
        function cerrarPeek() {
            clearTimeout(tPeek);
            tPeek = setTimeout(function () {
                root.classList.remove('side-peek');
                cerrarMenuUsuario();
            }, 220);
        }

        if (side && fino) {
            side.addEventListener('mouseenter', abrirPeek);
            side.addEventListener('mouseleave', cerrarPeek);
            /* Con teclado también se despliega */
            side.addEventListener('focusin', function () {
                if (root.classList.contains('side-rail') && anchoGrande()) {
                    root.classList.add('side-peek');
                }
            });
            side.addEventListener('focusout', function (e) {
                if (!side.contains(e.relatedTarget)) cerrarPeek();
            });
        }

        /* El botón fija el sidebar abierto o lo devuelve a riel. */
        if (pin) {
            pin.addEventListener('click', function () {
                var aRiel = !root.classList.contains('side-rail');
                root.classList.toggle('side-rail', aRiel);
                root.classList.remove('side-peek');
                try { localStorage.setItem('mus_side', aRiel ? 'riel' : 'fijo'); } catch (e) {}
                pin.setAttribute('aria-label', aRiel ? 'Fijar el menú abierto' : 'Contraer el menú');
            });
        }

        /* ---------- 2. Cajón en móvil ---------- */
        var burger   = document.getElementById('musBurger');
        var backdrop = document.getElementById('musBackdrop');
        function cerrarCajon() { body.classList.remove('side-open'); }
        if (burger) burger.addEventListener('click', function () { body.classList.toggle('side-open'); });
        if (backdrop) backdrop.addEventListener('click', cerrarCajon);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrarCajon(); });
        window.addEventListener('resize', function () { if (anchoGrande()) cerrarCajon(); });

        /* ---------- 3. Menú de usuario ---------- */
        var userBtn  = document.getElementById('musUserBtn');
        var userMenu = document.getElementById('musUserMenu');
        function cerrarMenuUsuario() {
            if (!userMenu) return;
            userMenu.classList.remove('is-open');
            if (userBtn) userBtn.classList.remove('is-open');
        }
        if (userBtn && userMenu) {
            userBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var abierto = userMenu.classList.toggle('is-open');
                userBtn.classList.toggle('is-open', abierto);
            });
            document.addEventListener('click', cerrarMenuUsuario);
            userMenu.addEventListener('click', function (e) { e.stopPropagation(); });
        }

        /* ---------- 4. Cierre de sesión ---------- */
        var bye = document.getElementById('mus-bye');

        (function tituloBye() {
            var h = document.getElementById('musByeTitle');
            if (!h) return;
            var txt = 'Cerrando sesión', i, ch, el, d = 0;
            for (i = 0; i < txt.length; i++) {
                ch = txt.charAt(i);
                el = document.createElement('span');
                if (ch === ' ') { el.className = 'sp'; }
                else {
                    el.textContent = ch;
                    el.style.animationDelay = (0.75 + d) + 's';
                    d += 0.035;
                }
                h.appendChild(el);
            }
        })();

        Array.prototype.forEach.call(document.querySelectorAll('form[data-logout]'), function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.going === '1') return;
                e.preventDefault();
                form.dataset.going = '1';
                if (bye) bye.classList.add('show');
                try { sessionStorage.removeItem('mus_intro'); } catch (err) {}
                setTimeout(function () { form.submit(); }, reduced ? 60 : 2500);
            });
        });
        Array.prototype.forEach.call(document.querySelectorAll('[data-logout-trigger]'), function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var f = document.getElementById('musLogoutForm');
                if (!f) return;
                if (f.requestSubmit) f.requestSubmit();
                else f.dispatchEvent(new Event('submit', { cancelable: true }));
            });
        });

        /* ---------- 5. Utilidades de animación ---------- */
        function easeOutExpo(t) { return t === 1 ? 1 : 1 - Math.pow(2, -10 * t); }

        /* Curva de los contadores: sube parejo y frena suave al final.
           easeOutExpo disparaba el número al 90% en un cuarto del tiempo y
           luego se arrastraba; con esta se alcanza a leer cómo sube. */
        function easeOutQuart(t) { return 1 - Math.pow(1 - t, 4); }

        /*  ── Ritmo de los números ──
            1 = como estaba. Más de 1 va más lento, menos de 1 más rápido.
            Es el único valor que hay que tocar para cambiar la velocidad
            de TODOS los contadores del sistema.                          */
        var RITMO_NUMEROS = 2.2;

        function countUp(el) {
            var target = parseFloat(el.getAttribute('data-count')) || 0;
            var dec    = parseInt(el.getAttribute('data-count-dec') || '0', 10);
            var pre    = el.getAttribute('data-count-prefix') || '';
            var suf    = el.getAttribute('data-count-suffix') || '';
            var dur    = parseInt(el.getAttribute('data-count-dur') || '1500', 10) * RITMO_NUMEROS;
            function fmt(v) {
                return pre + v.toLocaleString('es-CO', {
                    minimumFractionDigits: dec, maximumFractionDigits: dec
                }) + suf;
            }
            if (reduced || target === 0) { el.textContent = fmt(target); return; }
            var t0 = null;
            function step(ts) {
                if (t0 === null) t0 = ts;
                var p = Math.min((ts - t0) / dur, 1);
                el.textContent = fmt(target * easeOutQuart(p));
                if (p < 1) requestAnimationFrame(step);
                else el.textContent = fmt(target);
            }
            el.textContent = fmt(0);
            requestAnimationFrame(step);
        }

        function drawPath(el) {
            var len;
            try { len = el.getTotalLength(); } catch (e) { return; }
            var dur = el.getAttribute('data-draw-dur') || '1700';
            var del = el.getAttribute('data-draw-delay') || '0';
            el.style.transition = 'none';
            el.style.strokeDasharray = len;
            el.style.strokeDashoffset = reduced ? 0 : len;
            void el.getBoundingClientRect();
            el.style.transition = 'stroke-dashoffset ' + dur + 'ms cubic-bezier(.22,1,.36,1) ' + del + 'ms';
            el.style.strokeDashoffset = '0';
        }

        function growBar(el) {
            var to  = el.getAttribute('data-bar') || '0';
            var dir = el.getAttribute('data-bar-dir') || 'x';
            var del = el.getAttribute('data-bar-delay') || '0';
            var prop = dir === 'y' ? 'height' : 'width';
            el.style.transition = 'none';
            el.style[prop] = '0%';
            void el.getBoundingClientRect();
            el.style.transition = prop + ' 1.1s cubic-bezier(.22,1,.36,1) ' + del + 'ms';
            el.style[prop] = to + '%';
        }

        function fillArea(el) {
            var del = el.getAttribute('data-area-delay') || '250';
            el.style.transition = 'none';
            el.style.opacity = '0';
            void el.getBoundingClientRect();
            el.style.transition = 'opacity .85s ease ' + del + 'ms';
            el.style.opacity = el.getAttribute('data-area-opacity') || '1';
        }

        /* ---------- 6. Observador único ---------- */
        var vistos = new WeakSet();

        function activar(el) {
            if (el.hasAttribute('data-reveal')) el.classList.add('is-seen');
            if (el.hasAttribute('data-count'))  countUp(el);
            if (el.hasAttribute('data-draw'))   drawPath(el);
            if (el.hasAttribute('data-bar'))    growBar(el);
            if (el.hasAttribute('data-area'))   fillArea(el);
        }
        function desactivar(el) {
            if (el.hasAttribute('data-reveal')) el.classList.remove('is-seen');
        }

        var io = null;
        if ('IntersectionObserver' in window) {
            io = new IntersectionObserver(function (entradas) {
                entradas.forEach(function (en) {
                    var el = en.target;
                    if (en.isIntersecting) {
                        if (vistos.has(el)) return;
                        vistos.add(el);
                        var d = parseInt(el.getAttribute('data-delay') || '0', 10);
                        if (d > 0) setTimeout(function () { activar(el); }, d);
                        else activar(el);
                    } else if (el.hasAttribute('data-replay') && en.boundingClientRect.top > 0) {
                        vistos.delete(el);
                        desactivar(el);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
        }

        function escanear(scope) {
            var nodos = (scope || document).querySelectorAll(
                '[data-reveal],[data-count],[data-draw],[data-bar],[data-area]'
            );
            Array.prototype.forEach.call(nodos, function (el) {
                if (io) io.observe(el); else activar(el);
            });
        }
        window.musScan = escanear;

        Array.prototype.forEach.call(document.querySelectorAll('[data-stagger]'), function (g) {
            var paso = parseInt(g.getAttribute('data-stagger') || '80', 10);
            var hijos = g.querySelectorAll('[data-reveal]');
            Array.prototype.forEach.call(hijos, function (h, i) {
                if (!h.hasAttribute('data-delay')) h.setAttribute('data-delay', String(i * paso));
            });
        });

        /* ---------- 7. Progreso de lectura y parallax ---------- */
        (function scrollFx() {
            var barra = document.getElementById('musProgress');
            var paralaje = document.querySelectorAll('[data-parallax]');
            if (!barra && !paralaje.length) return;
            var pendiente = false;

            function pintar() {
                pendiente = false;
                var y = window.scrollY || 0;
                if (barra) {
                    var total = document.documentElement.scrollHeight - window.innerHeight;
                    barra.style.width = (total > 0 ? Math.min(y / total, 1) * 100 : 0) + '%';
                }
                if (!reduced) {
                    Array.prototype.forEach.call(paralaje, function (el) {
                        var f = parseFloat(el.getAttribute('data-parallax')) || 0.1;
                        el.style.transform = 'translate3d(0,' + (y * f) + 'px,0)';
                    });
                }
            }
            window.addEventListener('scroll', function () {
                if (!pendiente) { pendiente = true; requestAnimationFrame(pintar); }
            }, { passive: true });
            pintar();
        })();

        /* ---------- 8. Arranque ---------- */
        var gate = document.getElementById('mus-gate');

        function arrancar() {
            body.classList.add('mus-in');
            var items = document.querySelectorAll('.mus-item, .mus-nav__label');
            Array.prototype.forEach.call(items, function (el, i) {
                el.style.animationDelay = (0.04 + i * 0.03) + 's';
            });
            escanear(document);
        }

        if (!gate) {
            arrancar();
        } else if (reduced) {
            gate.remove();
            arrancar();
        } else {
            var st = document.getElementById('musGateStatus');
            var frases = @json(__('mus.puertas.gate_frases'));
            var fi = 0;
            if (st) st.style.transition = 'opacity .24s ease';
            var tick = setInterval(function () {
                fi++;
                if (fi >= frases.length) { clearInterval(tick); return; }
                if (st) {
                    st.style.opacity = '0';
                    setTimeout(function () { st.textContent = frases[fi]; st.style.opacity = '1'; }, 230);
                }
            }, 780);

            var cerrado = false;
            function cerrarGate() {
                if (cerrado) return;
                cerrado = true;
                clearInterval(tick);
                gate.classList.add('is-gone');
                arrancar();
                setTimeout(function () { if (gate.parentNode) gate.remove(); }, 1400);
            }
            setTimeout(cerrarGate, 4100);
            gate.addEventListener('click', cerrarGate);
        }

        /* ---------- 9. Parallax con el mouse ---------- */
        if (!reduced && fino) {
            var flotantes = document.querySelectorAll('[data-float]');
            if (flotantes.length) {
                var tx = 0, ty = 0, cx = 0, cy = 0, raf = null;
                function bucle() {
                    cx += (tx - cx) * 0.055;
                    cy += (ty - cy) * 0.055;
                    Array.prototype.forEach.call(flotantes, function (el) {
                        var d = parseFloat(el.getAttribute('data-float')) || 10;
                        el.style.transform = 'translate3d(' + (cx * d) + 'px,' + (cy * d) + 'px,0)';
                    });
                    if (Math.abs(tx - cx) > 0.0015 || Math.abs(ty - cy) > 0.0015) {
                        raf = requestAnimationFrame(bucle);
                    } else { raf = null; }
                }
                window.addEventListener('mousemove', function (e) {
                    tx = (e.clientX / window.innerWidth - 0.5) * 2;
                    ty = (e.clientY / window.innerHeight - 0.5) * 2;
                    if (!raf) raf = requestAnimationFrame(bucle);
                }, { passive: true });
            }
        }
    })();
    </script>


    {{-- ══════════ Avisos flotantes ══════════ --}}
    <div class="mtoasts" id="musToasts" aria-live="polite"></div>

    {{-- ══════════ Paleta de comandos (Ctrl + K) ══════════ --}}
    @php
        /* Paleta de comandos (Ctrl + K).
           Los textos salen del mismo archivo que el menú lateral: si el
           menú dice «Vender» y la paleta dijera «Punto de venta», el
           usuario creería que son dos pantallas distintas. */
        $irA   = __('mus.ui.ir_a');
        $crear = __('mus.ui.crear_rapido');

        $musCmd = [
            ['g' => $irA, 'r' => 'dashboard',             't' => __('menu.panel'),        's' => __('menu.panel_sub'),        'i' => 'grid'],
            ['g' => $irA, 'r' => 'pos.index',             't' => __('menu.vender'),       's' => __('menu.vender_sub'),       'i' => 'money'],
            ['g' => $irA, 'r' => 'sales.index',           't' => __('menu.ventas'),       's' => __('menu.ventas_sub'),       'i' => 'money'],
            ['g' => $irA, 'r' => 'cash.index',            't' => __('menu.caja'),         's' => __('menu.caja_sub'),         'i' => 'money'],
            ['g' => $irA, 'r' => 'inventory.index',       't' => __('menu.inventario'),   's' => __('menu.inventario_sub'),   'i' => 'box'],
            ['g' => $irA, 'r' => 'reports.index',         't' => __('menu.reportes'),     's' => __('menu.reportes_sub'),     'i' => 'grid'],
            ['g' => $irA, 'r' => 'audit.index',           't' => __('menu.auditoria'),    's' => __('menu.auditoria_sub'),    'i' => 'user'],
            ['g' => $irA, 'r' => 'settings.edit',         't' => __('menu.ajustes'),      's' => __('menu.ajustes_sub'),      'i' => 'tag'],
            ['g' => $irA, 'r' => 'users.index',           't' => __('menu.usuarios'),     's' => __('menu.usuarios_sub'),     'i' => 'user'],
            ['g' => $irA, 'r' => 'products.index',        't' => __('menu.productos'),    's' => __('menu.productos_sub'),    'i' => 'box'],
            ['g' => $irA, 'r' => 'categories.index',      't' => __('menu.categorias'),   's' => __('menu.categorias_sub'),   'i' => 'layers'],
            ['g' => $irA, 'r' => 'customers.index',       't' => __('menu.clientes'),     's' => __('menu.clientes_sub'),     'i' => 'users'],
            ['g' => $irA, 'r' => 'suppliers.index',       't' => __('menu.proveedores'),  's' => __('menu.proveedores_sub'),  'i' => 'truck'],
            ['g' => $irA, 'r' => 'purchases.index',       't' => __('menu.compras'),      's' => __('menu.compras_sub'),      'i' => 'truck'],
            ['g' => $irA, 'r' => 'units.index',           't' => __('menu.unidades'),     's' => __('menu.unidades_sub'),     'i' => 'ruler'],
            ['g' => $irA, 'r' => 'taxes.index',           't' => __('menu.impuestos'),    's' => __('menu.impuestos_sub'),    'i' => 'percent'],
            ['g' => $irA, 'r' => 'payment_methods.index', 't' => __('menu.medios_pago'),  's' => __('menu.medios_pago_sub'),  'i' => 'card'],
            ['g' => $irA, 'r' => 'attributes.index',      't' => __('menu.atributos'),    's' => __('menu.atributos_sub'),    'i' => 'tag'],
            ['g' => $irA, 'r' => 'profile.edit',          't' => __('menu.perfil'),       's' => __('menu.perfil_sub'),       'i' => 'user'],

            ['g' => $crear, 'r' => 'cash.create',       't' => __('menu.abrir_caja'),      's' => __('menu.abrir_caja_sub'),      'i' => 'plus'],
            ['g' => $crear, 'r' => 'users.create',      't' => __('menu.nuevo_usuario'),   's' => __('menu.nuevo_usuario_sub'),   'i' => 'plus'],
            ['g' => $crear, 'r' => 'products.create',   't' => __('menu.nuevo_producto'),  's' => __('menu.nuevo_producto_sub'),  'i' => 'plus'],
            ['g' => $crear, 'r' => 'categories.create', 't' => __('menu.nueva_categoria'), 's' => __('menu.nueva_categoria_sub'), 'i' => 'plus'],
            ['g' => $crear, 'r' => 'customers.create',  't' => __('menu.nuevo_cliente'),   's' => __('menu.nuevo_cliente_sub'),   'i' => 'plus'],
            ['g' => $crear, 'r' => 'suppliers.create',  't' => __('menu.nuevo_proveedor'), 's' => __('menu.nuevo_proveedor_sub'), 'i' => 'plus'],
            ['g' => $crear, 'r' => 'purchases.create',  't' => __('menu.nueva_compra'),    's' => __('menu.nueva_compra_sub'),    'i' => 'plus'],
            ['g' => $crear, 'r' => 'units.create',      't' => __('menu.nueva_unidad'),    's' => __('menu.nueva_unidad_sub'),    'i' => 'plus'],
            ['g' => $crear, 'r' => 'taxes.create',      't' => __('menu.nuevo_impuesto'),  's' => __('menu.nuevo_impuesto_sub'),  'i' => 'plus'],
        ];
        $musCmdItems = [];
        foreach ($musCmd as $c) {
            if (Route::has($c['r'])) {
                $musCmdItems[] = [
                    'g' => $c['g'], 't' => $c['t'], 's' => $c['s'],
                    'i' => $c['i'], 'u' => route($c['r']),
                ];
            }
        }
    @endphp

    <div class="mcmd" id="musCmd" role="dialog" aria-modal="true" aria-label="{{ __('mus.ui.buscar_panel') }}">
        <div class="mcmd__box">
            <div class="mcmd__top">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>
                </svg>
                <input class="mcmd__in" id="musCmdIn" type="text" autocomplete="off" spellcheck="false"
                       placeholder="{{ __('mus.ui.buscar_ph') }}">
                <span class="mcmd__kbd">{{ mb_strtoupper(__('mus.buscador.kbd_esc')) }}</span>
            </div>
            <div class="mcmd__list" id="musCmdList"></div>
            <div class="mcmd__foot">
                <span><b>↑ ↓</b> {{ __('mus.ui.moverse') }}</span>
                <span><b>{{ __('mus.buscador.kbd_enter') }}</b> {{ __('mus.ui.abrir') }}</span>
                <span><b>{{ __('mus.buscador.kbd_esc') }}</b> {{ __('mus.ui.cerrar_kbd') }}</span>
            </div>
        </div>
    </div>

    <script>
    /* ══════════════════════════════════════════════════════════════
       Capa de interfaz: avisos, paleta de comandos, transiciones
       ══════════════════════════════════════════════════════════════ */
    (function () {
        'use strict';

        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var body = document.body;

        /* ---------- 1. Avisos flotantes ---------- */
        var host = document.getElementById('musToasts');

        var ICO = {
            ok:   '<path d="M20 6 9 17l-5-5"/>',
            bad:  '<path d="M12 8v5M12 16.5v.01"/><circle cx="12" cy="12" r="9"/>',
            info: '<path d="M12 16v-5M12 8.5v.01"/><circle cx="12" cy="12" r="9"/>'
        };

        function toast(tipo, titulo, texto) {
            if (!host) return;
            var t = document.createElement('div');
            t.className = 'mtoast mtoast--' + tipo;
            t.innerHTML =
                '<span class="mtoast__ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
                'stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">' + (ICO[tipo] || ICO.info) + '</svg></span>' +
                '<span class="mtoast__txt"><b></b>' + (texto ? '<span></span>' : '') + '</span>' +
                '<button class="mtoast__x" aria-label=' + JSON.stringify(@json(__('mus.acciones.cerrar'))) + '><svg viewBox="0 0 24 24" fill="none" ' +
                'stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>' +
                '<span class="mtoast__bar"><i></i></span>';
            t.querySelector('.mtoast__txt b').textContent = titulo;
            if (texto) t.querySelector('.mtoast__txt span').textContent = texto;

            function cerrar() {
                if (t.classList.contains('out')) return;
                t.classList.add('out');
                setTimeout(function () { if (t.parentNode) t.remove(); }, 420);
            }
            t.querySelector('.mtoast__x').addEventListener('click', cerrar);
            host.appendChild(t);
            setTimeout(cerrar, 5400);
            return t;
        }
        window.musToast = toast;

        @if (session('success'))
            toast('ok', 'Listo', @json(session('success')));
        @endif
        @if (session('error'))
            toast('bad', 'Algo falló', @json(session('error')));
        @endif
        @if ($errors->any() && ! session('success'))
            toast('bad', 'Revisa el formulario', @json($errors->count() . ' campo(s) necesitan atención.'));
        @endif


        /* ---------- Reloj de la demostración ---------- */
        (function relojDemo() {
            var banda = document.getElementById('musDemoBanda');
            var reloj = document.getElementById('musDemoReloj');
            if (!banda || !reloj) return;

            var quedan = parseInt(banda.getAttribute('data-min'), 10);
            if (isNaN(quedan)) return;

            /* Se descuenta en el navegador y NO se le cree: el que decide si
               la prueba sigue viva es el servidor, en cada petición. Esto es
               solo para que el número no se quede congelado en la pantalla
               de alguien que lleva media hora sin hacer clic. */
            function pintar() {
                if (quedan <= 0) {
                    reloj.textContent = 'terminó';
                    banda.classList.add('is-poco');
                    return;
                }

                var h = Math.floor(quedan / 60);
                var m = quedan % 60;

                reloj.textContent = h > 0 ? (h + ' h ' + m + ' min') : (m + ' min');
                banda.classList.toggle('is-poco', quedan <= 15);
            }

            pintar();

            setInterval(function () { quedan -= 1; pintar(); }, 60000);
        })();

        /* ---------- Negocio y local activos ---------- */
        (function contexto() {

            /* --- El desplegable --- */
            var caja = document.getElementById('musCtx');
            var btn  = document.getElementById('musCtxBtn');

            if (caja && btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var abierto = caja.classList.toggle('is-on');
                    btn.setAttribute('aria-expanded', abierto ? 'true' : 'false');
                });

                document.addEventListener('click', function (e) {
                    if (!caja.contains(e.target)) {
                        caja.classList.remove('is-on');
                        btn.setAttribute('aria-expanded', 'false');
                    }
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        caja.classList.remove('is-on');
                        btn.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            /* --- El sello de las pestañas ---

               Cada formulario que escribe se va con la firma del contexto
               con el que se dibujó esta pantalla. Si el usuario cambió de
               local en otra pestaña, el servidor lo nota y no guarda nada.

               Se hace aquí y no formulario por formulario a propósito: son
               más de noventa vistas, y la que se olvidara sería justo la
               que un día escriba donde no es. Un solo sitio, y quedan
               todos —incluidos los que aún no existen—.

               Va en fase de captura porque las ventanas flotantes atrapan
               el «submit» y mandan el formulario por su cuenta: la captura
               corre antes, así que el sello ya está puesto cuando el otro
               código arma el envío. */
            var meta  = document.querySelector('meta[name="mus-ctx"]');
            var firma = meta ? meta.getAttribute('content') : '';

            if (!firma) return;

            document.addEventListener('submit', function (e) {
                var form = e.target;

                if (!form || form.tagName !== 'FORM') return;

                // Los formularios de consulta —buscadores, filtros— no
                // escriben nada, y sellarlos solo ensuciaría la dirección.
                if ((form.method || 'get').toLowerCase() === 'get') return;

                // El propio cambio de local va sin sello: es la manera de
                // ponerse al día, no puede exigir estar al día.
                if (form.hasAttribute('data-sin-ctx')) return;

                var campo = form.querySelector('input[name="_ctx"]');

                if (!campo) {
                    campo = document.createElement('input');
                    campo.type = 'hidden';
                    campo.name = '_ctx';
                    form.appendChild(campo);
                }

                campo.value = firma;
            }, true);
        })();

        /* ---------- Campana de avisos ---------- */
        (function campana() {
            var caja = document.getElementById('musBell');
            var btn  = document.getElementById('musBellBtn');
            if (!caja || !btn) return;

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                caja.classList.toggle('is-on');
            });

            document.addEventListener('click', function (e) {
                if (!caja.contains(e.target)) caja.classList.remove('is-on');
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') caja.classList.remove('is-on');
            });
        })();

        /* ---------- 2. Paleta de comandos ---------- */
        var CMD = @json($musCmdItems);
        var cmd  = document.getElementById('musCmd');
        var cin  = document.getElementById('musCmdIn');
        var list = document.getElementById('musCmdList');
        var sel  = 0, filtrados = [];

        var CICO = {
            grid:'<rect x="3.2" y="3.2" width="7.2" height="7.2" rx="2"/><rect x="13.6" y="3.2" width="7.2" height="7.2" rx="2"/><rect x="3.2" y="13.6" width="7.2" height="7.2" rx="2"/><rect x="13.6" y="13.6" width="7.2" height="7.2" rx="2"/>',
            box:'<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
            layers:'<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
            users:'<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M17 8.2a3 3 0 0 1 0 5.6"/>',
            truck:'<path d="M2 7h11v10H2z"/><path d="M13 10h4l4 3.5V17h-8z"/><circle cx="6.5" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/>',
            ruler:'<rect x="2.5" y="8.5" width="19" height="7" rx="1.6"/><path d="M7 8.5v3M11 8.5v4.5M15 8.5v3"/>',
            percent:'<path d="M19 5 5 19"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/>',
            card:'<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6 15h4"/>',
            tag:'<path d="M20.5 12.5 12 21l-9-9V3h9z"/><circle cx="7.5" cy="7.5" r="1.6"/>',
            user:'<circle cx="12" cy="8" r="3.4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
            plus:'<path d="M12 5v14M5 12h14"/>',
            money:'<rect x="2.5" y="6" width="19" height="12" rx="2.5"/><circle cx="12" cy="12" r="2.6"/><path d="M6 12h.01M18 12h.01"/>'
        };

        /* Resultados que llegan del servidor mientras se escribe. */
        var REMOTOS = [];
        var buscando = false;
        var temporizador = null;
        var ultimaQ = '';
        var URL_BUSCAR = @json(route('buscar'));

        function normaliza(s) {
            return (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function pinta() {
            if (!list) return;
            var q = normaliza(cin.value.trim());

            var secciones = CMD.filter(function (c) {
                if (!q) return true;
                return normaliza(c.t).indexOf(q) > -1 || normaliza(c.s).indexOf(q) > -1 || normaliza(c.g).indexOf(q) > -1;
            });

            // Primero lo que se encontró en la base de datos, después las secciones.
            filtrados = REMOTOS.concat(secciones);

            if (sel >= filtrados.length) sel = Math.max(0, filtrados.length - 1);

            if (!filtrados.length) {
                list.innerHTML = buscando
                    ? '<div class="mcmd__none">Buscando…</div>'
                    : '<div class="mcmd__none">Nada coincide con esa búsqueda.</div>';
                return;
            }
            var html = '', grupo = null;
            filtrados.forEach(function (c, i) {
                if (c.g !== grupo) { grupo = c.g; html += '<div class="mcmd__grp">' + grupo + '</div>'; }
                html += '<a class="mcmd__item' + (i === sel ? ' sel' : '') + '" href="' + c.u + '" data-i="' + i + '">' +
                    '<span class="i"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" ' +
                    'stroke-linecap="round" stroke-linejoin="round">' + (CICO[c.i] || CICO.tag) + '</svg></span>' +
                    '<span class="t"><b></b><span></span></span>' +
                    '<span class="go"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" ' +
                    'stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></a>';
            });
            list.innerHTML = html;
            /* Texto por nodo, para no inyectar HTML de los datos */
            Array.prototype.forEach.call(list.querySelectorAll('.mcmd__item'), function (el, i) {
                el.querySelector('.t b').textContent = filtrados[i].t;
                el.querySelector('.t span').textContent = filtrados[i].s;
                el.addEventListener('mouseenter', function () { sel = i; marca(); });
            });
        }

        function marca() {
            Array.prototype.forEach.call(list.querySelectorAll('.mcmd__item'), function (el, i) {
                el.classList.toggle('sel', i === sel);
                if (i === sel && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
            });
        }

        function abrirCmd() {
            if (!cmd) return;
            cmd.classList.add('on');
            cin.value = ''; sel = 0;
            REMOTOS = []; ultimaQ = ''; buscando = false;
            pinta();
            setTimeout(function () { cin.focus(); }, 60);
        }
        function cerrarCmd() { if (cmd) cmd.classList.remove('on'); }

        /* Busca en productos, clientes, ventas y compras. */
        function buscarEnServidor() {
            var q = cin.value.trim();

            if (q.length < 2) {
                REMOTOS = []; ultimaQ = ''; buscando = false; pinta();
                return;
            }

            if (q === ultimaQ) return;
            ultimaQ = q;
            buscando = true;

            fetch(URL_BUSCAR + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.ok ? r.json() : { items: [] }; })
                .then(function (d) {
                    // Si el usuario ya escribió otra cosa, se descarta esta respuesta.
                    if (cin.value.trim() !== q) return;
                    REMOTOS = (d && d.items) || [];
                    buscando = false;
                    sel = 0;
                    pinta();
                })
                .catch(function () { REMOTOS = []; buscando = false; pinta(); });
        }

        if (cmd && cin && list) {
            cin.addEventListener('input', function () {
                sel = 0;
                pinta();
                clearTimeout(temporizador);
                temporizador = setTimeout(buscarEnServidor, 220);
            });
            cin.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel + 1, filtrados.length - 1); marca(); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel - 1, 0); marca(); }
                else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (filtrados[sel]) window.location.href = filtrados[sel].u;
                }
            });
            cmd.addEventListener('click', function (e) { if (e.target === cmd) cerrarCmd(); });
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
                    e.preventDefault();
                    cmd.classList.contains('on') ? cerrarCmd() : abrirCmd();
                } else if (e.key === 'Escape' && cmd.classList.contains('on')) {
                    cerrarCmd();
                }
            });
            Array.prototype.forEach.call(document.querySelectorAll('[data-cmd-open]'), function (b) {
                b.addEventListener('click', abrirCmd);
            });
        }

        /* ---------- 3. Transición al navegar entre secciones ---------- */
        (function navegacion() {
            if (reduced) return;
            var barra = document.getElementById('musProgress');

            document.addEventListener('click', function (e) {
                if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
                var a = e.target.closest ? e.target.closest('a') : null;
                if (!a || !a.getAttribute('href')) return;
                if (a.target === '_blank' || a.hasAttribute('download') || a.dataset.noTransition !== undefined) return;
                /* Los que abren ventana flotante no navegan a ningún lado */
                if (a.hasAttribute('data-modal')) return;

                var url;
                try { url = new URL(a.href, window.location.origin); } catch (err) { return; }
                if (url.origin !== window.location.origin) return;
                if (url.href === window.location.href) return;
                if (a.getAttribute('href').charAt(0) === '#') return;
                /* El portal de acceso tiene su propio barrido */
                if (/\/(login|register|logout)$/.test(url.pathname)) return;

                e.preventDefault();
                body.classList.add('mus-leaving');
                if (barra) { barra.style.transition = 'width .45s ease'; barra.style.width = '72%'; }
                setTimeout(function () { window.location.href = a.href; }, 260);
            });

            window.addEventListener('pageshow', function (e) {
                if (e.persisted) {
                    body.classList.remove('mus-leaving');
                    if (barra) barra.style.width = '0%';
                }
            });
        })();

        /* ---------- 4. Micro-interacciones ---------- */
        /* Onda al pulsar botones */
        document.addEventListener('pointerdown', function (e) {
            if (reduced) return;
            var b = e.target.closest ? e.target.closest('.mb') : null;
            if (!b) return;
            var r = b.getBoundingClientRect();
            var w = document.createElement('span');
            w.className = 'mb__wave';
            w.style.left = (e.clientX - r.left) + 'px';
            w.style.top  = (e.clientY - r.top) + 'px';
            b.appendChild(w);
            setTimeout(function () { if (w.parentNode) w.remove(); }, 700);
        });

        /* Luz que sigue al cursor */
        (function spotlight() {
            if (reduced || !window.matchMedia('(pointer:fine)').matches) return;
            var objetivos = document.querySelectorAll('.mp, .d-card, .d-panel, .mempty');
            Array.prototype.forEach.call(objetivos, function (el) {
                el.setAttribute('data-spot', '');
                el.addEventListener('pointermove', function (e) {
                    var r = el.getBoundingClientRect();
                    el.style.setProperty('--mx', (e.clientX - r.left) + 'px');
                    el.style.setProperty('--my', (e.clientY - r.top) + 'px');
                });
            });
        })();

        /* Filtro instantáneo de la tabla visible */
        (function filtroTabla() {
            var campo = document.querySelector('[data-table-filter]');
            if (!campo) return;
            var tabla = document.querySelector('.mt tbody');
            if (!tabla) return;
            var contador = document.querySelector('[data-filter-count]');
            var textoOriginal = contador ? contador.textContent : '';
            var filas = Array.prototype.slice.call(tabla.querySelectorAll('tr'));
            var cache = filas.map(function (tr) {
                return (tr.textContent || '').toLowerCase()
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            });

            var vacio = null;
            function asegurarVacio() {
                if (vacio) return vacio;
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                td.colSpan = 12;
                td.style.cssText = 'padding:34px 16px;text-align:center;color:var(--muted-2);font-size:13px';
                td.textContent = 'Ningún registro de esta página coincide con el filtro.';
                tr.appendChild(td);
                tabla.appendChild(tr);
                vacio = tr;
                return tr;
            }

            var t = null;
            campo.addEventListener('input', function () {
                clearTimeout(t);
                t = setTimeout(function () {
                    var q = campo.value.trim().toLowerCase()
                        .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    var visibles = 0;
                    filas.forEach(function (tr, i) {
                        var ok = !q || cache[i].indexOf(q) > -1;
                        tr.style.display = ok ? '' : 'none';
                        if (ok) visibles++;
                    });
                    if (contador) {
                        contador.textContent = q
                            ? visibles + ' de ' + filas.length + ' en esta página'
                            : textoOriginal;
                    }
                    asegurarVacio().style.display = (q && visibles === 0) ? '' : 'none';
                }, 90);
            });
            asegurarVacio().style.display = 'none';
        })();

        /* Filas de tabla escalonadas */
        Array.prototype.forEach.call(document.querySelectorAll('.mt tbody tr[data-row]'), function (tr, i) {
            tr.style.animationDelay = (0.02 + i * 0.035) + 's';
        });

        /* ══════════════════════════════════════════════════════════════
           Tablas legibles en celular
           En pantalla angosta el CSS convierte cada fila en una tarjeta,
           pero una tarjeta sin etiquetas no se entiende. Aquí se copia el
           encabezado de cada columna al atributo data-th de su celda, que
           es lo que el CSS pinta con ::before. Se hace en JS —y no a mano
           en cada vista— para que valga para las 25 tablas del sistema y
           también para las filas que se agregan después (compras,
           devoluciones).
           ══════════════════════════════════════════════════════════════ */
        (function etiquetarCeldas() {
            function marcar(tabla) {
                var ths = tabla.querySelectorAll('thead th');
                if (!ths.length) return;

                var titulos = Array.prototype.map.call(ths, function (th) {
                    return (th.textContent || '').trim();
                });

                Array.prototype.forEach.call(tabla.querySelectorAll('tbody tr'), function (tr) {
                    // Las filas con colspan (mensajes de «sin resultados») se dejan quietas.
                    if (tr.children.length !== titulos.length) return;

                    Array.prototype.forEach.call(tr.children, function (td, i) {
                        if (titulos[i]) {
                            td.setAttribute('data-th', titulos[i]);
                        } else {
                            td.removeAttribute('data-th');
                        }
                    });
                });
            }

            // Se expone para poder reetiquetar lo que llegue por fetch
            // (los formularios que se abren en una ventana flotante).
            window.musEtiquetarTablas = function () {
                Array.prototype.forEach.call(document.querySelectorAll('table.mt'), marcar);
            };

            var tablas = document.querySelectorAll('table.mt');
            if (!tablas.length) return;

            Array.prototype.forEach.call(tablas, function (tabla) {
                marcar(tabla);

                // Las tablas editables crecen y se encogen mientras el
                // usuario trabaja; hay que reetiquetar cuando eso pasa.
                var cuerpo = tabla.querySelector('tbody');
                if (!cuerpo || !window.MutationObserver) return;

                var pendiente = false;
                new MutationObserver(function () {
                    if (pendiente) return;
                    pendiente = true;
                    requestAnimationFrame(function () {
                        pendiente = false;
                        marcar(tabla);
                    });
                }).observe(cuerpo, { childList: true, subtree: true });
            });
        })();

        /* ══════════════════════════════════════════════════════════════
           VENTANAS FLOTANTES
           «Nuevo producto» abre el formulario encima de la lista en vez de
           llevarte a otra página. No hay una vista aparte para el modal:
           se pide la MISMA ruta de siempre (products.create) con la
           cabecera X-Mus-Modal, y el layout devuelve solo el formulario.
           Así, si el JS falla o alguien abre el enlace en otra pestaña,
           la página completa sigue funcionando igual que antes.
           ══════════════════════════════════════════════════════════════ */
        (function ventanasFlotantes() {
            var modal  = document.getElementById('musMod');
            if (!modal || !window.fetch) { return; }

            var cuerpo = document.getElementById('musModCuerpo');
            var titulo = document.getElementById('musModTitulo');
            var pie    = document.getElementById('musModPie');
            var caja   = document.getElementById('musModCaja');

            var urlAbierta = '';
            var enviando   = false;
            var focoPrevio = null;

            function absoluta(u) {
                try { return new URL(u, location.href).href.split('#')[0]; }
                catch (e) { return u; }
            }

            function cerrar() {
                if (!modal.classList.contains('is-on')) { return; }
                modal.classList.remove('is-on');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                setTimeout(function () {
                    if (!modal.classList.contains('is-on')) {
                        cuerpo.innerHTML = '';
                        pie.innerHTML = '';
                        pie.hidden = true;
                    }
                }, 280);
                if (focoPrevio && focoPrevio.focus) { focoPrevio.focus(); }
            }

            function cargando() {
                cuerpo.innerHTML =
                    '<div class="mmod__cargando"><span class="mmod__spin"></span><p>Abriendo…</p></div>';
                pie.hidden = true;
                pie.innerHTML = '';
            }

            /* innerHTML no ejecuta los <script> que vengan dentro: hay que
               volver a crearlos a mano para que el formulario funcione
               (vista previa de la foto, renglones de compra, etc.). */
            function revivirScripts(raiz) {
                Array.prototype.forEach.call(raiz.querySelectorAll('script'), function (viejo) {
                    var nuevo = document.createElement('script');
                    Array.prototype.forEach.call(viejo.attributes, function (a) {
                        nuevo.setAttribute(a.name, a.value);
                    });
                    nuevo.text = viejo.textContent;
                    viejo.parentNode.replaceChild(nuevo, viejo);
                });
            }

            function pintar(html) {
                cuerpo.innerHTML = html;
                revivirScripts(cuerpo);

                /* Los paneles nacen invisibles esperando a que el
                   observador de scroll los muestre, y ese observador ya
                   terminó su ronda. Aquí se les marca como vistos para que
                   el formulario aparezca de una. */
                Array.prototype.forEach.call(cuerpo.querySelectorAll('[data-reveal]'), function (el) {
                    el.classList.add('is-seen');
                });

                var form = cuerpo.querySelector('form');

                /* El pie del panel trae los botones reales; se ocultan por
                   CSS y se rehacen aquí abajo, donde siempre se ven. */
                var enviar = cuerpo.querySelector('.mp__foot [type="submit"], .mp__foot button');
                var etiqueta = enviar ? enviar.textContent.trim() : 'Guardar';

                pie.innerHTML = '';
                pie.hidden = false;

                var cancelar = document.createElement('button');
                cancelar.type = 'button';
                cancelar.className = 'mb mb--ghost';
                cancelar.textContent = 'Cancelar';
                cancelar.setAttribute('data-mod-cerrar', '');
                pie.appendChild(cancelar);

                if (form) {
                    var guardar = document.createElement('button');
                    guardar.type = 'button';
                    guardar.className = 'mb mb--primary';
                    guardar.textContent = etiqueta;
                    guardar.addEventListener('click', function () {
                        if (form.requestSubmit) { form.requestSubmit(); }
                        else { form.dispatchEvent(new Event('submit', { cancelable: true })); }
                    });
                    pie.appendChild(guardar);

                    form.addEventListener('submit', function (ev) {
                        ev.preventDefault();
                        mandar(form, guardar);
                    });

                    var primero = form.querySelector('input:not([type=hidden]):not([disabled]), select, textarea');
                    if (primero) { setTimeout(function () { primero.focus(); }, 260); }
                }

                // Si el servidor devolvió errores, se sube a verlos.
                if (cuerpo.querySelector('.mfld--err, .mfld__err')) {
                    cuerpo.scrollTop = 0;
                }

                if (window.musEtiquetarTablas) { window.musEtiquetarTablas(); }
            }

            function mandar(form, boton) {
                if (enviando) { return; }
                enviando = true;

                var textoPrevio = boton ? boton.textContent : '';
                if (boton) { boton.disabled = true; boton.textContent = 'Guardando…'; }

                fetch(form.action, {
                    method: (form.method || 'POST').toUpperCase(),
                    body: new FormData(form),
                    headers: { 'X-Mus-Modal': '1', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    redirect: 'follow'
                })
                .then(function (r) { return r.text().then(function (t) { return { r: r, t: t }; }); })
                .then(function (d) {
                    enviando = false;
                    if (boton) { boton.disabled = false; boton.textContent = textoPrevio; }

                    /* Laravel redirige cuando guarda bien y devuelve al
                       mismo formulario cuando hay errores. Dos señales, por
                       si una falla: terminar en otra dirección, y que la
                       respuesta no traiga mensajes de validación. */
                    var volvio    = absoluta(d.r.url) === absoluta(urlAbierta);
                    var conFallos = d.t.indexOf('mfld--err') > -1 || d.t.indexOf('mfld__err') > -1;

                    if (d.r.ok && ! volvio && ! conFallos) {
                        location.href = d.r.url;
                        return;
                    }

                    pintar(d.t);
                })
                .catch(function () {
                    enviando = false;
                    if (boton) { boton.disabled = false; boton.textContent = textoPrevio; }
                    if (window.musToast) {
                        window.musToast('bad', 'No se pudo guardar',
                            'Revisa la conexión e inténtalo otra vez.');
                    }
                });
            }

            function abrir(url, texto, ancho) {
                focoPrevio = document.activeElement;
                urlAbierta = url;

                titulo.textContent = texto || @json(__('mus.puertas.cargando'));
                caja.style.setProperty('--mmw', (parseInt(ancho, 10) || 760) + 'px');

                cargando();
                modal.classList.add('is-on');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';

                fetch(url, {
                    headers: { 'X-Mus-Modal': '1', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                .then(function (r) {
                    if (!r.ok) { throw new Error(r.status); }
                    return r.text();
                })
                .then(pintar)
                .catch(function () {
                    // Si algo falla, se abre la página de siempre: nunca
                    // se deja al usuario con una ventana vacía.
                    location.href = url;
                });
            }

            /* --- Apertura --- */
            document.addEventListener('click', function (e) {
                var enlace = e.target.closest('[data-modal]');
                if (!enlace || e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) { return; }

                var url = enlace.getAttribute('href');
                if (!url || url.charAt(0) === '#') { return; }

                e.preventDefault();
                abrir(url, enlace.getAttribute('data-modal'), enlace.getAttribute('data-modal-ancho'));
            });

            /* --- Cierre --- */
            document.addEventListener('click', function (e) {
                if (e.target.closest('[data-mod-cerrar]')) { cerrar(); }
            });

            modal.addEventListener('click', function (e) {
                if (e.target === modal) { cerrar(); }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-on')) { cerrar(); }
            });
        })();

        /* En celular la barra superior se esconde al bajar y reaparece al
           subir, para que la pantalla completa quede para el contenido. */
        (function barraQueSeEsconde() {
            var barra = document.querySelector('.mus-top');
            if (!barra) return;

            var ultimo = window.scrollY;
            var arrastre = 0;

            window.addEventListener('scroll', function () {
                if (window.innerWidth > 1024) {
                    document.body.classList.remove('mus-top-off');
                    return;
                }

                var y = window.scrollY;
                var paso = y - ultimo;
                ultimo = y;

                // Cerca del tope la barra siempre se ve.
                if (y < 90) {
                    arrastre = 0;
                    document.body.classList.remove('mus-top-off');
                    return;
                }

                // Se acumula el movimiento en un mismo sentido para no
                // reaccionar a cada temblor del dedo.
                arrastre = (paso > 0) === (arrastre > 0) ? arrastre + paso : paso;

                if (arrastre > 70) { document.body.classList.add('mus-top-off'); }
                if (arrastre < -45) { document.body.classList.remove('mus-top-off'); }
            }, { passive: true });
        })();

        /* Confirmación de borrado sin cuadro nativo feo */
        Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm]'), function (f) {
            f.addEventListener('submit', function (e) {
                if (f.dataset.ok === '1') return;
                e.preventDefault();
                var msg = f.getAttribute('data-confirm') || '¿Seguro que quieres eliminarlo?';
                if (window.confirm(msg)) { f.dataset.ok = '1'; f.submit(); }
            });
        });
    })();
    </script>

    @stack('scripts')
</body>
</html>
@endif
