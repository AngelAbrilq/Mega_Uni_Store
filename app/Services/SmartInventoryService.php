<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Contexto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Orquesta "Smart Inventory Entry":
 *   analyze() → IA lee → backend recalcula y cruza con catálogo → tabla de validación (sin INSERT)
 *   confirm() → backend recalcula de nuevo lo que el usuario corrigió → compra en BORRADOR
 *
 * La entrada al kardex sigue siendo PurchaseService::recibir(): este módulo
 * no abre un segundo camino para mover stock.
 */
class SmartInventoryService
{
    public function __construct(
        private InvoiceAIReaderService $reader,
        private InventoryMathService $math,
        private PurchaseService $purchases,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(UploadedFile $image, ?float $markupPercent = null): array
    {
        $extraction = $this->reader->read($image);
        $imageRef   = $this->storeImage($image);
        $markup     = $markupPercent ?? (float) config('smart_inventory.default_markup_percent');

        $products = $this->matchProducts($extraction['items']);
        $supplier = $this->matchSupplier($extraction['proveedor'] ?? []);

        $lines = [];
        foreach ($extraction['items'] as $index => $aiItem) {
            $lines[] = $this->buildLine($index, (array) $aiItem, $products, $markup);
        }

        $invoiceNumber = $extraction['proveedor']['numero_factura'] ?? null;

        return [
            'imagen_ref' => $imageRef,
            'proveedor'  => [
                'leido'      => $extraction['proveedor'] ?? null,
                'coincide'   => $supplier?->only(['id', 'name', 'tax_id']),
                'duplicada'  => $supplier && $invoiceNumber ? $this->isDuplicate($supplier->id, $invoiceNumber) : false,
            ],
            'items'           => $lines,
            'cuadre_factura'  => $this->math->reconcileInvoice(
                array_map(fn ($l) => $l['calculado']['costo_total_compra'] ?? 0, array_filter($lines, fn ($l) => $l['calculado'] !== null)),
                $extraction['totales_factura'] ?? null,
            ),
            'resumen' => [
                'renglones'          => count($lines),
                'requieren_revision' => count(array_filter($lines, fn ($l) => $l['estado'] !== 'ok')),
                'corregidos_por_backend' => count(array_filter($lines, fn ($l) => $l['discrepancias'] !== [])),
            ],
            'calidad_imagen' => $extraction['calidad_imagen'],
            'observaciones'  => $extraction['observaciones'] ?? null,
            'meta'           => $extraction['_meta'],
        ];
    }

    /**
     * Crea la compra en borrador con las cifras RECALCULADAS en backend.
     *
     * @param  array<string, mixed>  $data  validado por ConfirmSmartEntryRequest
     */
    public function confirm(array $data, int $userId): Purchase
    {
        if (! empty($data['numero_factura']) && $this->isDuplicate((int) $data['supplier_id'], $data['numero_factura'])) {
            throw ValidationException::withMessages([
                'numero_factura' => 'Esta factura ya fue registrada para este proveedor.',
            ]);
        }

        return DB::transaction(function () use ($data, $userId) {
            $purchaseLines = [];
            $priceUpdates  = [];

            foreach ($data['items'] as $i => $item) {
                try {
                    $calc = $this->math->calculate(
                        (float) $item['cantidad_cajas'],
                        (float) $item['cantidad_unidades'],
                        (float) $item['costo_total_compra'],
                        isset($item['precio_venta_unitario']) ? (float) $item['precio_venta_unitario'] : null,
                    );
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages(["items.{$i}" => $e->getMessage()]);
                }

                $purchaseLines[] = [
                    'product_id'  => (int) $item['product_id'],
                    'quantity'    => $calc['unidades_totales'],   // el kardex vive en UNIDADES, no en cajas
                    'unit_cost'   => $calc['costo_unitario'],
                    'tax_rate'    => (float) ($item['iva_porcentaje'] ?? 0),
                    'update_cost' => (bool) ($item['actualizar_costo'] ?? true),
                ];

                if (! empty($item['actualizar_precio']) && $calc['precio_venta_unitario'] !== null) {
                    $priceUpdates[(int) $item['product_id']] = $calc['precio_venta_unitario'];
                }
            }

            $purchase = $this->purchases->registrar(
                supplierId: (int) $data['supplier_id'],
                lineas: $purchaseLines,
                userId: $userId,
                factura: $data['numero_factura'] ?? null,
                fecha: $data['fecha'] ?? null,
                notas: trim('Ingreso inteligente (IA)' . (! empty($data['imagen_ref']) ? ' · soporte ' . $data['imagen_ref'] : '')),
            );

            if ($priceUpdates !== []) {
                // Una sola consulta para todos los productos (sin N+1).
                Product::whereIn('id', array_keys($priceUpdates))->get()
                    ->each(fn (Product $p) => $p->forceFill(['price' => $priceUpdates[$p->id], 'updated_by' => $userId])->save());
            }

            return $purchase;
        });
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>          $ai
     * @param  array<string, Collection>     $products  ['codigo' => …, 'nombre' => …]
     * @return array<string, mixed>
     */
    private function buildLine(int $index, array $ai, array $products, float $markup): array
    {
        $product = $this->findProduct($ai, $products);
        $alerts  = [];

        // Origen del precio de venta: factura (PVP impreso) → precio actual del producto → sugerido por markup.
        [$unitSale, $priceSource] = match (true) {
            is_numeric($ai['precio_venta_unitario'] ?? null) => [(float) $ai['precio_venta_unitario'], 'factura'],
            $product && (float) $product->price > 0         => [(float) $product->price, 'producto'],
            default                                          => [null, 'sugerido'],
        };

        try {
            if (! is_numeric($ai['cantidad_cajas'] ?? null) || ! is_numeric($ai['cantidad_unidades'] ?? null) || ! is_numeric($ai['costo_total_compra'] ?? null)) {
                throw new InvalidArgumentException('Faltan cantidades o costo legibles en este renglón.');
            }

            $boxes = (float) $ai['cantidad_cajas'];
            $upb   = (float) $ai['cantidad_unidades'];
            $total = (float) $ai['costo_total_compra'];

            // La IA leyó dos números que se contradicen entre sí (costo de caja × cajas ≠ total).
            if (is_numeric($ai['costo_caja'] ?? null) && abs((float) $ai['costo_caja'] * $boxes - $total) > max(1, $boxes * 0.01)) {
                $alerts[] = 'LECTURA_INCONSISTENTE';
            }

            if ($unitSale === null) {
                $base     = $this->math->calculate($boxes, $upb, $total);
                $unitSale = $this->math->suggestUnitPrice($base['costo_unitario'], $markup);
            }

            $calc = $this->math->calculate($boxes, $upb, $total, $unitSale);
        } catch (InvalidArgumentException $e) {
            return [
                'indice' => $index, 'estado' => 'incompleto', 'error' => $e->getMessage(),
                'ia' => $ai, 'calculado' => null, 'discrepancias' => [], 'alertas' => ['DATOS_INCOMPLETOS'],
                'producto_sugerido' => $product?->only(['id', 'name', 'sku', 'barcode', 'price', 'cost']),
                'origen_precio_venta' => null,
            ];
        }

        $discrepancies = $this->math->discrepancies($ai, $calc);
        $alerts = array_values(array_unique(array_merge(
            $alerts,
            array_diff($calc['alertas'], ['SIN_PRECIO_VENTA']),
            $product ? [] : ['PRODUCTO_NO_ENCONTRADO'],
            ((float) ($ai['confianza'] ?? 0)) < (float) config('smart_inventory.min_confidence') ? ['BAJA_CONFIANZA'] : [],
            $priceSource === 'sugerido' ? ['PRECIO_SUGERIDO'] : [],
        )));
        unset($calc['alertas']);

        $blocking = array_intersect($alerts, ['PRODUCTO_NO_ENCONTRADO', 'BAJA_CONFIANZA', 'LECTURA_INCONSISTENTE', 'VENTA_BAJO_COSTO']);

        return [
            'indice'              => $index,
            'estado'              => $blocking !== [] ? 'revisar' : ($discrepancies !== [] ? 'corregido' : 'ok'),
            'producto'            => $ai['producto'] ?? null,
            'codigo'              => $ai['codigo'] ?? null,
            'presentacion_caja'   => $ai['presentacion_caja'] ?? null,
            'iva_porcentaje'      => $ai['iva_porcentaje'] ?? null,
            'confianza'           => $ai['confianza'] ?? null,
            'producto_sugerido'   => $product?->only(['id', 'name', 'sku', 'barcode', 'price', 'cost']),
            'origen_precio_venta' => $priceSource,
            'calculado'           => $calc,      // ← lo que el frontend debe mostrar
            'discrepancias'       => $discrepancies,
            'alertas'             => $alerts,
        ];
    }

    /**
     * Una consulta por criterio para todos los renglones (evita N+1).
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{codigo: Collection, nombre: Collection}
     */
    private function matchProducts(array $items): array
    {
        $codes = collect($items)->pluck('codigo')->filter()->map(fn ($c) => trim((string) $c))->unique()->values();
        $names = collect($items)->pluck('producto')->filter()->map(fn ($n) => mb_strtolower(trim((string) $n)))->unique()->values();

        $byCode = $codes->isEmpty() ? collect() : Product::query()
            ->where(fn ($q) => $q->whereIn('barcode', $codes)->orWhereIn('sku', $codes))
            ->get(['id', 'name', 'sku', 'barcode', 'price', 'cost']);

        $byName = $names->isEmpty() ? collect() : Product::query()
            ->whereIn(DB::raw('LOWER(name)'), $names)
            ->get(['id', 'name', 'sku', 'barcode', 'price', 'cost']);

        // Índice manual: flatMap() renumera claves enteras y un EAN numérico se perdería.
        $codeIndex = [];
        foreach ($byCode as $product) {
            foreach ([$product->barcode, $product->sku] as $key) {
                if ($key !== null && $key !== '') {
                    $codeIndex[(string) $key] = $product;
                }
            }
        }

        return [
            'codigo' => collect($codeIndex),
            'nombre' => $byName->keyBy(fn ($p) => mb_strtolower($p->name)),
        ];
    }

    /** @param array{codigo: Collection, nombre: Collection} $products */
    private function findProduct(array $ai, array $products): ?Product
    {
        $code = trim((string) ($ai['codigo'] ?? ''));
        $name = mb_strtolower(trim((string) ($ai['producto'] ?? '')));

        return ($code !== '' ? $products['codigo']->get($code) : null)
            ?? ($name !== '' ? $products['nombre']->get($name) : null);
    }

    /** @param array<string, mixed> $header */
    private function matchSupplier(array $header): ?Supplier
    {
        $nit = preg_replace('/\D/', '', explode('-', (string) ($header['nit'] ?? ''))[0]);

        if ($nit !== '' && strlen($nit) >= 6) {
            $found = Supplier::query()->where('tax_id', 'like', $nit . '%')->first(['id', 'name', 'tax_id']);
            if ($found) {
                return $found;
            }
        }

        $name = trim((string) ($header['nombre'] ?? ''));

        return $name === '' ? null : Supplier::query()->where('name', $name)->first(['id', 'name', 'tax_id']);
    }

    private function isDuplicate(int $supplierId, string $invoiceNumber): bool
    {
        return Purchase::query()
            ->where('supplier_id', $supplierId)
            ->where('invoice_number', $invoiceNumber)
            ->where('status', '!=', Purchase::ANULADA)
            ->exists();
    }

    /** Guarda el soporte en disco privado con nombre UUID, segregado por empresa. */
    private function storeImage(UploadedFile $image): string
    {
        $dir  = config('smart_inventory.image.dir') . '/' . (Contexto::empresaId() ?? 'sin-empresa') . '/' . now()->format('Y/m');
        $name = Str::uuid()->toString() . '.' . strtolower($image->guessExtension() ?? 'jpg');

        Storage::disk(config('smart_inventory.image.disk'))->putFileAs($dir, $image, $name);

        return "{$dir}/{$name}";
    }
}
