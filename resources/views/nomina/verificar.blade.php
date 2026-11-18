{{-- Página pública a la que lleva el QR del desprendible (NR-NOM-008).
     Sin sesión y sin recursos externos: debe abrir en cualquier celular.
     Muestra lo mínimo para cotejar el papel; nada de documento ni conceptos. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verificación de desprendible · {{ config('app.name', 'Mega Uni Store') }}</title>
    <style>
        *{ box-sizing:border-box; }
        body{ margin:0; min-height:100vh; display:grid; place-items:center; padding:24px 16px; font-family:system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background:#0E1723; color:#16202E; }
        main{ width:100%; max-width:440px; background:#fff; border-radius:18px; padding:28px 24px; box-shadow:0 20px 60px rgba(0,0,0,.35); }
        .sello{ display:flex; gap:12px; align-items:center; margin-bottom:18px; }
        .sello span{ width:44px; height:44px; flex:none; border-radius:50%; display:grid; place-items:center; font-size:22px; font-weight:700; color:#fff; }
        .ok span{ background:#31684D; } .mal span{ background:#A3302B; }
        h1{ margin:0; font-size:19px; letter-spacing:-.01em; }
        p{ margin:4px 0 0; font-size:13.5px; line-height:1.5; color:#5A6B82; }
        dl{ margin:0; border-top:1px solid #E6EBF2; }
        dl div{ display:flex; justify-content:space-between; gap:16px; padding:11px 0; border-bottom:1px solid #E6EBF2; font-size:14px; }
        dt{ color:#5A6B82; } dd{ margin:0; font-weight:600; text-align:right; }
        .neto dd{ font-size:20px; font-variant-numeric:tabular-nums; }
        code{ font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12.5px; letter-spacing:.04em; }
        footer{ margin-top:16px; font-size:12px; color:#7B8AA0; }
    </style>
</head>
<body>
<main>
    @if ($valido)
        <div class="sello ok"><span aria-hidden="true">✓</span>
            <div><h1>Documento auténtico</h1><p>Este desprendible fue emitido por el sistema y sus valores son los registrados.</p></div></div>
        <dl>
            <div><dt>Empresa</dt><dd>{{ $fila->payroll->tienda?->empresa?->nombre ?? '—' }}</dd></div>
            <div><dt>Local</dt><dd>{{ $fila->payroll->tienda?->nombre ?? '—' }}</dd></div>
            <div><dt>Empleado</dt><dd>{{ $fila->employee?->nombre_completo ?? '—' }}</dd></div>
            <div><dt>Período</dt><dd>{{ $fila->payroll->periodo() }}</dd></div>
            <div><dt>Nómina</dt><dd>{{ $fila->payroll->number }}</dd></div>
            <div class="neto"><dt>Neto pagado</dt><dd>${{ number_format((float) $fila->net, 0, ',', '.') }}</dd></div>
        </dl>
        <footer>Código <code>{{ $codigo }}</code>. Si algún valor no coincide con el papel, el documento fue alterado.</footer>
    @else
        <div class="sello mal"><span aria-hidden="true">!</span>
            <div><h1>No se pudo verificar</h1><p>El código <code>{{ $codigo }}</code> no corresponde a ningún desprendible emitido. Revisa que el enlace esté completo o pide el documento de nuevo al empleador.</p></div></div>
    @endif
</main>
</body>
</html>
