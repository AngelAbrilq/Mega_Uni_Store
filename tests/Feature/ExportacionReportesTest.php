<?php

namespace Tests\Feature;

use App\Mail\ReporteMail;
use App\Models\ReportDelivery;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\ExportacionService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\MontaNegocio;
use Tests\TestCase;
use ZipArchive;

/**
 * RP-EXP-001 · Exportar reportes a PDF/Excel y enviarlos.
 * TBL-ENV-001 · Registrar cada envío con canal, destinatario y estado.
 * RN-36      · Archivo privado, enlace público solo firmado y con vencimiento.
 */
class ExportacionReportesTest extends TestCase
{
    use MontaNegocio, RefreshDatabase;

    private User $reportero;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->montarNegocio('exportes');
        $this->reportero = $this->empleado('Reportero');

        $cajero   = $this->empleado('Cajero');
        $producto = $this->productoConExistencia('Cuaderño «Áreas»', 12_000, 7_000, 30);
        $this->trabajando(fn () => app(SaleService::class)->registrar(
            lineas: [['product_id' => $producto->id, 'quantity' => 2]],
            pagos: [['payment_method_id' => $this->efectivo->id, 'amount' => 24_000]],
            userId: $cajero->id,
        ));
    }

    /* ═══════════════ Exportar ═══════════════ */

    public function test_exporta_a_pdf_valido_y_lo_registra(): void
    {
        $this->exportar('productos', 'pdf')->assertSessionHasNoErrors();

        $x = $this->trabajando(fn () => ReportExport::firstOrFail());
        $pdf = Storage::disk('local')->get($x->file_path);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertStringContainsString(mb_convert_encoding('Cuaderño', 'Windows-1252', 'UTF-8'), $pdf, 'Las tildes y eñes van en WinAnsi.');
        $this->assertSame(1, $x->rows);
        $this->assertSame(strlen($pdf), $x->size_bytes);

        $this->actingAs($this->reportero)->get("/reports/exportes/{$x->id}/descargar")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_exporta_a_excel_con_las_cifras_como_numeros(): void
    {
        $this->exportar('ventas_dia', 'xlsx')->assertSessionHasNoErrors();

        $x    = $this->trabajando(fn () => ReportExport::firstOrFail());
        $ruta = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($ruta, Storage::disk('local')->get($x->file_path));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($ruta) === true, 'Debe ser un ZIP (XLSX) válido.');
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($ruta);

        $this->assertStringContainsString('Día', $hoja);
        $this->assertStringContainsString('<v>24000</v>', $hoja, 'El total del día va como número, sin formato de texto.');
    }

    public function test_todos_los_reportes_se_pueden_exportar(): void
    {
        foreach (array_keys(ExportacionService::REPORTES) as $reporte) {
            $this->exportar($reporte, 'pdf')->assertSessionHasNoErrors();
        }

        $this->assertSame(count(ExportacionService::REPORTES), $this->trabajando(fn () => ReportExport::count()));
    }

    public function test_un_cajero_no_exporta(): void
    {
        $this->actingAs($this->empleado('Cajero'))->get('/reports/exportes')->assertForbidden();
    }

    /* ═══════════════ Envíos ═══════════════ */

    public function test_el_envio_por_correo_adjunta_el_archivo_y_queda_enviado(): void
    {
        Mail::fake();
        $x = $this->exportado();

        $this->actingAs($this->reportero)
            ->post("/reports/exportes/{$x->id}/enviar", ['channel' => 'email', 'recipient' => 'Gerencia@Negocio.com'])
            ->assertSessionHasNoErrors();

        Mail::assertSent(ReporteMail::class, fn (ReporteMail $m) => $m->hasTo('gerencia@negocio.com')
            && count($m->attachments()) === 1);
        $this->assertDatabaseHas('report_deliveries', [
            'report_export_id' => $x->id, 'channel' => 'email', 'recipient' => 'gerencia@negocio.com', 'status' => 'enviado',
        ]);
    }

    public function test_si_el_correo_falla_el_envio_queda_fallido_con_el_motivo(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        $x = $this->exportado();

        $this->actingAs($this->reportero)
            ->post("/reports/exportes/{$x->id}/enviar", ['channel' => 'email', 'recipient' => 'a@b.co']);

        $this->assertDatabaseHas('report_deliveries', ['status' => 'fallido', 'error' => 'SMTP caído']);
    }

    public function test_whatsapp_normaliza_el_numero_y_arma_el_enlace(): void
    {
        $x = $this->exportado();

        $this->actingAs($this->reportero)
            ->post("/reports/exportes/{$x->id}/enviar", ['channel' => 'whatsapp', 'recipient' => '300 123 4567'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('whatsapp', fn ($url) => str_starts_with($url, 'https://wa.me/573001234567?text='));

        $this->assertDatabaseHas('report_deliveries', ['channel' => 'whatsapp', 'recipient' => '+573001234567', 'status' => 'enviado']);
    }

    public function test_rechaza_destinatarios_invalidos(): void
    {
        $x = $this->exportado();

        $this->actingAs($this->reportero)->from('/reports/exportes')
            ->post("/reports/exportes/{$x->id}/enviar", ['channel' => 'email', 'recipient' => 'no-es-correo'])
            ->assertSessionHasErrors('recipient');
        $this->actingAs($this->reportero)->from('/reports/exportes')
            ->post("/reports/exportes/{$x->id}/enviar", ['channel' => 'whatsapp', 'recipient' => '12345'])
            ->assertSessionHasErrors('recipient');

        $this->assertSame(0, ReportDelivery::sinFiltroEmpresa()->count());
    }

    public function test_el_enlace_publico_solo_funciona_firmado(): void
    {
        $x = $this->exportado();
        auth()->logout();

        $this->get(URL::temporarySignedRoute('exportes.publico', now()->addDay(), ['export' => $x->id]))->assertOk();
        $this->get("/exportes/{$x->id}/archivo")->assertForbidden();
        $this->get(URL::temporarySignedRoute('exportes.publico', now()->subMinute(), ['export' => $x->id]))->assertForbidden();
    }

    public function test_el_historial_muestra_archivos_y_envios(): void
    {
        $x = $this->exportado();
        $this->actingAs($this->reportero)->post("/reports/exportes/{$x->id}/enviar", ['channel' => 'whatsapp', 'recipient' => '3001234567']);

        $this->actingAs($this->reportero)->get('/reports/exportes')
            ->assertOk()->assertSee('Productos más vendidos')->assertSee('+573001234567')->assertSee('Generar archivo');
        $this->actingAs($this->reportero)->get('/reports')->assertOk()->assertSee('Exportar y enviar');
    }

    public function test_normalizacion_de_telefonos(): void
    {
        $this->assertSame('573001234567', ExportacionService::normalizarTelefono('+57 300-123-4567'));
        $this->assertSame('573001234567', ExportacionService::normalizarTelefono('3001234567'));
        $this->assertNull(ExportacionService::normalizarTelefono('123'));
    }

    /* ═══════════════ Ayudas ═══════════════ */

    private function exportar(string $reporte, string $formato)
    {
        return $this->actingAs($this->reportero)->post('/reports/exportes', [
            'report' => $reporte, 'format' => $formato,
            'desde' => now()->subDays(7)->format('Y-m-d'), 'hasta' => now()->format('Y-m-d'),
        ]);
    }

    private function exportado(): ReportExport
    {
        $this->exportar('productos', 'pdf');

        return $this->trabajando(fn () => ReportExport::latest('id')->firstOrFail());
    }
}
