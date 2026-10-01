<?php

namespace App\Http\Controllers;

use App\Exceptions\InvoiceReadingException;
use App\Http\Requests\SmartInventory\AnalyzeInvoiceRequest;
use App\Http\Requests\SmartInventory\ConfirmSmartEntryRequest;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\SmartInventoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Smart Inventory Entry: foto de factura → tabla de validación → compra en borrador.
 *
 * Respuesta estándar: {success, data, message} (+ error_code en fallas de IA).
 */
class SmartInventoryController extends Controller
{
    public function __construct(private SmartInventoryService $smartInventory) {}

    /**
     * GET /smart-inventory
     * Pantalla de captura + tabla de validación. Los catálogos viajan una
     * sola vez para armar los selectores de proveedor y producto.
     */
    public function create(): View
    {
        return view('smart-inventory.create', [
            'proveedores' => Supplier::active()->orderBy('name')->get(['id', 'name', 'tax_id']),
            // without('existencia'): aquí no se muestra stock, sobra la relación del $with.
            'productos'   => Product::without('existencia')->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'sku', 'barcode', 'price', 'cost'])
                ->map(fn (Product $p) => [
                    'id'     => $p->id,
                    'nombre' => $p->name,
                    'sku'    => $p->sku,
                    'precio' => (float) $p->price,
                    'costo'  => (float) $p->cost,
                ])->values(),
            'markup'      => (float) config('smart_inventory.default_markup_percent'),
            'puedePrecio' => (bool) auth()->user()?->can('productos.editar'),
        ]);
    }

    /**
     * POST /smart-inventory/analizar
     * Lee la factura y devuelve la tabla de validación. NO escribe en la BD de negocio.
     */
    public function analyze(AnalyzeInvoiceRequest $request): JsonResponse
    {
        try {
            $data = $this->smartInventory->analyze(
                $request->file('imagen'),
                $request->filled('markup_porcentaje') ? (float) $request->input('markup_porcentaje') : null,
            );
        } catch (InvoiceReadingException $e) {
            Log::info('smart_inventory.rejected', ['code' => $e->errorCode, 'user_id' => $request->user()->id]);

            return response()->json([
                'success'    => false,
                'data'       => null,
                'error_code' => $e->errorCode,
                'message'    => $e->getMessage(),
            ], $e->status());
        }

        $pending = $data['resumen']['requieren_revision'];

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => $pending > 0
                ? "Factura leída. {$pending} renglón(es) requieren tu revisión antes de confirmar."
                : 'Factura leída. Verifica los valores y confirma.',
        ]);
    }

    /**
     * POST /smart-inventory/confirmar
     * Recalcula en backend lo que el usuario validó y crea la compra en BORRADOR.
     * El stock entra cuando la compra se recibe (purchases.recibir).
     */
    public function confirm(ConfirmSmartEntryRequest $request): JsonResponse
    {
        try {
            $purchase = $this->smartInventory->confirm($request->validated(), $request->user()->id);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data'    => ['errors' => $e->errors()],
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'purchase_id' => $purchase->id,
                'number'      => $purchase->number,
                'status'      => $purchase->status,
                'total'       => (float) $purchase->total,
                'url'         => route('purchases.show', $purchase),
            ],
            'message' => "Compra {$purchase->number} creada en borrador. Recíbela para dar entrada al inventario.",
        ], 201);
    }
}
