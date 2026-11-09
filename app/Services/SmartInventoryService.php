<?php

namespace App\Services;

use App\Models\Existencia;
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
    private const BASE_CURRENCY = 'COP';

    /** Rango en que el costo leído se considera coherente con el del catálogo. */
    private const COST_RATIO_MIN = 0.5;
    private const COST_RATIO_MAX = 2.0;

    public function __construct(
        private InvoiceAIReaderService $reader,
        private InventoryMathService $math,
        private PurchaseService $purchases,
        private LimiteService $limits,
    ) {}

    /**
     * Crea en el catálogo un producto que llegó en la factura y no existía.
     * Nace con stock 0: sus unidades entran cuando se reciba la compra, para
     * que el kardex explique de dónde salió cada una.
     *
     * @param  array{name:string, sku?:?string, barcode?:?string, price:float, cost?:?float}  $data
     */
    public function createProduct(array $data, int $userId): Product
    {
        $this->limits->exigir('productos');   // respeta el tope del plan (LimiteDelPlan → 422 JSON)

        return DB::transaction(function () use ($data, $userId) {
            $product = Product::create([
                'name'       => $data['name'],
                'sku'        => $data['sku'] ?? null,
                'barcode'    => $data['barcode'] ?? null,
                'price'      => (float) $data['price'],
                'cost'       => (float) ($data['cost'] ?? 0),
                'is_active'  => true,
                'is_public'  => false,
                'created_by' => $userId,
            ]);

            Existencia::create([
                'tienda_id'  => Contexto::tiendaId(),
                'product_id' => $product->id,
                'stock'      => 0,
                'min_stock'  => 0,
                'activo'     => true,
            ]);

            return $product;
        });
    }

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

        $currency = $this->normalizeCurrency($extraction['moneda'] ?? null);
        $foreign  = $currency !== self::BASE_CURRENCY;
        $charges  = (float) ($extraction['cargos_no_mercancia']['total'] ?? 0);

        $lines = [];
        foreach ($extraction['items'] as $index => $aiItem) {
            $lines[] = $this->buildLine($index, (array) $aiItem, $products, $markup, $foreign);
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
                $charges,
            ),
            'moneda' => [
                'codigo'     => $currency,
                'extranjera' => $foreign,   // true → el frontend exige TRM antes de confirmar
                'evidencia'  => $foreign ? ($extraction['moneda_evidencia'] ?? null) : null,
            ],
            'cargos_no_mercancia' => [
                'total'   => round($charges, 2),
                'detalle' => $extraction['cargos_no_mercancia']['detalle'] ?? null,
            ],
            'fecha_alerta' => $this->dateWarning($extraction['proveedor']['fecha'] ?? null),
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

        $currency = $this->normalizeCurrency($data['moneda'] ?? null);
        // La factura viene en su moneda; el kardex siempre en pesos. Los
        // precios de venta ya vienen en pesos (son los de la tienda).
        $rate = $currency === self::BASE_CURRENCY ? 1.0 : (float) $data['trm'];

        return DB::transaction(function () use ($data, $userId, $currency, $rate) {
            $purchaseLines = [];
            $priceUpdates  = [];

            foreach ($data['items'] as $i => $item) {
                try {
                    $calc = $this->math->calculate(
                        (float) $item['cantidad_cajas'],
                        (float) $item['cantidad_unidades'],
                        round((float) $item['costo_total_compra'] * $rate, 2),
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
                notas: trim('Ingreso inteligente (IA)'
                    . ($currency !== self::BASE_CURRENCY ? " · factura en {$currency}, TRM " . number_format($rate, 2, ',', '.') : '')
                    . (! empty($data['imagen_ref']) ? ' · soporte ' . $data['imagen_ref'] : '')),
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
    private function buildLine(int $index, array $ai, array $products, float $markup, bool $foreign = false): array
    {
        $product = $this->findProduct($ai, $products);
        $alerts  = ! empty($ai['cantidad_inferida']) ? ['CANTIDAD_DEDUCIDA'] : [];

        // Origen del precio de venta: factura (PVP impreso) → precio actual del producto → sugerido por markup.
        // En moneda extranjera no se puede comparar un costo en USD con un precio
        // en pesos: el precio se calcula en el navegador cuando el usuario da la TRM.
        // El precio del catálogo ya está en pesos: sirve en cualquier moneda.
        [$unitSale, $priceSource] = match (true) {
            $product && (float) $product->price > 0 && ($foreign || ! is_numeric($ai['precio_venta_unitario'] ?? null))
                                                             => [(float) $product->price, 'producto'],
            $foreign                                         => [null, 'pendiente_trm'],
            is_numeric($ai['precio_venta_unitario'] ?? null) => [(float) $ai['precio_venta_unitario'], 'factura'],
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

            if ($unitSale === null && ! $foreign) {
                $base     = $this->math->calculate($boxes, $upb, $total);
                $unitSale = $this->math->suggestUnitPrice($base['costo_unitario'], $markup);
            }

            // En moneda extranjera los márgenes se calculan en el navegador con
            // la TRM: comparar un costo en USD con un precio en pesos no tiene sentido.
            $calc = $this->math->calculate($boxes, $upb, $total, $foreign ? null : $unitSale);
        } catch (InvalidArgumentException $e) {
            return [
                'indice' => $index, 'estado' => 'incompleto', 'error' => $e->getMessage(),
                'ia' => $ai, 'calculado' => null, 'discrepancias' => [], 'alertas' => ['DATOS_INCOMPLETOS'],
                'producto_sugerido' => $product?->only(['id', 'name', 'sku', 'barcode', 'price', 'cost']),
                'origen_precio_venta' => null,
            ];
        }

        $discrepancies = $this->math->discrepancies($ai, $calc);

        // Cordura contra el catálogo: un costo unitario que es menos de la
        // mitad o más del doble del actual casi siempre es una lectura mala
        // (unidades por caja mal leídas, un cero de más…), no un cambio de precio.
        $catalogCost = $product ? (float) $product->cost : 0.0;
        if ($catalogCost > 0 && ! $foreign) {
            $ratio = $calc['costo_unitario'] / $catalogCost;
            if ($ratio < self::COST_RATIO_MIN || $ratio > self::COST_RATIO_MAX) {
                $alerts[] = 'COSTO_ATIPICO';
            }
        }
        $alerts = array_values(array_unique(array_merge(
            $alerts,
            array_diff($calc['alertas'], ['SIN_PRECIO_VENTA']),
            $product ? [] : ['PRODUCTO_NO_ENCONTRADO'],
            ((float) ($ai['confianza'] ?? 0)) < (float) config('smart_inventory.min_confidence') ? ['BAJA_CONFIANZA'] : [],
            $priceSource === 'sugerido' ? ['PRECIO_SUGERIDO'] : [],
        )));
        unset($calc['alertas']);

        $blocking = array_intersect($alerts, ['PRODUCTO_NO_ENCONTRADO', 'BAJA_CONFIANZA', 'LECTURA_INCONSISTENTE', 'VENTA_BAJO_COSTO', 'CANTIDAD_DEDUCIDA', 'COSTO_ATIPICO']);

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
            'precio_venta_sugerido' => $unitSale,   // siempre en pesos
            'cantidad_inferida'   => ! empty($ai['cantidad_inferida']),
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

    /** Código ISO de 3 letras; cualquier cosa ilegible se toma como la moneda base. */
    private function normalizeCurrency(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z]{3}$/', $code) ? $code : self::BASE_CURRENCY;
    }

    /** Aviso cuando la fecha leída no sirve y la pantalla tendrá que usar la de hoy. */
    private function dateWarning(?string $date): ?string
    {
        if (! $date || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            return 'No se pudo leer la fecha de la factura: se usará la de hoy. Verifícala.';
        }
        if ($date > now()->toDateString()) {
            return "La factura tiene fecha futura ({$date}): se usará la de hoy. Verifícala.";
        }

        return null;
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
