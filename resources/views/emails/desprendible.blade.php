<!doctype html>
<html lang="es">
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1e293b">
    <table role="presentation" width="100%" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;border:1px solid #e2e8f0">
        <tr><td style="height:6px;background:#2E6EA8;border-radius:12px 12px 0 0"></td></tr>
        <tr><td style="padding:24px 28px">
            <p style="margin:0 0 4px;font-size:12px;color:#64748b;letter-spacing:.06em;text-transform:uppercase">{{ \App\Models\Setting::obtener('negocio.nombre') }}</p>
            <h1 style="margin:0 0 14px;font-size:20px;color:#1B4870">Hola, {{ $fila->employee->first_name }}</h1>
            <p style="margin:0 0 10px;font-size:14px;line-height:1.6">
                Adjuntamos tu desprendible de nómina del período
                <b>{{ $fila->payroll->period_start->format('d/m/Y') }} – {{ $fila->payroll->period_end->format('d/m/Y') }}</b>.
            </p>
            <p style="margin:0 0 10px;font-size:14px;line-height:1.6">
                Neto a pagar: <b>${{ number_format((float) $fila->net, 0, ',', '.') }}</b>
            </p>
            <p style="margin:0;font-size:12px;color:#64748b">
                El documento trae un código QR para validar su autenticidad (código {{ $fila->verification_code }}).
            </p>
        </td></tr>
    </table>
</body>
</html>
