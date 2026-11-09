<?php

namespace App\Services;

use App\Jobs\EnviarReporteJob;
use App\Models\Empresa;
use App\Models\Product;
use App\Models\ReportDelivery;
use App\Models\ReportExport;
use App\Models\Tienda;
use App\Support\Contexto;
use App\Support\Exportes\PdfWriter;
use App\Support\Exportes\Tabla;
use App\Support\Exportes\XlsxWriter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Exportar reportes a PDF/XLSX y enviarlos (RP-EXP-001 · TBL-ENV-001 · RN-36).
 *
 * Las cifras salen de ReporteService —las mismas de la pantalla de
 * reportes—, se arman como una Tabla y cada escritor la pinta en su
 * formato. El archivo queda guardado (disco privado) y registrado; los
 * envíos se registran aparte, uno por destinatario.
 */
class ExportacionService
{
    /** Reportes que se pueden exportar: clave => título. */
    public const REPORTES = [
        'ventas_dia'   => 'Ventas por día',
        'productos'    => 'Productos más vendidos',
        'categorias'   => 'Ventas por categoría',
        'medios'       => 'Ingresos por medio de pago',
        'vendedores'   => 'Ventas por vendedor',
        'stock_bajo'   => 'Productos con stock bajo',
        'sin_rotacion' => 'Inventario sin rotación',
    ];

    public function __construct(private ReporteService $reportes) {}

    /** Arma la tabla del reporte con los datos del negocio y el local activos. */
    public function tabla(string $reporte, Carbon $desde, Carbon $hasta): Tabla
    {
        $rango   = $desde->format('d/m/Y') . ' – ' . $hasta->format('d/m/Y');
        $negocio = trim((Empresa::find(Contexto::empresaId())?->nombre ?? '') . ' · ' . (Tienda::find(Contexto::tiendaId())?->nombre ?? ''), ' ·');
        $titulo  = self::REPORTES[$reporte] ?? throw ValidationException::withMessages(['report' => 'Ese reporte no existe.']);
        $T = fn (string $e, string $tipo = Tabla::TEXTO, float $ancho = 1) => ['etiqueta' => $e, 'tipo' => $tipo, 'ancho' => $ancho];

        [$columnas, $filas, $totales] = match ($reporte) {
            'ventas_dia' => (function () use ($desde, $hasta, $T) {
                $d = $this->reportes->porDia($desde, $hasta);
                return [
                    [$T('Día'), $T('Ventas', Tabla::ENTERO), $T('Total', Tabla::MONEDA), $T('Utilidad', Tabla::MONEDA)],
                    array_map(fn ($r) => [$r['dia'], $r['ventas'], $r['total'], $r['utilidad']], $d),
                    ['Total', array_sum(array_column($d, 'ventas')), array_sum(array_column($d, 'total')), array_sum(array_column($d, 'utilidad'))],
                ];
            })(),
            'productos' => (function () use ($desde, $hasta, $T) {
                $d = $this->reportes->topProductos($desde, $hasta, 1000);
                return [
                    [$T('Producto', Tabla::TEXTO, 3), $T('SKU'), $T('Unidades', Tabla::NUMERO), $T('Ingresos', Tabla::MONEDA), $T('Utilidad', Tabla::MONEDA), $T('Ventas', Tabla::ENTERO)],
                    array_map(fn ($r) => [$r['nombre'], $r['sku'], $r['unidades'], $r['ingresos'], $r['utilidad'], $r['veces']], $d),
                    ['Total', '', array_sum(array_column($d, 'unidades')), array_sum(array_column($d, 'ingresos')), array_sum(array_column($d, 'utilidad')), array_sum(array_column($d, 'veces'))],
                ];
            })(),
            'categorias' => (function () use ($desde, $hasta, $T) {
                $d = $this->reportes->porCategoria($desde, $hasta, 500);
                return [
                    [$T('Categoría', Tabla::TEXTO, 2), $T('Unidades', Tabla::NUMERO), $T('Ingresos', Tabla::MONEDA)],
                    array_map(fn ($r) => [$r['nombre'], $r['unidades'], $r['ingresos']], $d),
                    ['Total', array_sum(array_column($d, 'unidades')), array_sum(array_column($d, 'ingresos'))],
                ];
            })(),
            'medios' => (function () use ($desde, $hasta, $T) {
                $d = $this->reportes->porMedio($desde, $hasta);
                return [
                    [$T('Medio de pago', Tabla::TEXTO, 2), $T('Movimientos', Tabla::ENTERO), $T('Total', Tabla::MONEDA)],
                    array_map(fn ($r) => [$r['nombre'], $r['veces'], $r['total']], $d),
                    ['Total', array_sum(array_column($d, 'veces')), array_sum(array_column($d, 'total'))],
                ];
            })(),
            'vendedores' => (function () use ($desde, $hasta, $T) {
                $d = $this->reportes->porVendedor($desde, $hasta);
                return [
                    [$T('Vendedor', Tabla::TEXTO, 2), $T('Ventas', Tabla::ENTERO), $T('Total', Tabla::MONEDA), $T('Utilidad', Tabla::MONEDA)],
                    array_map(fn ($r) => [$r['nombre'], $r['ventas'], $r['total'], $r['utilidad']], $d),
                    ['Total', array_sum(array_column($d, 'ventas')), array_sum(array_column($d, 'total')), array_sum(array_column($d, 'utilidad'))],
                ];
            })(),
            'stock_bajo' => (function () use ($T) {
                $d = Product::query()->lowStock()->with('existencia')->orderBy('name')->get()
                    ->map(fn (Product $p) => [$p->name, $p->sku, (float) $p->stock, (float) $p->min_stock, max(0, (float) $p->min_stock - (float) $p->stock)])
                    ->all();
                return [
                    [$T('Producto', Tabla::TEXTO, 3), $T('SKU'), $T('Existencias', Tabla::NUMERO), $T('Mínimo', Tabla::NUMERO), $T('Faltan', Tabla::NUMERO)],
                    $d, null,
                ];
            })(),
            'sin_rotacion' => (function () use ($hasta, $T) {
                $dias = max(7, (int) \App\Models\Setting::obtener('inventario.dias_rotacion', '30'));
                $d = $this->reportes->sinRotacion($hasta->copy()->subDays($dias)->startOfDay(), $hasta, 1000);
                return [
                    [$T('Producto', Tabla::TEXTO, 3), $T('SKU'), $T('Existencias', Tabla::NUMERO), $T('Valor inmóvil', Tabla::MONEDA)],
                    array_map(fn ($r) => [$r['nombre'], $r['sku'], $r['stock'], $r['inmovil']], $d),
                    ['Total', '', array_sum(array_column($d, 'stock')), array_sum(array_column($d, 'inmovil'))],
                ];
            })(),
        };

        $subtitulo = in_array($reporte, ['stock_bajo'], true) ? 'Corte al ' . now()->format('d/m/Y') : $rango;

        return new Tabla($titulo, $subtitulo, $columnas, $filas, $totales, $negocio);
    }

    /**
     * Genera el archivo, lo guarda y registra la exportación.
     *
     * @throws ValidationException
     */
    public function exportar(string $reporte, string $formato, Carbon $desde, Carbon $hasta, ?int $userId): ReportExport
    {
        $tabla = $this->tabla($reporte, $desde, $hasta);

        $contenido = match ($formato) {
            ReportExport::PDF  => (new PdfWriter())->render($tabla, now()->format('d/m/Y H:i')),
            ReportExport::XLSX => XlsxWriter::disponible()
                ? (new XlsxWriter())->render($tabla)
                : throw ValidationException::withMessages(['format' => 'El servidor no tiene la extensión zip de PHP: activa «zip» en php.ini para exportar a Excel.']),
            default => throw ValidationException::withMessages(['format' => 'Formato no soportado.']),
        };

        $ruta = 'exportes/' . Contexto::empresaId() . '/' . Str::uuid() . '.' . $formato;
        Storage::disk('local')->put($ruta, $contenido);

        return ReportExport::create([
            'tienda_id'  => Contexto::tiendaId(),
            'user_id'    => $userId,
            'report'     => $reporte,
            'format'     => $formato,
            'filters'    => ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')],
            'file_name'  => Str::slug($tabla->titulo) . '_' . $desde->format('Ymd') . '-' . $hasta->format('Ymd') . '.' . $formato,
            'file_path'  => $ruta,
            'size_bytes' => strlen($contenido),
            'rows'       => count($tabla->filas),
        ]);
    }

    /**
     * Envío por correo: queda PENDIENTE y lo procesa EnviarReporteJob al
     * terminar la respuesta (sin depender de un worker de colas).
     */
    public function enviarPorCorreo(ReportExport $export, string $correo, ?int $userId): ReportDelivery
    {
        $envio = $export->deliveries()->create([
            'user_id'   => $userId,
            'channel'   => ReportDelivery::EMAIL,
            'recipient' => mb_strtolower(trim($correo)),
            'status'    => ReportDelivery::PENDIENTE,
        ]);

        EnviarReporteJob::dispatchAfterResponse($envio->id);

        return $envio;
    }

    /**
     * WhatsApp no tiene API gratuita para adjuntar archivos: se arma el
     * mensaje con un enlace firmado (vence en 7 días) para descargar el
     * archivo y se abre wa.me con el número. El envío queda registrado.
     *
     * @return array{envio: ReportDelivery, url: string}
     */
    public function enviarPorWhatsApp(ReportExport $export, string $telefono, ?int $userId): array
    {
        $numero = self::normalizarTelefono($telefono)
            ?? throw ValidationException::withMessages(['recipient' => 'Escribe un número de WhatsApp válido: 10 dígitos (300 123 4567) o con indicativo (+57…).']);

        $enlace  = URL::temporarySignedRoute('exportes.publico', now()->addDays(7), ['export' => $export->id]);
        $mensaje = (self::REPORTES[$export->report] ?? 'Reporte') . ' (' . strtoupper($export->format) . ")\n" . $enlace;

        $envio = $export->deliveries()->create([
            'user_id'   => $userId,
            'channel'   => ReportDelivery::WHATSAPP,
            'recipient' => '+' . $numero,
            'status'    => ReportDelivery::ENVIADO,
            'sent_at'   => now(),
        ]);

        return ['envio' => $envio, 'url' => 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensaje)];
    }

    /** 3001234567 → 573001234567; +57 300… → 57300…; null si no es un número válido. */
    public static function normalizarTelefono(string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', $telefono);

        if (strlen($digitos) === 10 && str_starts_with($digitos, '3')) {
            $digitos = '57' . $digitos;
        }

        return preg_match('/^\d{11,15}$/', $digitos) ? $digitos : null;
    }
}
