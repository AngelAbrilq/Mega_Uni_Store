<?php

namespace Tests\Feature;

use App\Exceptions\InvoiceReadingException;
use App\Services\InvoiceAIReaderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceAIReaderServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'smart_inventory.driver' => 'gemini',
            'smart_inventory.providers.gemini.key' => 'test-key',
            'smart_inventory.http.retries' => 0,
        ]);
    }

    private function photo(): UploadedFile
    {
        // Requiere la extensión GD (activa por defecto en Laragon).
        return UploadedFile::fake()->image('factura.jpg', 1200, 1600);
    }

    private function fakeGemini(array $payload, int $status = 200): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => json_encode($payload)]]]]],
        ], $status)]);
    }

    #[Test]
    public function devuelve_la_extraccion_cuando_la_factura_es_legible(): void
    {
        $this->fakeGemini([
            'es_factura' => true,
            'calidad_imagen' => ['legible' => true, 'confianza' => 0.93, 'problemas' => []],
            'proveedor' => ['nombre' => 'Distribuidora X', 'nit' => '900123456-7', 'numero_factura' => 'FE-101', 'fecha' => '2026-09-30'],
            'items' => [['producto' => 'Galleta', 'cantidad_cajas' => 1, 'cantidad_unidades' => 10, 'costo_total_compra' => 20, 'confianza' => 0.9]],
            'totales_factura' => ['subtotal' => 20, 'descuento' => 0, 'iva' => 0, 'total' => 20],
            'observaciones' => null,
        ]);

        $data = app(InvoiceAIReaderService::class)->read($this->photo());

        $this->assertSame('Galleta', $data['items'][0]['producto']);
        $this->assertSame('gemini', $data['_meta']['driver']);
        Http::assertSent(fn ($r) => $r->hasHeader('x-goog-api-key', 'test-key')
            && $r['generationConfig']['responseMimeType'] === 'application/json');
    }

    #[Test]
    public function rechaza_una_foto_borrosa(): void
    {
        $this->fakeGemini([
            'es_factura' => true,
            'calidad_imagen' => ['legible' => false, 'confianza' => 0.3, 'problemas' => ['imagen movida']],
            'proveedor' => null, 'items' => [], 'totales_factura' => null, 'observaciones' => null,
        ]);

        try {
            app(InvoiceAIReaderService::class)->read($this->photo());
            $this->fail('Se esperaba InvoiceReadingException');
        } catch (InvoiceReadingException $e) {
            $this->assertSame(InvoiceReadingException::IMAGE_UNREADABLE, $e->errorCode);
            $this->assertStringContainsString('imagen movida', $e->getMessage());
            $this->assertSame(422, $e->status());
        }
    }

    #[Test]
    public function traduce_la_caida_del_proveedor_a_502(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 503)]);

        try {
            app(InvoiceAIReaderService::class)->read($this->photo());
            $this->fail('Se esperaba InvoiceReadingException');
        } catch (InvoiceReadingException $e) {
            $this->assertSame(InvoiceReadingException::PROVIDER_ERROR, $e->errorCode);
            $this->assertSame(502, $e->status());
        }
    }
}
