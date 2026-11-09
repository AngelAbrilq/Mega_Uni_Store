@extends('sitio.legal._base')

@section('titulo', 'Política de privacidad y tratamiento de datos')
@section('descripcion', 'Cómo '.config('sitio.marca').' recolecta, usa y protege tus datos personales según la Ley 1581 de 2012, y cómo ejercer tus derechos de habeas data.')

@section('legal')
@php
    $s = config('sitio'); $l = $s['legal'];
    $responsable = $l['razon_social'] ?: $s['marca'];
    $canal = $l['email'] ? '<a href="mailto:'.e($l['email']).'">'.e($l['email']).'</a>' : 'los canales de contacto publicados en este sitio';
@endphp
<h1>Política de tratamiento de datos personales</h1>
<p>Esta política da cumplimiento a la Ley Estatutaria 1581 de 2012, al Decreto 1377 de 2013 (compilado en el Decreto 1074 de 2015) y demás normas de protección de datos personales en Colombia.</p>

<h2>1. Responsable del tratamiento</h2>
<ul>
    <li><strong>Responsable:</strong> {{ $responsable }}</li>
    @if ($l['nit'])<li><strong>NIT / documento:</strong> {{ $l['nit'] }}</li>@endif
    <li><strong>Domicilio:</strong> @if ($l['direccion']){{ $l['direccion'] }}, @endif{{ $l['ciudad'] }}, {{ $l['departamento'] }}, Colombia</li>
    <li><strong>Canal para consultas y reclamos:</strong> {!! $canal !!}@if ($l['telefono']) · {{ $l['telefono'] }}@endif</li>
</ul>

<h2>2. Datos que recolectamos</h2>
<div class="tabla">
<table>
    <thead><tr><th>Dónde</th><th>Datos</th><th>Finalidad</th></tr></thead>
    <tbody>
        <tr><td>Prueba gratuita</td><td>Nombre del negocio, nombre, correo, teléfono (opcional), tipo de negocio y contraseña (guardada cifrada)</td><td>Crear la cuenta de prueba y, si dejaste teléfono o correo, contactarte sobre el servicio</td></tr>
        <tr><td>Registro e ingreso</td><td>Nombre, correo, contraseña cifrada, dirección IP y registros de acceso</td><td>Autenticarte, proteger la cuenta y auditar acciones dentro del sistema</td></tr>
        <tr><td>Navegación</td><td>Cookies técnicas y, solo con tu permiso, cookies de analítica</td><td>Ver la <a href="{{ route('sitio.cookies') }}">política de cookies</a></td></tr>
        <tr><td>Contacto por WhatsApp o correo</td><td>Número, nombre y el contenido del mensaje</td><td>Responder tu solicitud</td></tr>
    </tbody>
</table>
</div>
<p>No recolectamos datos sensibles ni datos de niños, niñas o adolescentes.</p>

<h2>3. Datos que los negocios registran en el sistema</h2>
<p>Cuando un negocio usa {{ $s['marca'] }} y registra a sus propios clientes, proveedores o empleados, ese negocio es el <strong>responsable</strong> de esos datos y {{ $responsable }} actúa como <strong>encargado del tratamiento</strong>: los almacena y procesa solo para prestar el servicio, aislados de los de otras empresas, y no los usa para fines propios.</p>

<h2>4. Proveedores que procesan datos por nuestra cuenta</h2>
<ul>
    <li><strong>Hosting y base de datos</strong>, para alojar el sistema.</li>
    <li><strong>Lectura de facturas con IA (opcional):</strong> si un negocio usa la función de cargar la foto de una factura de proveedor, la imagen se envía al proveedor de inteligencia artificial configurado (Google LLC – Gemini, o Anthropic PBC), con servidores en Estados Unidos, solo para extraer los productos y valores de la factura.</li>
    <li><strong>Google Analytics</strong>, solo si aceptas las cookies de analítica.</li>
</ul>
<p>Estas transmisiones se hacen bajo contratos que obligan al proveedor a guardar confidencialidad y a usar los datos solo para el fin indicado (art. 25 del Decreto 1377 de 2013).</p>

<h2>5. Tus derechos como titular</h2>
<p>Según el artículo 8 de la Ley 1581 de 2012 puedes:</p>
<ul>
    <li>Conocer, actualizar y rectificar tus datos.</li>
    <li>Pedir prueba de la autorización que nos diste.</li>
    <li>Saber qué uso le hemos dado a tus datos.</li>
    <li>Revocar la autorización o pedir que suprimamos tus datos cuando no exista un deber legal o contractual de conservarlos.</li>
    <li>Presentar quejas ante la Superintendencia de Industria y Comercio (SIC) después de agotar el trámite con nosotros.</li>
    <li>Acceder gratis a tus datos.</li>
</ul>

<h2>6. Cómo ejercerlos</h2>
<p>Escribe a {!! $canal !!} indicando tu nombre, tu documento, lo que pides y un medio para responderte.</p>
<ul>
    <li><strong>Consultas:</strong> respondemos en máximo 10 días hábiles, prorrogables 5 días más avisándote el motivo (art. 14).</li>
    <li><strong>Reclamos</strong> (corrección, actualización, supresión o revocatoria): máximo 15 días hábiles, prorrogables 8 días más avisándote el motivo (art. 15).</li>
</ul>

<h2>7. Seguridad</h2>
<p>Las contraseñas se guardan cifradas con bcrypt, el tráfico viaja por HTTPS, cada empresa ve únicamente sus propios datos y las acciones relevantes quedan registradas en auditoría. Hacemos copias de seguridad diarias.</p>

<h2>8. Conservación</h2>
<p>Los datos de una cuenta de prueba se eliminan automáticamente tres días (72 horas) después de que la prueba vence. Los de las cuentas activas se conservan mientras dure la relación y, después, solo durante el tiempo que exijan las obligaciones legales, contables o tributarias.</p>

<h2>9. Autorización y vigencia</h2>
<p>Al crear una cuenta o una prueba, nos autorizas a tratar tus datos según esta política. La política rige desde la fecha indicada al final y cualquier cambio importante se publicará en esta misma página.</p>
@endsection
