<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0E1723">

        <title>{{ config('app.name', 'MEGA UNI STORE') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* Fondo coherente con la pantalla de autenticación principal. */
            body.mus-guest{
                font-family:'Inter',ui-sans-serif,system-ui,sans-serif;
                background:
                    radial-gradient(1100px 650px at 12% 8%,  rgba(46,110,168,.28), transparent 60%),
                    radial-gradient(850px 550px at 88% 92%,  rgba(74,143,201,.20), transparent 60%),
                    linear-gradient(160deg,#0A1119 0%,#0E1723 45%,#142033 100%);
                min-height:100vh;
            }
            .mus-orb{ position:fixed; border-radius:50%; filter:blur(70px); opacity:.45; pointer-events:none; z-index:0; }
            .mus-orb.a{ width:480px; height:480px; top:-140px; left:-120px;
                        background:radial-gradient(circle at 30% 30%,#2E6EA8,transparent 68%);
                        animation:musFloatA 19s cubic-bezier(.65,0,.35,1) infinite; }
            .mus-orb.b{ width:420px; height:420px; bottom:-150px; right:-100px;
                        background:radial-gradient(circle at 60% 40%,#4A8FC9,transparent 68%);
                        animation:musFloatB 23s cubic-bezier(.65,0,.35,1) infinite; }
            .mus-orb.c{ width:300px; height:300px; top:12%; right:14%; opacity:.35;
                        background:radial-gradient(circle at 45% 55%,#215480,transparent 70%);
                        animation:musFloatC 29s cubic-bezier(.65,0,.35,1) infinite; }
            @keyframes musFloatA{ 50%{ transform:translate(85px,64px) scale(1.16) rotate(26deg); } }
            @keyframes musFloatB{ 50%{ transform:translate(-72px,-54px) scale(1.11) rotate(-22deg); } }
            @keyframes musFloatC{ 50%{ transform:translate(-56px,72px) scale(1.18) rotate(30deg); } }

            /* Anillos y polígonos girando muy lento */
            .mus-ring{ position:fixed; border-radius:50%; pointer-events:none; z-index:0;
                       border:1px solid rgba(127,178,222,.12); }
            .mus-ring.r1{ width:540px; height:540px; top:-150px; left:-160px;
                          animation:musSpin 48s linear infinite; }
            .mus-ring.r2{ width:760px; height:760px; top:-260px; left:-270px;
                          border-style:dashed; border-color:rgba(127,178,222,.08);
                          animation:musSpin 82s linear reverse infinite; }
            .mus-ring.r3{ width:400px; height:400px; bottom:-130px; right:-110px;
                          border-color:rgba(74,143,201,.14);
                          animation:musSpin 58s linear infinite; }
            @keyframes musSpin{ to{ transform:rotate(360deg); } }

            .mus-poly{ position:fixed; pointer-events:none; z-index:0; opacity:.5; }
            .mus-poly svg{ width:100%; height:100%; }
            .mus-poly.p1{ width:130px; height:130px; top:18%; right:12%; }
            .mus-poly.p1 svg{ animation:musPolyA 27s cubic-bezier(.65,0,.35,1) infinite; }
            .mus-poly.p2{ width:92px; height:92px; bottom:18%; left:9%; }
            .mus-poly.p2 svg{ animation:musPolyA 35s cubic-bezier(.65,0,.35,1) reverse infinite; }
            @keyframes musPolyA{
                0%,100%{ transform:rotate(0) translateY(0); }
                50%{ transform:rotate(180deg) translateY(-24px); }
            }

            .mus-wrap{ position:relative; z-index:2; }

            .mus-brand{ display:flex; align-items:center; gap:11px; color:#fff; text-decoration:none;
                        animation:musIn .6s cubic-bezier(.22,1,.36,1) both; }
            .mus-brand svg{ width:38px; height:38px; }
            .mus-brand b{ font-size:14.5px; font-weight:700; letter-spacing:.16em; }

            .mus-card{
                background:#fff; border-radius:10px; padding:36px 34px;
                box-shadow:0 36px 80px -30px rgba(5,10,18,.7), 0 0 0 1px rgba(255,255,255,.07);
                animation:musIn .6s cubic-bezier(.22,1,.36,1) .1s both;
            }
            @keyframes musIn{ from{ opacity:0; transform:translateY(22px) scale(.97); } }

            .mus-back{ display:inline-flex; align-items:center; gap:6px; margin-top:22px;
                       color:rgba(183,202,221,.62); font-size:13px; text-decoration:none;
                       transition:color .2s ease; animation:musIn .6s cubic-bezier(.22,1,.36,1) .2s both; }
            .mus-back:hover{ color:#7FB2DE; }

            /* Ajustes finos a los componentes Blade heredados de Breeze. */
            .mus-card input[type="text"],
            .mus-card input[type="email"],
            .mus-card input[type="password"]{
                border-radius:8px; border-color:#DFE5EC; background:#f6f8fb; padding:12px 14px;
            }
            .mus-card input:focus{ border-color:#4A8FC9; box-shadow:0 0 0 4px rgba(74,143,201,.13); background:#fff; }
            .mus-card button, .mus-card .inline-flex[type="submit"]{ border-radius:8px; }

            @media (prefers-reduced-motion:reduce){
                *,*::before,*::after{ animation-duration:.01ms !important; transition-duration:.01ms !important; }
            }
        </style>
    </head>
    <body class="mus-guest font-sans text-gray-900 antialiased">
        <div class="mus-orb a" aria-hidden="true"></div>
        <div class="mus-orb b" aria-hidden="true"></div>
        <div class="mus-orb c" aria-hidden="true"></div>
        <div class="mus-ring r1" aria-hidden="true"></div>
        <div class="mus-ring r2" aria-hidden="true"></div>
        <div class="mus-ring r3" aria-hidden="true"></div>
        <div class="mus-poly p1" aria-hidden="true">
            <svg viewBox="0 0 120 120" fill="none" stroke="rgba(127,178,222,.26)" stroke-width="1.1">
                <polygon points="60,6 112,36 112,84 60,114 8,84 8,36"/>
                <polygon points="60,26 94,46 94,76 60,96 26,76 26,46"/>
            </svg>
        </div>
        <div class="mus-poly p2" aria-hidden="true">
            <svg viewBox="0 0 120 120" fill="none" stroke="rgba(74,143,201,.28)" stroke-width="1.3">
                <rect x="18" y="18" width="84" height="84" rx="14"/>
                <rect x="38" y="38" width="44" height="44" rx="8"/>
            </svg>
        </div>

        <div class="mus-wrap min-h-screen flex flex-col justify-center items-center px-4 py-10">
            <a href="{{ url('/') }}" class="mus-brand mb-7">
                <svg viewBox="0 0 48 48" fill="none" stroke="#4A8FC9" stroke-width="2.2" stroke-linejoin="round">
                    <path d="M8 16 L24 7 L40 16 L24 25 Z"/>
                    <path d="M8 16 v16 L24 41 V25"/>
                    <path d="M40 16 v16 L24 41"/>
                </svg>
                <b>MEGA UNI STORE</b>
            </a>

            <div class="mus-card w-full sm:max-w-md">
                {{ $slot }}
            </div>

            <a href="{{ route('login') }}" class="mus-back">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Volver al inicio de sesión
            </a>
        </div>
    </body>
</html>
