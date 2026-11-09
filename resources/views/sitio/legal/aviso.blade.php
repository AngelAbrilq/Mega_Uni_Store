@extends('sitio.legal._base')

@section('titulo', 'Aviso legal')
@section('descripcion', 'Identificación del responsable de '.config('sitio.marca').', condiciones de uso del sitio, propiedad intelectual y legislación aplicable.')

@section('legal')
@php
    $s = config('sitio'); $l = $s['legal'];
    $responsable = $l['razon_social'] ?: $s['marca'];
@endphp
<h1>Aviso legal</h1>

<h2>1. Identificación del responsable</h2>
<p>En cumplimiento de la Ley 527 de 1999 (comercio electrónico) y del Estatuto del Consumidor (Ley 1480 de 2011), se informa que este sitio web pertenece a:</p>
<ul>
    <li><strong>Titular:</strong> {{ $responsable }}</li>
    @if ($l['nit'])<li><strong>NIT / documento:</strong> {{ $l['nit'] }}</li>@endif
    <li><strong>Domicilio:</strong> @if ($l['direccion']){{ $l['direccion'] }}, @endif{{ $l['ciudad'] }}, {{ $l['departamento'] }}, Colombia</li>
    @if ($l['email'])<li><strong>Correo:</strong> <a href="mailto:{{ $l['email'] }}">{{ $l['email'] }}</a></li>@endif
    @if ($l['telefono'])<li><strong>Teléfono:</strong> {{ $l['telefono'] }}</li>@endif
</ul>

<h2>2. Objeto</h2>
<p>{{ $s['marca'] }} es un software de gestión en la nube (punto de venta, inventario, compras, caja y reportes) ofrecido como servicio a negocios. Este aviso regula el acceso y uso del sitio público y de la prueba gratuita.</p>

<h2>3. Condiciones de uso</h2>
<p>Al navegar el sitio o crear una prueba, el usuario se compromete a:</p>
<ul>
    <li>Suministrar información veraz al registrarse.</li>
    <li>No usar el servicio para fines ilícitos ni para cargar datos de terceros sin autorización.</li>
    <li>No intentar acceder a información de otras empresas, vulnerar la seguridad o automatizar el registro de cuentas.</li>
</ul>
<p>Las cuentas de prueba se eliminan automáticamente, junto con los datos cargados en ellas, tres días después de vencer.</p>

<h2>4. Propiedad intelectual</h2>
<p>El software, su código, diseño, logotipos y textos son propiedad de {{ $responsable }} o se usan con licencia. Se prohíbe su reproducción, distribución o modificación sin autorización escrita. Los datos que cada negocio registra en el sistema son de ese negocio.</p>

<h2>5. Responsabilidad</h2>
<p>Se procura que el servicio esté disponible y libre de errores, pero no se garantiza su funcionamiento ininterrumpido. {{ $responsable }} no responde por daños derivados de un uso indebido del sistema, de fallas de conexión del usuario o de la información que el propio usuario registre. Los enlaces a sitios de terceros se ofrecen como referencia y no implican responsabilidad sobre su contenido.</p>

<h2>6. Datos personales y cookies</h2>
<p>El tratamiento de datos personales se rige por la <a href="{{ route('sitio.privacidad') }}">Política de privacidad</a> y el uso de cookies por la <a href="{{ route('sitio.cookies') }}">Política de cookies</a>.</p>

<h2>7. Legislación y jurisdicción</h2>
<p>Este aviso se rige por las leyes de la República de Colombia. Cualquier controversia se someterá a los jueces competentes de {{ $l['departamento'] }}, sin perjuicio de los derechos que la ley otorga al consumidor.</p>
@endsection
