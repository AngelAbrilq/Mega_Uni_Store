@extends('sitio.legal._base')

@section('titulo', 'Política de cookies')
@section('descripcion', 'Qué cookies usa '.config('sitio.marca').', para qué sirve cada una, cuánto duran y cómo cambiar tu elección.')

@section('legal')
@php
    $ga = config('sitio.ga4_id');
    $sesion = config('session.cookie');
    $minSesion = (int) config('session.lifetime');
@endphp
<h1>Política de cookies</h1>
<p>Una cookie es un archivo pequeño que el sitio guarda en tu navegador. Aquí explicamos cuáles usamos y cómo controlarlas.</p>

<h2>1. Cookies necesarias (siempre activas)</h2>
<p>Sin ellas el sitio no funciona ni es seguro, por eso no piden permiso.</p>
<div class="tabla">
<table>
    <thead><tr><th>Cookie</th><th>Para qué sirve</th><th>Duración</th></tr></thead>
    <tbody>
        <tr><td><code>{{ $sesion }}</code></td><td>Mantener tu sesión iniciada</td><td>{{ $minSesion }} minutos de inactividad</td></tr>
        <tr><td><code>XSRF-TOKEN</code></td><td>Proteger los formularios contra envíos falsificados (CSRF)</td><td>{{ $minSesion }} minutos</td></tr>
        <tr><td><code>mus_cookies</code></td><td>Recordar tu elección sobre las cookies</td><td>180 días</td></tr>
    </tbody>
</table>
</div>

<h2>2. Cookies de analítica (solo con tu permiso)</h2>
@if ($ga)
<p>Usamos Google Analytics 4 para medir visitas de forma agregada, con la IP anonimizada y sin señales publicitarias. Solo se activan si eliges «Aceptar analítica».</p>
<div class="tabla">
<table>
    <thead><tr><th>Cookie</th><th>Proveedor</th><th>Para qué sirve</th><th>Duración</th></tr></thead>
    <tbody>
        <tr><td><code>_ga</code></td><td>Google</td><td>Distinguir visitantes</td><td>2 años</td></tr>
        <tr><td><code>_ga_{{ \Illuminate\Support\Str::after($ga, 'G-') }}</code></td><td>Google</td><td>Mantener el estado de la visita</td><td>2 años</td></tr>
    </tbody>
</table>
</div>
@else
<p>Actualmente no usamos cookies de analítica ni de publicidad.</p>
@endif

<h2>3. Cómo cambiar tu elección</h2>
<p>Puedes cambiarla cuando quieras desde <button type="button" class="enlace" data-cookies-abrir style="background:none;border:0;padding:0;color:var(--a-600);font:inherit;text-decoration:underline;cursor:pointer">Configurar cookies</button> o borrarlas desde la configuración de tu navegador. Si retiras el permiso, eliminamos las cookies de analítica.</p>

<p>Para más información sobre tus datos, consulta la <a href="{{ route('sitio.privacidad') }}">Política de privacidad</a>.</p>
@endsection
