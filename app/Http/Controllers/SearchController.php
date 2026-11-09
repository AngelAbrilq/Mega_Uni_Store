<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Búsqueda global que alimenta la paleta de comandos (Ctrl + K).
 *
 * Devuelve JSON, no una vista: la paleta la consume por fetch mientras
 * el usuario escribe. Solo busca en lo que ese usuario puede ver.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['items' => []]);
        }

        $usuario = $request->user();
        $items   = [];

        /* ─────────── Productos ─────────── */
        if ($usuario->can('productos.ver')) {
            foreach (Product::query()->search($q)->orderBy('name')->take(6)->get() as $p) {
                $items[] = [
                    'g' => 'Productos',
                    't' => $p->name,
                    's' => trim(($p->sku ? $p->sku . ' · ' : '')
                          . '$' . number_format((float) $p->price, 0, ',', '.')
                          . ' · ' . (int) $p->stock . ' en bodega'),
                    'i' => 'box',
                    'u' => route('products.show', $p),
                ];
            }
        }

        /* ─────────── Clientes ─────────── */
        if ($usuario->can('clientes.ver')) {
            foreach (Customer::query()->search($q)->orderBy('first_name')->take(5)->get() as $c) {
                $items[] = [
                    'g' => 'Clientes',
                    't' => $c->full_name,
                    's' => $c->document ?: ($c->email ?: ($c->phone ?: 'Sin datos de contacto')),
                    'i' => 'users',
                    'u' => route('customers.show', $c),
                ];
            }
        }

        /* ─────────── Ventas ─────────── */
        if ($usuario->can('ventas.ver')) {
            foreach (Sale::query()->with('customer:id,first_name,last_name')
                         ->search($q)->latest('sold_at')->take(5)->get() as $v) {
                $items[] = [
                    'g' => 'Ventas',
                    't' => $v->number . ' · $' . number_format((float) $v->total, 0, ',', '.'),
                    's' => ($v->customer?->full_name ?? 'Consumidor final')
                          . ' · ' . ($v->sold_at?->format('d/m/Y H:i') ?? '')
                          . ($v->status === Sale::ANULADA ? ' · anulada' : ''),
                    'i' => 'money',
                    'u' => route('sales.show', $v),
                ];
            }
        }

        /* ─────────── Compras ─────────── */
        if ($usuario->can('compras.ver')) {
            foreach (Purchase::query()->with('supplier:id,name')
                         ->search($q)->latest('ordered_at')->take(4)->get() as $c) {
                $items[] = [
                    'g' => 'Compras',
                    't' => $c->number . ' · $' . number_format((float) $c->total, 0, ',', '.'),
                    's' => ($c->supplier->name ?? '—') . ' · ' . $c->estado_label,
                    'i' => 'truck',
                    'u' => route('purchases.show', $c),
                ];
            }
        }

        return response()->json(['items' => $items]);
    }
}
