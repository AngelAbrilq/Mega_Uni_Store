@php
    /**
     * Pantalla unificada de autenticación (login + registro).
     * $mode llega desde auth/login.blade.php o auth/register.blade.php.
     * old('form_type') gana, para que al fallar la validación vuelva al panel correcto.
     */
    $mode   = $mode ?? 'login';
    $active = old('form_type') ?: $mode;
    $active = in_array($active, ['login', 'register'], true) ? $active : 'login';
    $hasErrors = $errors->any();
    $brand = 'MEGA UNI STORE';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0E1723">
    <title>{{ $active === 'register' ? 'Crear cuenta' : 'Iniciar sesión' }} · {{ $brand }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ============================================================
           MEGA UNI STORE · Auth Portal
           Sin dependencias: todo el CSS y JS vive en este archivo,
           así funciona aunque no se haya corrido `npm run build`.
           ============================================================ */

        :root{
            --navy-900:#070D14;
            --navy-800:#0A1119;
            --navy-700:#0E1723;
            --navy-600:#142033;
            --blue-500:#4A8FC9;
            --blue-600:#2E6EA8;
            --blue-700:#215480;
            --sky:#4A8FC9;
            --sky-soft:#7FB2DE;
            --ink:#101825;
            --ink-soft:#33415A;
            --muted:#66768F;
            --line:#DFE5EC;
            --field:#F2F5F9;
            --white:#ffffff;
            --danger:#96504F;
            --success:#3E7D5C;

            --ease-soft:cubic-bezier(.22,1,.36,1);
            --ease-inout:cubic-bezier(.65,0,.35,1);
        }

        *,*::before,*::after{ box-sizing:border-box; margin:0; padding:0; }

        html,body{ height:100%; }

        body{
            font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
            background:var(--navy-800);
            color:var(--ink);
            min-height:100vh;
            display:grid;
            place-items:center;
            padding:28px 18px;
            overflow-x:hidden;
            -webkit-font-smoothing:antialiased;
        }

        /* ---------- Fondo animado ---------- */
        .bg{ position:fixed; inset:0; z-index:0; overflow:hidden;
             background:
                radial-gradient(1200px 700px at 12% 8%,  rgba(46,110,168,.28), transparent 60%),
                radial-gradient(900px 600px at 88% 92%,  rgba(74,143,201,.20), transparent 60%),
                linear-gradient(160deg, var(--navy-900) 0%, var(--navy-800) 45%, var(--navy-700) 100%);
        }
        .bg__grid{
            position:absolute; inset:-50%;
            background-image:
                linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
            background-size:64px 64px;
            transform:perspective(600px) rotateX(58deg) translateY(-8%);
            mask-image:radial-gradient(ellipse 60% 55% at 50% 42%, #000 20%, transparent 78%);
            -webkit-mask-image:radial-gradient(ellipse 60% 55% at 50% 42%, #000 20%, transparent 78%);
            animation:gridDrift 26s linear infinite;
        }
        @keyframes gridDrift{ to{ background-position:0 64px, 64px 0; } }

        /* --- Orbes: derivan, respiran y giran --- */
        .orb{ position:absolute; border-radius:50%; filter:blur(70px); opacity:.5; will-change:transform; }
        .orb i{ display:block; width:100%; height:100%; border-radius:50%; }
        .orb--1{ width:520px; height:520px; top:-140px; left:-120px; }
        .orb--1 i{ background:radial-gradient(circle at 30% 30%, #2E6EA8, transparent 68%);
                   animation:float1 19s var(--ease-inout) infinite; }
        .orb--2{ width:470px; height:470px; bottom:-160px; right:-100px; }
        .orb--2 i{ background:radial-gradient(circle at 60% 40%, #4A8FC9, transparent 68%);
                   animation:float2 23s var(--ease-inout) infinite; }
        .orb--3{ width:350px; height:350px; top:50%; left:54%; }
        .orb--3 i{ background:radial-gradient(circle at 50% 50%, #215480, transparent 70%);
                   animation:float3 27s var(--ease-inout) infinite; }
        .orb--4{ width:280px; height:280px; top:8%; right:16%; opacity:.4; }
        .orb--4 i{ background:radial-gradient(circle at 40% 60%, #3E82BE, transparent 70%);
                   animation:float4 31s var(--ease-inout) infinite; }
        @keyframes float1{ 50%{ transform:translate(95px,72px) scale(1.16) rotate(28deg); } }
        @keyframes float2{ 50%{ transform:translate(-85px,-62px) scale(1.11) rotate(-24deg); } }
        @keyframes float3{ 50%{ transform:translate(-64px,84px) scale(1.2) rotate(34deg); } }
        @keyframes float4{ 50%{ transform:translate(58px,-70px) scale(1.14) rotate(-30deg); } }

        /* --- Anillos concéntricos girando --- */
        .ring{ position:absolute; border-radius:50%; pointer-events:none; will-change:transform; }
        .ring i{ display:block; width:100%; height:100%; border-radius:50%;
                 border:1px solid rgba(127,178,222,.13); }
        .ring--1{ width:520px; height:520px; top:-130px; left:-140px; }
        .ring--1 i{ animation:spinSlow 44s linear infinite; }
        .ring--2{ width:740px; height:740px; top:-240px; left:-250px; }
        .ring--2 i{ border-style:dashed; border-color:rgba(127,178,222,.09);
                    animation:spinSlow 78s linear reverse infinite; }
        .ring--3{ width:420px; height:420px; bottom:-140px; right:-120px; }
        .ring--3 i{ border-color:rgba(74,143,201,.15); animation:spinSlow 56s linear infinite; }
        @keyframes spinSlow{ to{ transform:rotate(360deg); } }

        /* --- Polígonos flotantes --- */
        .poly{ position:absolute; pointer-events:none; will-change:transform; }
        .poly svg{ width:100%; height:100%; }
        .poly--1{ width:150px; height:150px; top:16%; right:11%; opacity:.55; }
        .poly--1 svg{ animation:polyA 26s var(--ease-inout) infinite; }
        .poly--2{ width:104px; height:104px; bottom:16%; left:8%; opacity:.45; }
        .poly--2 svg{ animation:polyB 34s var(--ease-inout) infinite; }
        .poly--3{ width:74px; height:74px; top:70%; right:22%; opacity:.4; }
        .poly--3 svg{ animation:polyA 21s var(--ease-inout) reverse infinite; }
        @keyframes polyA{
            0%,100%{ transform:rotate(0) translateY(0); }
            50%{ transform:rotate(180deg) translateY(-26px); }
        }
        @keyframes polyB{
            0%,100%{ transform:rotate(0) translateY(0) scale(1); }
            50%{ transform:rotate(-160deg) translateY(22px) scale(1.15); }
        }

        /* --- Haz de luz que barre lentamente --- */
        .beam{
            position:absolute; top:-40%; left:-30%; width:60%; height:180%;
            background:linear-gradient(100deg, transparent, rgba(127,178,222,.07) 45%, transparent);
            transform:rotate(14deg); pointer-events:none;
            animation:beamSweep 17s var(--ease-inout) infinite;
        }
        @keyframes beamSweep{
            0%,100%{ transform:translateX(-20%) rotate(14deg); opacity:0; }
            35%{ opacity:1; }
            65%{ opacity:1; }
            50%{ transform:translateX(140%) rotate(14deg); }
        }

        /* --- Cruces de retícula: marcas de plano técnico --- */
        .cross{
            position:absolute; width:13px; height:13px; pointer-events:none;
            opacity:.32; will-change:transform;
        }
        .cross::before,.cross::after{
            content:''; position:absolute; background:rgba(127,178,222,.75);
        }
        .cross::before{ left:50%; top:0; bottom:0; width:1px; margin-left:-.5px; }
        .cross::after{ top:50%; left:0; right:0; height:1px; margin-top:-.5px; }
        .cross.x1{ left:18%; top:14%; animation:crossPulse 6s var(--ease-inout) infinite; }
        .cross.x2{ left:76%; top:26%; animation:crossPulse 7.5s var(--ease-inout) .8s infinite; }
        .cross.x3{ left:30%; top:78%; animation:crossPulse 8.5s var(--ease-inout) 1.6s infinite; }
        .cross.x4{ left:88%; top:66%; animation:crossPulse 6.8s var(--ease-inout) 2.4s infinite; }
        .cross.x5{ left:8%;  top:52%; animation:crossPulse 9s   var(--ease-inout) 3.2s infinite; }
        @keyframes crossPulse{ 50%{ opacity:.75; transform:scale(1.5) rotate(45deg); } }

        /* --- Arco punteado que gira muy lento --- */
        .arc{
            position:absolute; border-radius:50%; pointer-events:none;
            border:1px dashed rgba(74,143,201,.16);
            border-right-color:transparent; border-bottom-color:transparent;
        }
        .arc.g1{ width:900px; height:900px; left:-260px; bottom:-380px;
                 animation:spinSlow 96s linear infinite; }
        .arc.g2{ width:600px; height:600px; right:-200px; top:-140px;
                 border-color:rgba(127,178,222,.13); border-left-color:transparent;
                 animation:spinSlow 68s linear reverse infinite; }

        /* --- Línea de barrido horizontal, muy lenta --- */
        .scanline{
            position:absolute; left:0; right:0; height:1px;
            background:linear-gradient(90deg,transparent,rgba(127,178,222,.28),transparent);
            animation:scanDown 13s linear infinite;
        }
        @keyframes scanDown{
            0%{ top:-2%; opacity:0; }
            8%{ opacity:1; }
            92%{ opacity:1; }
            100%{ top:102%; opacity:0; }
        }

        /* --- Grano: rompe la planitud digital del degradado --- */
        body::after{
            content:''; position:fixed; inset:0; z-index:70;
            pointer-events:none; opacity:.055; mix-blend-mode:overlay;
            background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='140' height='140'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3'/></filter><rect width='140' height='140' filter='url(%23n)' opacity='.6'/></svg>");
        }

        .spark{ position:absolute; width:3px; height:3px; border-radius:50%;
                background:rgba(183,202,221,.85); box-shadow:0 0 10px rgba(127,178,222,.9);
                animation:rise linear infinite; opacity:0; }
        @keyframes rise{
            0%{ transform:translateY(0) scale(.6); opacity:0; }
            12%{ opacity:.9; }
            88%{ opacity:.5; }
            100%{ transform:translateY(-105vh) scale(1.1); opacity:0; }
        }

        /* ---------- Intro / preloader ---------- */
        #intro{
            position:fixed; inset:0; z-index:90;
            display:grid; place-items:center;
            background:linear-gradient(160deg, var(--navy-900), var(--navy-700));
        }
        #intro.is-gone{ pointer-events:none; }
        /* background-size al 200% + posiciones opuestas = el degradado se ve
           continuo entre las dos hojas y no aparece una costura en el centro. */
        #intro .curtain{ position:absolute; left:0; width:100%; height:50.5%;
                         background:linear-gradient(160deg, var(--navy-900), var(--navy-700));
                         background-size:100% 198%;
                         transition:transform 1s var(--ease-soft); }
        #intro .curtain--top{ top:0; background-position:0 0; }
        #intro .curtain--bot{ bottom:0; background-position:0 100%; }
        #intro.is-gone .curtain--top{ transform:translateY(-100%); }
        #intro.is-gone .curtain--bot{ transform:translateY(100%); }

        .intro__inner{ position:relative; z-index:2; text-align:center; transition:opacity .45s ease, transform .45s ease; }
        #intro.is-gone .intro__inner{ opacity:0; transform:scale(1.06); }

        /* Anillos que laten detrás del logotipo */
        .intro__halo{ position:absolute; left:50%; top:50%; border-radius:50%;
                      border:1px solid rgba(127,178,222,.16);
                      transform:translate(-50%,-50%); pointer-events:none; }
        .intro__halo.h1{ width:230px; height:230px; animation:haloPulse 3.4s var(--ease-soft) infinite; }
        .intro__halo.h2{ width:380px; height:380px; animation:haloPulse 3.4s var(--ease-soft) .55s infinite; }
        .intro__halo.h3{ width:540px; height:540px; animation:haloPulse 3.4s var(--ease-soft) 1.1s infinite; }
        @keyframes haloPulse{
            0%{ transform:translate(-50%,-50%) scale(.82); opacity:0; }
            30%{ opacity:.8; }
            100%{ transform:translate(-50%,-50%) scale(1.2); opacity:0; }
        }

        .intro__mark{ width:94px; height:94px; margin:0 auto 26px; display:block; }
        .intro__mark path,.intro__mark circle,.intro__mark rect{
            fill:none; stroke:var(--sky); stroke-width:2.2;
            stroke-linecap:round; stroke-linejoin:round;
            stroke-dasharray:240; stroke-dashoffset:240;
            animation:draw 1.45s var(--ease-soft) .2s forwards;
        }
        .intro__mark .d2{ animation-delay:.5s; }
        .intro__mark .d3{ animation-delay:.8s; }
        @keyframes draw{ to{ stroke-dashoffset:0; } }

        .intro__word{ display:flex; gap:.07em; justify-content:center; flex-wrap:wrap;
                      font-size:clamp(20px,4.4vw,34px); font-weight:700; letter-spacing:.14em; color:#fff; }
        .intro__word span{ display:inline-block; transform:translateY(120%) rotate(6deg); opacity:0;
                           animation:letterUp .85s var(--ease-soft) forwards; }
        .intro__word .sp{ width:.45em; }
        @keyframes letterUp{ to{ transform:translateY(0) rotate(0); opacity:1; } }

        .intro__sub{ margin-top:14px; color:rgba(183,202,221,.62); font-size:12.5px;
                     letter-spacing:.34em; text-transform:uppercase;
                     opacity:0; animation:fadeIn .75s ease 1.55s forwards; }

        .intro__bar{ width:210px; height:2px; margin:30px auto 0; border-radius:2px;
                     background:rgba(255,255,255,.14); overflow:hidden;
                     opacity:0; animation:fadeIn .5s ease 1.7s forwards; }
        .intro__bar i{ display:block; height:100%; width:0;
                       background:linear-gradient(90deg,var(--blue-600),var(--sky));
                       animation:load 2.05s var(--ease-inout) 1.8s forwards; }
        @keyframes load{ to{ width:100%; } }
        @keyframes fadeIn{ to{ opacity:1; } }

        .intro__status{ margin-top:14px; height:15px;
                        color:rgba(148,162,182,.5); font-size:10.5px;
                        letter-spacing:.28em; text-transform:uppercase;
                        opacity:0; animation:fadeIn .5s ease 1.85s forwards;
                        transition:opacity .25s ease; }

        .intro__skip{ position:absolute; bottom:30px; left:0; right:0; text-align:center;
                      color:rgba(183,202,221,.34); font-size:11.5px; letter-spacing:.18em;
                      text-transform:uppercase; opacity:0; animation:fadeIn .6s ease 2.5s forwards; }

        /* ---------- Tarjeta ---------- */
        .shell{ position:relative; z-index:5; width:100%; max-width:1020px; }

        .card{
            position:relative; width:100%; min-height:610px;
            border-radius:16px; overflow:hidden;
            background:var(--white);
            box-shadow:0 40px 90px -30px rgba(5,10,18,.75), 0 0 0 1px rgba(255,255,255,.07);
            opacity:0; transform:translateY(26px) scale(.965);
            transition:transform .55s var(--ease-soft), opacity .55s ease;
        }
        body.is-ready .card{ opacity:1; transform:none; }
        .card.is-shaking{ animation:shake .5s var(--ease-inout); }
        @keyframes shake{
            10%,90%{ transform:translateX(-5px); }
            20%,80%{ transform:translateX(8px); }
            30%,50%,70%{ transform:translateX(-11px); }
            40%,60%{ transform:translateX(11px); }
        }

        /* Panel deslizante */
        .panel{
            position:absolute; top:0; left:0; width:50%; height:100%; z-index:3;
            display:flex; flex-direction:column; justify-content:space-between;
            padding:46px 44px;
            color:#fff; overflow:hidden;
            background:linear-gradient(158deg,#16385A 0%,#1B4870 38%,#215480 70%,#2B6694 100%);
            transition:transform 1.05s var(--ease-inout);
        }
        .card.is-register .panel{ transform:translateX(100%); }

        /* Retícula tenue: textura técnica en vez de degradado liso */
        .panel__mesh{
            position:absolute; inset:0; pointer-events:none; opacity:.55; z-index:1;
            background-image:
                linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
            background-size:38px 38px;
            mask-image:radial-gradient(ellipse 85% 75% at 32% 42%, #000 5%, transparent 74%);
            -webkit-mask-image:radial-gradient(ellipse 85% 75% at 32% 42%, #000 5%, transparent 74%);
            transition:transform 1.05s var(--ease-inout);
        }
        .card.is-register .panel__mesh{ transform:translateX(-44px); }

        .panel__deco{ position:absolute; inset:0; overflow:hidden; pointer-events:none; z-index:1; }

        /* ---------- Los círculos viajan de verdad al cambiar de formulario ---------- */
        .panel__circle{
            position:absolute; border-radius:50%;
            transition:transform 1.3s var(--ease-back), opacity .9s ease;
        }
        .panel__circle.c1{
            width:540px; height:540px; right:-235px; top:-245px;
            background:radial-gradient(circle at 34% 34%, rgba(255,255,255,.14), rgba(255,255,255,.03) 60%, transparent 72%);
            border:1px solid rgba(255,255,255,.13);
        }
        .panel__circle.c2{
            width:330px; height:330px; left:-155px; bottom:-165px;
            background:radial-gradient(circle at 60% 40%, rgba(255,255,255,.11), transparent 66%);
            border:1px solid rgba(255,255,255,.1);
            transition-delay:.07s;
        }
        .panel__circle.c3{
            width:190px; height:190px; left:56%; top:60%;
            border:1px dashed rgba(255,255,255,.2);
            transition-delay:.14s;
        }
        .panel__circle.c4{
            width:92px; height:92px; left:11%; top:20%;
            border:1px solid rgba(255,255,255,.15);
            transition-delay:.2s;
        }

        /* Intercambian esquinas girando: el grande baja, el mediano sube. */
        .card.is-register .panel__circle.c1{ transform:translate(-238px,452px) rotate(128deg) scale(.7); }
        .card.is-register .panel__circle.c2{ transform:translate(298px,-402px) rotate(-146deg) scale(1.5); }
        .card.is-register .panel__circle.c3{ transform:translate(-262px,-330px) rotate(210deg) scale(1.38); }
        .card.is-register .panel__circle.c4{ transform:translate(210px,312px) rotate(-180deg) scale(1.75); }

        /* Destello diagonal que barre el panel justo al cambiar */
        .panel__sheen{
            position:absolute; top:-30%; bottom:-30%; left:0; width:36%; z-index:2;
            background:linear-gradient(100deg,transparent,rgba(255,255,255,.16),transparent);
            transform:translateX(-180%) rotate(12deg); opacity:0;
        }
        .card.is-swapping .panel__sheen{ animation:sheen 1.15s var(--ease-inout) forwards; }
        @keyframes sheen{
            0%{ transform:translateX(-180%) rotate(12deg); opacity:0; }
            22%{ opacity:1; }
            100%{ transform:translateX(340%) rotate(12deg); opacity:0; }
        }

        /* Onda que nace del punto donde se pulsó */
        .ripple{
            position:absolute; width:16px; height:16px; border-radius:50%;
            border:1.4px solid rgba(255,255,255,.55);
            transform:translate(-50%,-50%) scale(0); opacity:.95;
            pointer-events:none;
            animation:rip 1.05s var(--ease-soft) forwards;
        }
        .ripple.b{ animation-delay:.14s; border-color:rgba(255,255,255,.28); }
        @keyframes rip{ to{ transform:translate(-50%,-50%) scale(46); opacity:0; } }

        .panel__brand{ position:relative; z-index:2; display:flex; align-items:center; gap:11px; }
        .panel__brand svg{ width:34px; height:34px; flex:none; }
        .panel__brand b{ font-size:14.5px; font-weight:700; letter-spacing:.15em; }

        .panel__slides{ position:relative; z-index:2; flex:1; display:flex; align-items:center; }
        .panel__slide{
            position:absolute; inset:0; display:flex; flex-direction:column; justify-content:center;
            transition:opacity .5s ease, transform .6s var(--ease-soft);
        }
        .panel__slide[data-for="login"]{ opacity:1; transform:none; }
        .panel__slide[data-for="register"]{ opacity:0; transform:translateX(-34px); pointer-events:none; }
        .card.is-register .panel__slide[data-for="login"]{ opacity:0; transform:translateX(34px); pointer-events:none; }
        .card.is-register .panel__slide[data-for="register"]{ opacity:1; transform:none; pointer-events:auto; }

        .panel__slide h2{ font-size:29px; font-weight:700; line-height:1.2; letter-spacing:-.015em; }
        .panel__slide p{ margin-top:13px; font-size:14.5px; line-height:1.65; color:rgba(255,255,255,.86); max-width:330px; }

        .panel__list{ list-style:none; margin-top:26px; display:grid; gap:12px; }
        .panel__list li{ display:flex; align-items:center; gap:10px; font-size:13.5px; color:rgba(255,255,255,.9); }
        .panel__list svg{ width:17px; height:17px; flex:none; opacity:.95; }

        .panel__cta{
            margin-top:30px; align-self:flex-start;
            padding:12px 30px; border-radius:999px; cursor:pointer;
            border:1.6px solid rgba(255,255,255,.75); background:transparent;
            color:#fff; font-family:inherit; font-size:12.5px; font-weight:600;
            letter-spacing:.11em; text-transform:uppercase;
            transition:background .3s ease, color .3s ease, transform .3s var(--ease-soft);
        }
        .panel__cta:hover{ background:#fff; color:var(--blue-700); transform:translateY(-2px); }

        .panel__foot{ position:relative; z-index:2; font-size:11.5px; color:rgba(255,255,255,.62); letter-spacing:.04em; }

        /* Zona de formularios */
        .forms{
            position:absolute; top:0; right:0; width:50%; height:100%; z-index:2;
            display:grid; transition:transform .95s var(--ease-inout);
        }
        .card.is-register .forms{ transform:translateX(-100%); }

        .form{
            grid-area:1/1; overflow-y:auto;
            display:flex; flex-direction:column; justify-content:center;
            padding:46px 52px;
            transition:opacity .45s ease, transform .55s var(--ease-soft);
        }
        .form[data-form="register"]{ opacity:0; transform:translateX(26px); pointer-events:none; }
        .card.is-register .form[data-form="login"]{ opacity:0; transform:translateX(-26px); pointer-events:none; }
        .card.is-register .form[data-form="register"]{ opacity:1; transform:none; pointer-events:auto; }

        .form__eyebrow{ font-size:11px; font-weight:600; letter-spacing:.24em; text-transform:uppercase; color:var(--blue-600); }
        .form__title{ margin-top:9px; font-size:27px; font-weight:700; letter-spacing:-.02em; color:var(--ink); }
        .form__lead{ margin-top:7px; font-size:13.5px; color:var(--muted); }

        .fields{ margin-top:26px; display:grid; gap:15px; }
        .stagger > *{ opacity:0; transform:translateY(14px); }
        body.is-ready .form:not([data-hidden]) .stagger > *{
            animation:fieldIn .55s var(--ease-soft) forwards;
        }
        .stagger > *:nth-child(1){ animation-delay:.06s; }
        .stagger > *:nth-child(2){ animation-delay:.12s; }
        .stagger > *:nth-child(3){ animation-delay:.18s; }
        .stagger > *:nth-child(4){ animation-delay:.24s; }
        .stagger > *:nth-child(5){ animation-delay:.30s; }
        .stagger > *:nth-child(6){ animation-delay:.36s; }
        @keyframes fieldIn{ to{ opacity:1; transform:none; } }

        .field{ position:relative; }
        .field input{
            width:100%; height:54px;
            padding:20px 46px 8px 44px;
            font-family:inherit; font-size:14.5px; color:var(--ink);
            background:var(--field); border:1.5px solid transparent; border-radius:9px;
            outline:none; transition:border-color .25s ease, background .25s ease, box-shadow .25s ease;
        }
        .field input:focus{ background:#fff; border-color:var(--blue-500); box-shadow:0 0 0 4px rgba(74,143,201,.13); }
        .field label{
            position:absolute; left:44px; top:17px;
            font-size:14px; color:var(--muted); pointer-events:none;
            transition:transform .22s var(--ease-soft), font-size .22s var(--ease-soft), color .22s ease;
            transform-origin:left top;
        }
        .field input:focus + label,
        .field input:not(:placeholder-shown) + label{
            transform:translateY(-10px) scale(.76); color:var(--blue-600);
        }
        .field .ico{ position:absolute; left:15px; top:18px; width:18px; height:18px; color:#94A2B6; transition:color .25s ease; }
        .field input:focus ~ .ico{ color:var(--blue-600); }

        .field.has-error input{ border-color:var(--danger); background:#FBF3F3; }
        .field.has-error .ico{ color:var(--danger); }

        .toggle{
            position:absolute; right:12px; top:15px; width:26px; height:26px;
            display:grid; place-items:center; cursor:pointer;
            background:none; border:none; color:#94A2B6; padding:0;
            transition:color .2s ease;
        }
        .toggle:hover{ color:var(--blue-600); }
        .toggle svg{ width:17px; height:17px; }
        .toggle .off{ display:none; }
        .toggle.is-on .on{ display:none; }
        .toggle.is-on .off{ display:block; }

        .err{
            display:flex; align-items:flex-start; gap:6px;
            margin-top:6px; font-size:12.2px; color:var(--danger);
            animation:errIn .35s var(--ease-soft);
        }
        .err svg{ width:13px; height:13px; flex:none; margin-top:1.5px; }
        @keyframes errIn{ from{ opacity:0; transform:translateY(-4px); } }

        .caps{ margin-top:6px; font-size:11.8px; color:#96703C; display:none; align-items:center; gap:5px; }
        .caps.show{ display:flex; }

        /* Medidor de contraseña */
        .meter{ margin-top:9px; }
        .meter__track{ display:flex; gap:5px; }
        .meter__track i{ flex:1; height:4px; border-radius:3px; background:var(--line); transition:background .3s ease; }
        .meter__text{ margin-top:6px; font-size:11.5px; color:var(--muted); }
        .meter[data-level="1"] .meter__track i:nth-child(-n+1){ background:#96504F; }
        .meter[data-level="2"] .meter__track i:nth-child(-n+2){ background:#96703C; }
        .meter[data-level="3"] .meter__track i:nth-child(-n+3){ background:#4A8FC9; }
        .meter[data-level="4"] .meter__track i{ background:var(--success); }

        .row{ display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:2px; }
        .check{ display:inline-flex; align-items:center; gap:8px; cursor:pointer; font-size:13px; color:var(--ink-soft); user-select:none; }
        .check input{ position:absolute; opacity:0; width:0; height:0; }
        .check i{ width:17px; height:17px; border-radius:5px; border:1.6px solid #C3CCD8; background:#fff;
                  display:grid; place-items:center; transition:all .2s var(--ease-soft); }
        .check i::after{ content:''; width:8px; height:4.5px; border-left:2px solid #fff; border-bottom:2px solid #fff;
                         transform:rotate(-45deg) scale(0); transition:transform .2s var(--ease-soft); }
        .check input:checked + i{ background:var(--blue-600); border-color:var(--blue-600); }
        .check input:checked + i::after{ transform:rotate(-45deg) scale(1); }
        .link{ font-size:13px; color:var(--blue-600); text-decoration:none; font-weight:500; transition:color .2s ease; }
        .link:hover{ color:var(--blue-700); text-decoration:underline; }

        .submit{
            position:relative; width:100%; height:52px; margin-top:22px;
            border:none; border-radius:9px; cursor:pointer; overflow:hidden;
            background:linear-gradient(120deg, var(--blue-700), var(--blue-600) 55%, #3E82BE);
            background-size:200% 100%;
            color:#fff; font-family:inherit; font-size:14.5px; font-weight:600; letter-spacing:.02em;
            box-shadow:0 12px 26px -10px rgba(46,110,168,.75);
            transition:background-position .55s var(--ease-soft), transform .22s var(--ease-soft), box-shadow .25s ease;
        }
        .submit:hover{ background-position:100% 0; transform:translateY(-2px); box-shadow:0 18px 32px -12px rgba(46,110,168,.85); }
        .submit:active{ transform:translateY(0); }
        .submit span{ position:relative; z-index:2; display:inline-flex; align-items:center; gap:9px; }
        .submit svg{ width:16px; height:16px; transition:transform .3s var(--ease-soft); }
        .submit:hover svg{ transform:translateX(4px); }

        .status{
            margin-bottom:18px; padding:11px 14px; border-radius:8px;
            background:#EDF5F0; border:1px solid #B9D5C6; color:#31684D; font-size:13px;
        }

        .swap{ margin-top:22px; text-align:center; font-size:13.2px; color:var(--muted); display:none; }
        .swap--probar{ margin-top:6px; }
        .swap--probar a{ color:inherit; text-decoration:underline; text-underline-offset:3px; }
        .swap button{ background:none; border:none; padding:0; cursor:pointer;
                      color:var(--blue-600); font-family:inherit; font-size:13.2px; font-weight:600; }
        .swap button:hover{ text-decoration:underline; }

        .back{ position:absolute; top:18px; right:22px; z-index:6;
               display:inline-flex; align-items:center; gap:7px;
               padding:7px 12px; border-radius:999px;
               font-size:12.5px; color:var(--muted); text-decoration:none;
               transition:color .25s ease, background .28s ease, transform .3s var(--ease-back); }
        .back:hover{ color:var(--blue-600); background:rgba(46,110,168,.07); }
        .back:active{ transform:scale(.95); }
        .back svg{ width:14px; height:14px; transition:transform .38s var(--ease-back); }
        .back:hover svg{ transform:translateX(-5px); }
        .back .u{ position:relative; }
        .back .u::after{
            content:''; position:absolute; left:0; right:0; bottom:-2px; height:1.4px;
            background:currentColor; border-radius:2px;
            transform:scaleX(0); transform-origin:right;
            transition:transform .45s var(--ease-soft);
        }
        .back:hover .u::after{ transform:scaleX(1); transform-origin:left; }
        /* En modo registro el panel azul ocupa la derecha: el enlace se aclara. */
        .card.is-register .back{ color:rgba(255,255,255,.75); }
        .card.is-register .back:hover{ color:#fff; background:rgba(255,255,255,.14); }

        /* ---------- Barrido al volver al inicio ---------- */
        #wipe{ position:fixed; inset:0; z-index:95; display:flex; pointer-events:none; }
        #wipe span{
            flex:1; height:100%;
            background:linear-gradient(160deg,var(--navy-900),var(--navy-700));
            transform:translateY(101%);
            transition:transform .62s var(--ease-inout);
        }
        #wipe span:nth-child(2){ transition-delay:.08s; }
        #wipe span:nth-child(3){ transition-delay:.16s; }
        #wipe span:nth-child(4){ transition-delay:.24s; }
        #wipe .mark{
            position:absolute; inset:0; display:grid; place-items:center;
            opacity:0; transform:scale(.7) rotate(-12deg);
            transition:opacity .34s ease .34s, transform .55s var(--ease-back) .34s;
        }
        #wipe .mark svg{ width:76px; height:76px; }
        #wipe.on{ pointer-events:all; }
        #wipe.on span{ transform:translateY(0); }
        #wipe.on .mark{ opacity:1; transform:none; }

        /* ---------- Overlay de verificación ---------- */
        #gate{
            position:fixed; inset:0; z-index:80;
            display:grid; place-items:center;
            background:rgba(10,17,25,.82);
            backdrop-filter:blur(9px); -webkit-backdrop-filter:blur(9px);
            opacity:0; pointer-events:none; transition:opacity .4s ease;
        }
        #gate.show{ opacity:1; pointer-events:auto; }
        #gate .box{ text-align:center; transform:translateY(14px) scale(.95); transition:transform .5s var(--ease-soft); }
        #gate.show .box{ transform:none; }
        .ring{ width:64px; height:64px; margin:0 auto 20px; position:relative; }
        .ring i{ position:absolute; inset:0; border-radius:50%; border:2.5px solid transparent; }
        .ring i:nth-child(1){ border-top-color:var(--sky); animation:spin 1s linear infinite; }
        .ring i:nth-child(2){ inset:9px; border-bottom-color:var(--blue-500); animation:spin 1.4s linear reverse infinite; }
        .ring i:nth-child(3){ inset:18px; border-left-color:#fff; animation:spin .8s linear infinite; }
        @keyframes spin{ to{ transform:rotate(360deg); } }
        #gate h3{ color:#fff; font-size:16.5px; font-weight:600; letter-spacing:.01em; }
        #gate p{ margin-top:7px; color:rgba(183,202,221,.62); font-size:12.5px; letter-spacing:.14em; text-transform:uppercase; }

        /* ---------- Responsive ---------- */
        @media (max-width:900px){
            body{ padding:0; display:block; }
            .shell{ max-width:none; min-height:100vh; }
            .card{ min-height:100vh; border-radius:0; display:flex; flex-direction:column; }
            .panel{ position:relative; width:100%; height:auto; padding:30px 26px 26px; transform:none !important; }
            .card.is-register .panel{ transform:none; }
            .panel__slides{ min-height:150px; }
            .panel__list{ display:none; }
            .panel__cta{ display:none; }
            .panel__foot{ display:none; }
            .forms{ position:relative; width:100%; height:auto; flex:1; transform:none !important; }
            .form{ padding:28px 24px 42px; justify-content:flex-start; }
            .form[data-form="register"]{ display:none; }
            .card.is-register .form[data-form="login"]{ display:none; }
            .card.is-register .form[data-form="register"]{ display:flex; opacity:1; transform:none; }
            .swap{ display:block; }
            /* Sobre el panel azul, que en móvil ocupa la franja superior. */
            .back{ top:16px; right:18px; color:rgba(255,255,255,.82) !important; }
        }
        @media (max-width:420px){
            .form{ padding:26px 18px 36px; }
            .form__title{ font-size:24px; }
        }

        @media (prefers-reduced-motion:reduce){
            *,*::before,*::after{ animation-duration:.01ms !important; animation-iteration-count:1 !important;
                                  transition-duration:.01ms !important; }
        }
    </style>
</head>

<body data-active="{{ $active }}" data-has-errors="{{ $hasErrors ? '1' : '0' }}">

    {{-- ══════════ Fondo ══════════ --}}
    <div class="bg" aria-hidden="true">
        <div class="bg__grid"></div>

        <div class="orb orb--1" data-float="16"><i></i></div>
        <div class="orb orb--2" data-float="-22"><i></i></div>
        <div class="orb orb--3" data-float="13"><i></i></div>
        <div class="orb orb--4" data-float="-18"><i></i></div>

        <div class="ring ring--1" data-float="-9"><i></i></div>
        <div class="ring ring--2" data-float="6"><i></i></div>
        <div class="ring ring--3" data-float="-13"><i></i></div>

        <div class="poly poly--1" data-float="30">
            <svg viewBox="0 0 120 120" fill="none" stroke="rgba(127,178,222,.28)" stroke-width="1.1">
                <polygon points="60,6 112,36 112,84 60,114 8,84 8,36"/>
                <polygon points="60,26 94,46 94,76 60,96 26,76 26,46"/>
            </svg>
        </div>
        <div class="poly poly--2" data-float="-24">
            <svg viewBox="0 0 120 120" fill="none" stroke="rgba(74,143,201,.3)" stroke-width="1.3">
                <rect x="18" y="18" width="84" height="84" rx="14"/>
                <rect x="38" y="38" width="44" height="44" rx="8"/>
            </svg>
        </div>
        <div class="poly poly--3" data-float="20">
            <svg viewBox="0 0 120 120" fill="none" stroke="rgba(127,178,222,.26)" stroke-width="1.6">
                <polygon points="60,14 108,100 12,100"/>
            </svg>
        </div>

        <div class="arc g1" data-float="-7"></div>
        <div class="arc g2" data-float="9"></div>

        <div class="cross x1" data-float="22"></div>
        <div class="cross x2" data-float="-17"></div>
        <div class="cross x3" data-float="14"></div>
        <div class="cross x4" data-float="-25"></div>
        <div class="cross x5" data-float="19"></div>

        <div class="scanline"></div>
        <div class="beam"></div>
        <div id="sparks"></div>
    </div>

    {{-- ══════════ Intro (animación ANTES de llegar al login) ══════════ --}}
    <div id="intro" aria-hidden="true">
        <div class="curtain curtain--top"></div>
        <div class="curtain curtain--bot"></div>
        <span class="intro__halo h1"></span>
        <span class="intro__halo h2"></span>
        <span class="intro__halo h3"></span>

        <div class="intro__inner">
            <svg class="intro__mark" viewBox="0 0 48 48">
                <path d="M8 16 L24 7 L40 16 L24 25 Z"/>
                <path class="d2" d="M8 16 v16 L24 41 V25"/>
                <path class="d3" d="M40 16 v16 L24 41"/>
            </svg>
            <div class="intro__word" id="introWord"></div>
            <div class="intro__sub">Sistema de gestión comercial</div>
            <div class="intro__bar"><i></i></div>
            <div class="intro__status" id="introStatus">Iniciando entorno</div>
        </div>
        <div class="intro__skip">Clic para continuar</div>
    </div>

    {{-- ══════════ Tarjeta ══════════ --}}
    <div class="shell">
        <div class="card {{ $active === 'register' ? 'is-register' : '' }}" id="card">

            <a href="{{ url('/') }}" class="back" id="backHome">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                <span class="u">Volver al inicio</span>
            </a>

            {{-- ---------- Panel azul ---------- --}}
            <aside class="panel">
                <span class="panel__mesh" aria-hidden="true"></span>
                <div class="panel__deco" id="panelDeco" aria-hidden="true">
                    <span class="panel__circle c1"></span>
                    <span class="panel__circle c2"></span>
                    <span class="panel__circle c3"></span>
                    <span class="panel__circle c4"></span>
                </div>
                <span class="panel__sheen" aria-hidden="true"></span>

                <div class="panel__brand">
                    <svg viewBox="0 0 48 48" fill="none" stroke="#fff" stroke-width="2.2" stroke-linejoin="round">
                        <path d="M8 16 L24 7 L40 16 L24 25 Z"/>
                        <path d="M8 16 v16 L24 41 V25"/>
                        <path d="M40 16 v16 L24 41"/>
                    </svg>
                    <b>{{ $brand }}</b>
                </div>

                <div class="panel__slides">
                    {{-- Se ve cuando el formulario activo es LOGIN --}}
                    <div class="panel__slide" data-for="login">
                        <h2>¿Aún no tienes cuenta?</h2>
                        <p>Crea tu usuario en menos de un minuto y empieza a administrar productos, clientes e inventario desde un solo lugar.</p>
                        <ul class="panel__list">
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                Catálogo de productos y categorías
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                Clientes, proveedores e impuestos
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                Roles y permisos por usuario
                            </li>
                        </ul>
                        <button type="button" class="panel__cta" data-go="register">Crear cuenta</button>
                    </div>

                    {{-- Se ve cuando el formulario activo es REGISTRO --}}
                    <div class="panel__slide" data-for="register">
                        <h2>¿Ya eres parte del equipo?</h2>
                        <p>Inicia sesión con tu correo institucional y retoma tu trabajo justo donde lo dejaste.</p>
                        <ul class="panel__list">
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                Sesión segura y cifrada
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                Recuperación de contraseña
                            </li>
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                Acceso según tu rol
                            </li>
                        </ul>
                        <button type="button" class="panel__cta" data-go="login">Iniciar sesión</button>
                    </div>
                </div>

                <div class="panel__foot">© {{ date('Y') }} {{ $brand }} · Todos los derechos reservados</div>
            </aside>

            {{-- ---------- Formularios ---------- --}}
            <div class="forms">

                {{-- ============ LOGIN ============ --}}
                <form class="form" data-form="login" method="POST" action="{{ route('login') }}" novalidate>
                    @csrf
                    <input type="hidden" name="form_type" value="login">

                    @if (session('status'))
                        <div class="status">{{ session('status') }}</div>
                    @endif

                    <div class="form__eyebrow">Bienvenido de nuevo</div>
                    <h1 class="form__title">Iniciar sesión</h1>
                    <p class="form__lead">Ingresa tus credenciales para entrar al panel.</p>

                    <div class="fields stagger">
                        <div>
                            <div class="field {{ $active === 'login' && $errors->has('email') ? 'has-error' : '' }}">
                                <input type="email" id="l_email" name="email" placeholder=" " autocomplete="username"
                                       value="{{ $active === 'login' ? old('email') : '' }}" required>
                                <label for="l_email">Correo electrónico</label>
                                <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="4" width="20" height="16" rx="3"/><path d="m2 7 10 6 10-6"/>
                                </svg>
                            </div>
                            @if ($active === 'login')
                                @error('email')
                                    <div class="err">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/></svg>
                                        <span>{{ $message }}</span>
                                    </div>
                                @enderror
                            @endif
                        </div>

                        <div>
                            <div class="field {{ $active === 'login' && $errors->has('password') ? 'has-error' : '' }}">
                                <input type="password" id="l_password" name="password" placeholder=" " autocomplete="current-password" required>
                                <label for="l_password">Contraseña</label>
                                <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                </svg>
                                <button type="button" class="toggle" data-toggle="l_password" aria-label="Mostrar contraseña">
                                    <svg class="on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2M6.6 6.7C3.9 8.3 2 12 2 12s3.6 7 10 7c1.9 0 3.5-.6 4.9-1.4M14 5.4A9.6 9.6 0 0 0 12 5c-.7 0-1.3.1-1.9.2M21.9 12.4C21.2 11 18.6 7.2 15 5.8"/></svg>
                                </button>
                            </div>
                            <div class="caps" data-caps-for="l_password">
                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m12 4 8 8h-4v5H8v-5H4z"/></svg>
                                Bloq Mayús está activado
                            </div>
                            @if ($active === 'login')
                                @error('password')
                                    <div class="err">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/></svg>
                                        <span>{{ $message }}</span>
                                    </div>
                                @enderror
                            @endif
                        </div>

                        <div class="row">
                            <label class="check">
                                <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                                <i></i> Recordarme
                            </label>
                            @if (Route::has('password.request'))
                                <a class="link" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a>
                            @endif
                        </div>

                        <button type="submit" class="submit">
                            <span>
                                Entrar al panel
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </span>
                        </button>
                    </div>

                    <div class="swap">¿No tienes cuenta? <button type="button" data-go="register">Regístrate</button></div>

                    {{-- Entrada a la prueba de 2 h 30. Va aquí, debajo del
                         ingreso, porque el que llega a esta pantalla sin
                         cuenta es exactamente a quien va dirigida. --}}
                    @if (Route::has('demo.crear'))
                        <div class="swap swap--probar">
                            ¿Todavía no lo conoces?
                            <a href="{{ route('demo.crear') }}">Pruébalo 2 h 30 con tu negocio</a>
                        </div>
                    @endif
                </form>

                {{-- ============ REGISTRO ============ --}}
                <form class="form" data-form="register" method="POST" action="{{ route('register') }}" novalidate>
                    @csrf
                    <input type="hidden" name="form_type" value="register">

                    <div class="form__eyebrow">Primera vez aquí</div>
                    <h1 class="form__title">Crear cuenta</h1>
                    <p class="form__lead">Solo necesitas tu nombre y un correo válido.</p>

                    <div class="fields stagger">
                        <div>
                            <div class="field {{ $active === 'register' && $errors->has('name') ? 'has-error' : '' }}">
                                <input type="text" id="r_name" name="name" placeholder=" " autocomplete="name"
                                       value="{{ $active === 'register' ? old('name') : '' }}" required>
                                <label for="r_name">Nombre completo</label>
                                <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="8" r="3.6"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>
                                </svg>
                            </div>
                            @if ($active === 'register')
                                @error('name')
                                    <div class="err">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/></svg>
                                        <span>{{ $message }}</span>
                                    </div>
                                @enderror
                            @endif
                        </div>

                        <div>
                            <div class="field {{ $active === 'register' && $errors->has('email') ? 'has-error' : '' }}">
                                <input type="email" id="r_email" name="email" placeholder=" " autocomplete="email"
                                       value="{{ $active === 'register' ? old('email') : '' }}" required>
                                <label for="r_email">Correo electrónico</label>
                                <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="4" width="20" height="16" rx="3"/><path d="m2 7 10 6 10-6"/>
                                </svg>
                            </div>
                            @if ($active === 'register')
                                @error('email')
                                    <div class="err">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/></svg>
                                        <span>{{ $message }}</span>
                                    </div>
                                @enderror
                            @endif
                        </div>

                        <div>
                            <div class="field {{ $active === 'register' && $errors->has('password') ? 'has-error' : '' }}">
                                <input type="password" id="r_password" name="password" placeholder=" " autocomplete="new-password" required>
                                <label for="r_password">Contraseña</label>
                                <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                </svg>
                                <button type="button" class="toggle" data-toggle="r_password" aria-label="Mostrar contraseña">
                                    <svg class="on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2M6.6 6.7C3.9 8.3 2 12 2 12s3.6 7 10 7c1.9 0 3.5-.6 4.9-1.4M14 5.4A9.6 9.6 0 0 0 12 5c-.7 0-1.3.1-1.9.2M21.9 12.4C21.2 11 18.6 7.2 15 5.8"/></svg>
                                </button>
                            </div>
                            <div class="meter" id="meter" data-level="0">
                                <div class="meter__track"><i></i><i></i><i></i><i></i></div>
                                <div class="meter__text" id="meterText">Usa al menos 8 caracteres.</div>
                            </div>
                            @if ($active === 'register')
                                @error('password')
                                    <div class="err">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/></svg>
                                        <span>{{ $message }}</span>
                                    </div>
                                @enderror
                            @endif
                        </div>

                        <div>
                            <div class="field {{ $active === 'register' && $errors->has('password_confirmation') ? 'has-error' : '' }}">
                                <input type="password" id="r_password2" name="password_confirmation" placeholder=" " autocomplete="new-password" required>
                                <label for="r_password2">Confirmar contraseña</label>
                                <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 3l7.5 3v6c0 4.4-3.2 8.2-7.5 9-4.3-.8-7.5-4.6-7.5-9V6z"/><path d="m9 12 2.2 2.2L15.5 10"/>
                                </svg>
                                <button type="button" class="toggle" data-toggle="r_password2" aria-label="Mostrar contraseña">
                                    <svg class="on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2M6.6 6.7C3.9 8.3 2 12 2 12s3.6 7 10 7c1.9 0 3.5-.6 4.9-1.4M14 5.4A9.6 9.6 0 0 0 12 5c-.7 0-1.3.1-1.9.2M21.9 12.4C21.2 11 18.6 7.2 15 5.8"/></svg>
                                </button>
                            </div>
                            <div class="err" id="matchErr" style="display:none">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/></svg>
                                <span>Las contraseñas no coinciden.</span>
                            </div>
                            @if ($active === 'register')
                                @error('password_confirmation')
                                    <div class="err">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/></svg>
                                        <span>{{ $message }}</span>
                                    </div>
                                @enderror
                            @endif
                        </div>

                        <button type="submit" class="submit">
                            <span>
                                Crear mi cuenta
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </span>
                        </button>
                    </div>

                    <div class="swap">¿Ya tienes cuenta? <button type="button" data-go="login">Inicia sesión</button></div>
                </form>
            </div>
        </div>
    </div>

    {{-- ══════════ Barrido al volver al inicio ══════════ --}}
    <div id="wipe" aria-hidden="true">
        <span></span><span></span><span></span><span></span>
        <div class="mark">
            <svg viewBox="0 0 48 48" fill="none" stroke="#4A8FC9" stroke-width="2.2"
                 stroke-linejoin="round" stroke-linecap="round">
                <path d="M8 16 L24 7 L40 16 L24 25 Z"/>
                <path d="M8 16 v16 L24 41 V25"/>
                <path d="M40 16 v16 L24 41"/>
            </svg>
        </div>
    </div>

    {{-- ══════════ Overlay: animación DESPUÉS de enviar (verificando) ══════════ --}}
    <div id="gate" aria-hidden="true">
        <div class="box">
            <div class="ring"><i></i><i></i><i></i></div>
            <h3 id="gateTitle">Verificando credenciales</h3>
            <p>Un momento por favor</p>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        var body   = document.body;
        var card   = document.getElementById('card');
        var intro  = document.getElementById('intro');
        var gate   = document.getElementById('gate');
        var BRAND  = @json($brand);
        var hasErrors = body.dataset.hasErrors === '1';
        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        /* ---------- 1. Partículas del fondo ---------- */
        (function sparks() {
            if (reduced) return;
            var host = document.getElementById('sparks');
            var frag = document.createDocumentFragment();
            for (var i = 0; i < 26; i++) {
                var s = document.createElement('span');
                s.className = 'spark';
                s.style.left = (Math.random() * 100) + '%';
                s.style.bottom = '-10px';
                s.style.animationDuration = (9 + Math.random() * 12) + 's';
                s.style.animationDelay = (Math.random() * 12) + 's';
                s.style.opacity = 0;
                s.style.transform = 'scale(' + (0.5 + Math.random()) + ')';
                frag.appendChild(s);
            }
            host.appendChild(frag);
        })();

        /* ---------- 2. Intro: letras del logotipo ---------- */
        (function letters() {
            var word = document.getElementById('introWord');
            var i, ch, el, delay = 0;
            for (i = 0; i < BRAND.length; i++) {
                ch = BRAND.charAt(i);
                el = document.createElement('span');
                if (ch === ' ') {
                    el.className = 'sp';
                } else {
                    el.textContent = ch;
                    el.style.animationDelay = (0.75 + delay) + 's';
                    delay += 0.058;
                }
                word.appendChild(el);
            }
        })();

        /* ---------- 3. Control del intro ----------
           Se muestra una sola vez por pestaña y NUNCA cuando hay errores
           de validación: en ese caso el usuario ya venía de la pantalla.  */
        var seen = false;
        try { seen = sessionStorage.getItem('mus_intro') === '1'; } catch (e) {}
        var skipIntro = seen || hasErrors || reduced;
        var introTick = null;

        function endIntro() {
            if (intro.classList.contains('is-gone')) return;
            if (introTick) clearInterval(introTick);
            intro.classList.add('is-gone');
            body.classList.add('is-ready');
            try { sessionStorage.setItem('mus_intro', '1'); } catch (e) {}
            setTimeout(function () {
                intro.style.display = 'none';
                focusFirst();
            }, 1150);
        }

        if (skipIntro) {
            intro.style.display = 'none';
            intro.classList.add('is-gone');
            requestAnimationFrame(function () {
                body.classList.add('is-ready');
                setTimeout(focusFirst, 120);
            });
        } else {
            /* Texto de estado que va rotando bajo la barra de carga. */
            var statusEl = document.getElementById('introStatus');
            var frases = ['Iniciando entorno', 'Conectando con el servidor', 'Cargando formularios', 'Listo'];
            var fi = 0;
            introTick = setInterval(function () {
                fi++;
                if (fi >= frases.length) { clearInterval(introTick); introTick = null; return; }
                if (statusEl) {
                    statusEl.style.opacity = '0';
                    setTimeout(function () {
                        statusEl.textContent = frases[fi];
                        statusEl.style.opacity = '1';
                    }, 220);
                }
            }, 700);

            setTimeout(endIntro, 3900);
            intro.addEventListener('click', endIntro);
            document.addEventListener('keydown', function once() {
                endIntro();
                document.removeEventListener('keydown', once);
            });
        }

        function focusFirst() {
            var sel = card.classList.contains('is-register') ? '#r_name' : '#l_email';
            var el = document.querySelector(sel);
            if (el && window.innerWidth > 900) { try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); } }
        }

        /* ---------- 4. Deslizamiento login ⇄ registro ---------- */
        var swapTimer = null;

        /* Onda expansiva desde el punto pulsado, dibujada dentro del panel. */
        function ripple(ev) {
            if (reduced) return;
            var deco = document.getElementById('panelDeco');
            if (!deco || !ev) return;
            var r = deco.getBoundingClientRect();
            var x = ev.clientX - r.left, y = ev.clientY - r.top;
            /* Si el clic viene de un botón fuera del panel, se centra. */
            if (x < 0 || x > r.width || y < 0 || y > r.height) { x = r.width / 2; y = r.height / 2; }
            ['a', 'b'].forEach(function (cls) {
                var o = document.createElement('span');
                o.className = 'ripple ' + cls;
                o.style.left = x + 'px';
                o.style.top = y + 'px';
                deco.appendChild(o);
                setTimeout(function () { if (o.parentNode) o.remove(); }, 1400);
            });
        }

        function goTo(mode, ev) {
            var isReg = mode === 'register';
            if (isReg === card.classList.contains('is-register')) return;

            ripple(ev);
            card.classList.add('is-swapping');
            clearTimeout(swapTimer);
            swapTimer = setTimeout(function () { card.classList.remove('is-swapping'); }, 1250);

            card.classList.toggle('is-register', isReg);
            history.replaceState(null, '', isReg ? @json(route('register')) : @json(route('login')));
            document.title = (isReg ? 'Crear cuenta' : 'Iniciar sesión') + ' · ' + BRAND;
            /* Relanza la entrada escalonada de los campos del panel que entra */
            var target = card.querySelector('.form[data-form="' + mode + '"] .stagger');
            if (target && !reduced) {
                var kids = target.children, k;
                for (k = 0; k < kids.length; k++) {
                    kids[k].style.animation = 'none';
                    /* forzar reflow */
                    void kids[k].offsetWidth;
                    kids[k].style.animation = '';
                }
            }
            setTimeout(focusFirst, 700);
        }

        Array.prototype.forEach.call(document.querySelectorAll('[data-go]'), function (btn) {
            btn.addEventListener('click', function (ev) { goTo(btn.getAttribute('data-go'), ev); });
        });

        /* ---------- 5. Mostrar / ocultar contraseña ---------- */
        Array.prototype.forEach.call(document.querySelectorAll('.toggle'), function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.getAttribute('data-toggle'));
                if (!input) return;
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.classList.toggle('is-on', show);
                btn.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
                try { input.focus({ preventScroll: true }); } catch (e) {}
            });
        });

        /* ---------- 6. Aviso de Bloq Mayús ---------- */
        Array.prototype.forEach.call(document.querySelectorAll('[data-caps-for]'), function (hint) {
            var input = document.getElementById(hint.getAttribute('data-caps-for'));
            if (!input) return;
            function check(e) {
                var on = e.getModifierState && e.getModifierState('CapsLock');
                hint.classList.toggle('show', !!on);
            }
            input.addEventListener('keydown', check);
            input.addEventListener('keyup', check);
            input.addEventListener('blur', function () { hint.classList.remove('show'); });
        });

        /* ---------- 7. Medidor de fuerza de contraseña ---------- */
        (function strength() {
            var input = document.getElementById('r_password');
            var meter = document.getElementById('meter');
            var text  = document.getElementById('meterText');
            if (!input) return;
            var labels = [
                'Usa al menos 8 caracteres.',
                'Muy débil — agrega más caracteres.',
                'Débil — combina mayúsculas y números.',
                'Buena — agrega un símbolo para mejorarla.',
                'Excelente contraseña.'
            ];
            input.addEventListener('input', function () {
                var v = input.value, score = 0;
                if (v.length >= 8) score++;
                if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
                if (/\d/.test(v)) score++;
                if (/[^\w\s]/.test(v)) score++;
                if (!v.length) score = 0;
                if (v.length && v.length < 8) score = 1;
                meter.setAttribute('data-level', String(score));
                text.textContent = labels[score];
            });
        })();

        /* ---------- 8. Confirmación de contraseña en vivo ---------- */
        (function match() {
            var p1 = document.getElementById('r_password');
            var p2 = document.getElementById('r_password2');
            var box = document.getElementById('matchErr');
            if (!p1 || !p2) return;
            function test() {
                var bad = p2.value.length > 0 && p1.value !== p2.value;
                box.style.display = bad ? 'flex' : 'none';
                p2.closest('.field').classList.toggle('has-error', bad);
            }
            p1.addEventListener('input', test);
            p2.addEventListener('input', test);
        })();

        /* ---------- 9. Envío: overlay de verificación ---------- */
        Array.prototype.forEach.call(document.querySelectorAll('form.form'), function (form) {
            form.addEventListener('submit', function (e) {
                var isReg = form.getAttribute('data-form') === 'register';

                /* Validación mínima en cliente para no mostrar el overlay en vano */
                var required = form.querySelectorAll('input[required]');
                var missing = null, i;
                for (i = 0; i < required.length; i++) {
                    if (!required[i].value.trim()) { missing = required[i]; break; }
                }
                if (missing) {
                    e.preventDefault();
                    missing.closest('.field').classList.add('has-error');
                    card.classList.add('is-shaking');
                    setTimeout(function () { card.classList.remove('is-shaking'); }, 520);
                    try { missing.focus(); } catch (err) {}
                    return;
                }
                if (isReg) {
                    var p1 = document.getElementById('r_password');
                    var p2 = document.getElementById('r_password2');
                    if (p1.value !== p2.value) {
                        e.preventDefault();
                        document.getElementById('matchErr').style.display = 'flex';
                        card.classList.add('is-shaking');
                        setTimeout(function () { card.classList.remove('is-shaking'); }, 520);
                        try { p2.focus(); } catch (err) {}
                        return;
                    }
                }

                document.getElementById('gateTitle').textContent =
                    isReg ? 'Creando tu cuenta' : 'Verificando credenciales';
                gate.classList.add('show');
                card.style.transform = 'scale(.985)';
                card.style.opacity = '.6';
                /* El POST sigue su curso normal: Laravel responde con redirect o con errores. */
            });
        });

        /* Si el navegador restaura la página desde caché (botón atrás), limpia el overlay */
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                gate.classList.remove('show');
                card.style.transform = '';
                card.style.opacity = '';
            }
        });

        /* ---------- 10. Volver al inicio con barrido ---------- */
        (function backHome() {
            var link = document.getElementById('backHome');
            var wipe = document.getElementById('wipe');
            if (!link || !wipe) return;
            link.addEventListener('click', function (e) {
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
                e.preventDefault();
                if (reduced) { window.location.href = link.href; return; }
                wipe.classList.add('on');
                try { sessionStorage.removeItem('mus_intro'); } catch (err) {}
                setTimeout(function () { window.location.href = link.href; }, 780);
            });
            window.addEventListener('pageshow', function (e) {
                if (e.persisted) wipe.classList.remove('on');
            });
        })();

        /* ---------- 11. Parallax de las formas del fondo ---------- */
        (function parallax() {
            if (reduced || !window.matchMedia('(pointer:fine)').matches) return;
            var shapes = document.querySelectorAll('[data-float]');
            if (!shapes.length) return;
            var tx = 0, ty = 0, cx = 0, cy = 0, raf = null;

            function loop() {
                cx += (tx - cx) * 0.055;
                cy += (ty - cy) * 0.055;
                Array.prototype.forEach.call(shapes, function (el) {
                    var d = parseFloat(el.getAttribute('data-float')) || 10;
                    el.style.transform = 'translate3d(' + (cx * d) + 'px,' + (cy * d) + 'px,0)';
                });
                if (Math.abs(tx - cx) > 0.0015 || Math.abs(ty - cy) > 0.0015) {
                    raf = requestAnimationFrame(loop);
                } else { raf = null; }
            }

            window.addEventListener('mousemove', function (e) {
                tx = (e.clientX / window.innerWidth - 0.5) * 2;
                ty = (e.clientY / window.innerHeight - 0.5) * 2;
                if (!raf) raf = requestAnimationFrame(loop);
            }, { passive: true });
        })();

        /* ---------- 12. Sacudida cuando el servidor devolvió errores ---------- */
        if (hasErrors && !reduced) {
            setTimeout(function () {
                card.classList.add('is-shaking');
                setTimeout(function () { card.classList.remove('is-shaking'); }, 560);
                var firstErr = card.querySelector('.form:not([style*="display: none"]) .field.has-error input');
                if (firstErr) { try { firstErr.focus({ preventScroll: true }); } catch (e) {} }
            }, 260);
        }
    })();
    </script>
</body>
</html>
