@php
    /**
     * Página de error del sistema. Autocontenida: no depende de Vite ni
     * del layout del panel, así se ve bien incluso si algo se rompió.
     */
    $codigo = $codigo ?? 500;
    $titulo = $titulo ?? 'Algo salió mal';
    $texto  = $texto  ?? 'Ocurrió un error inesperado. Inténtalo de nuevo en un momento.';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $codigo }} · {{ $titulo }} — MEGA UNI STORE</title>
    <style>
        *,*::before,*::after{ box-sizing:border-box; }
        :root{
            --n-950:#070D14; --n-900:#0A1119; --n-850:#0E1723; --n-800:#142033;
            --a-600:#215480; --a-500:#2E6EA8; --a-400:#4A8FC9; --a-300:#7FB2DE;
            --e-soft:cubic-bezier(.22,1,.36,1);
        }
        html,body{ height:100%; margin:0; }
        body{
            display:grid; place-items:center; padding:28px;
            background:var(--n-950);
            font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
            color:#DCE5F0; overflow:hidden;
        }
        .bg{ position:fixed; inset:0; pointer-events:none; }
        .bg i{
            position:absolute; border-radius:50%; opacity:.5;
            background:radial-gradient(circle at 30% 30%, rgba(46,110,168,.5), transparent 68%);
            filter:blur(52px); animation:flota 22s var(--e-soft) infinite alternate;
        }
        .bg i:nth-child(1){ width:520px; height:520px; top:-160px; left:-120px; }
        .bg i:nth-child(2){ width:420px; height:420px; bottom:-140px; right:-90px; animation-delay:-7s; }
        .bg i:nth-child(3){ width:300px; height:300px; top:44%; left:58%; animation-delay:-13s; opacity:.32; }
        @keyframes flota{ from{ transform:translate3d(0,0,0) scale(1); }
                          to  { transform:translate3d(34px,-28px,0) scale(1.12); } }

        .bg u{
            position:absolute; border:1px solid rgba(122,164,208,.09); border-radius:50%;
            left:50%; top:50%; translate:-50% -50%; animation:pulso 9s ease-out infinite;
        }
        .bg u:nth-child(4){ width:340px; height:340px; }
        .bg u:nth-child(5){ width:560px; height:560px; animation-delay:-3s; }
        .bg u:nth-child(6){ width:800px; height:800px; animation-delay:-6s; }
        @keyframes pulso{ 0%{ opacity:0; transform:scale(.86); }
                          22%{ opacity:1; } 100%{ opacity:0; transform:scale(1.14); } }

        .caja{
            position:relative; width:min(520px,100%); padding:44px 40px 38px; text-align:center;
            background:linear-gradient(168deg, rgba(20,32,51,.94), rgba(10,17,25,.96));
            border:1px solid rgba(122,164,208,.13); border-radius:20px;
            box-shadow:0 40px 90px -50px #000, inset 0 1px 0 rgba(255,255,255,.05);
            animation:entra .8s var(--e-soft) both;
        }
        @keyframes entra{ from{ opacity:0; transform:translateY(18px) scale(.97); } }

        .ico{
            display:grid; place-items:center; width:62px; height:62px; margin:0 auto 20px;
            border-radius:18px; color:#fff;
            background:linear-gradient(145deg,var(--a-600),var(--a-500));
            box-shadow:0 16px 34px -18px var(--a-500);
            animation:late 3.4s var(--e-soft) infinite;
        }
        @keyframes late{ 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-5px); } }
        .ico svg{ width:28px; height:28px; }

        .cod{
            font-size:12px; font-weight:700; letter-spacing:.24em; text-transform:uppercase;
            color:var(--a-300); margin:0 0 8px;
        }
        h1{ margin:0 0 10px; font-size:25px; font-weight:700; letter-spacing:-.03em; color:#fff; }
        p{ margin:0 0 26px; font-size:14.4px; line-height:1.62; color:#93A6BF; }

        .btns{ display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }
        a.b{
            display:inline-flex; align-items:center; gap:8px; padding:11px 20px; border-radius:11px;
            font-size:13.4px; font-weight:600; text-decoration:none;
            transition:transform .2s var(--e-soft), box-shadow .2s var(--e-soft), background .2s;
        }
        a.b--p{ background:linear-gradient(145deg,var(--a-600),var(--a-500)); color:#fff;
                box-shadow:0 14px 30px -16px var(--a-500); }
        a.b--g{ background:rgba(255,255,255,.05); color:#C4D2E4; border:1px solid rgba(122,164,208,.16); }
        a.b:hover{ transform:translateY(-2px); }
        a.b--g:hover{ background:rgba(255,255,255,.09); }

        .marca{ margin-top:28px; font-size:10.8px; letter-spacing:.18em; text-transform:uppercase;
                color:#4E617C; }

        @media (prefers-reduced-motion:reduce){ *{ animation:none !important; } }
    </style>
</head>
<body>
    <div class="bg" aria-hidden="true">
        <i></i><i></i><i></i><u></u><u></u><u></u>
    </div>

    <main class="caja">
        <span class="ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round">
                {!! $icono ?? '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/>' !!}
            </svg>
        </span>

        <p class="cod">Error {{ $codigo }}</p>
        <h1>{{ $titulo }}</h1>
        <p>{{ $texto }}</p>

        <div class="btns">
            @auth
                <a class="b b--p" href="{{ route('dashboard') }}">Ir al panel</a>
            @else
                <a class="b b--p" href="{{ route('login') }}">Iniciar sesión</a>
            @endauth
            <a class="b b--g" href="javascript:history.back()">Volver atrás</a>
        </div>

        <p class="marca">MEGA UNI STORE</p>
    </main>
</body>
</html>
