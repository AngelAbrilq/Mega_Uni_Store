<?php

namespace App\Services;

use App\Support\Contexto;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el sistema quiere avisarle a quien está mirando el panel.
 *
 * Se cachea dos minutos: la campana se pinta en todas las páginas y no
 * tiene sentido consultar el inventario entero en cada clic.
 */
class AlertService
{
    private const CACHE = 'mus.alertas';
    private const SEGUNDOS = 120;

    /**
     * @return array{total:int, criticas:int, items:array<int, array<string,mixed>>}
     */
    public function todas(): array
    {
        return Cache::remember(self::CACHE, self::SEGUNDOS, function () {
            $items = array_merge(
                $this->stock(),
                $this->comprasPendientes(),
                $this->cajasSinCerrar(),
            );

            return [
                'total'    => count($items),
                'criticas' => count(array_filter($items, fn ($a) => $a['nivel'] === 'alta')),
                'items'    => array_slice($items, 0, 12),
            ];
        });
    }

    /** Se llama cuando algo cambió y la campana debe refrescarse. */
    public static function olvidar(): void
    {
        Cache::forget(self::CACHE);
    }

    /** @return array<int, array<string, mixed>> */
    private function stock(): array
    {
        if (! Schema::hasTable('existencias') || ! Contexto::tiendaId()) {
            return [];
        }

        $salida = [];

        $agotados = Product::query()->where('is_active', true)->agotados()->count();

        if ($agotados > 0) {
            $salida[] = [
                'nivel'  => 'alta',
                'icono'  => 'alert',
                'titulo' => $agotados === 1 ? 'Un producto agotado' : "{$agotados} productos agotados",
                'texto'  => 'No se pueden vender hasta que entre mercancía.',
                'url'    => route('products.index', ['estado' => 'activos', 'orden' => 'stock']),
            ];
        }

        $bajos = Product::query()->where('is_active', true)->lowStock()
            ->whereHas('existencia', fn ($e) => $e->where('stock', '>', 0))->count();

        if ($bajos > 0) {
            $salida[] = [
                'nivel'  => 'media',
                'icono'  => 'stock',
                'titulo' => $bajos === 1 ? 'Un producto por debajo del mínimo' : "{$bajos} productos por reponer",
                'texto'  => 'Llegaron a su punto de reorden.',
                'url'    => route('products.index', ['bajo' => 1]),
            ];
        }

        return $salida;
    }

    /** @return array<int, array<string, mixed>> */
    private function comprasPendientes(): array
    {
        if (! Schema::hasTable('purchases')) {
            return [];
        }

        $n = Purchase::query()->where('status', Purchase::BORRADOR)->count();

        if ($n === 0) {
            return [];
        }

        return [[
            'nivel'  => 'media',
            'icono'  => 'truck',
            'titulo' => $n === 1 ? 'Una compra sin recibir' : "{$n} compras sin recibir",
            'texto'  => 'Están en borrador: el inventario todavía no subió.',
            'url'    => route('purchases.index', ['estado' => 'borrador']),
        ]];
    }

    /** @return array<int, array<string, mixed>> */
    private function cajasSinCerrar(): array
    {
        if (! Schema::hasTable('cash_sessions')) {
            return [];
        }

        // Turnos abiertos desde antes de hoy: alguien olvidó cerrar.
        $viejos = CashSession::query()
            ->where('status', CashSession::ABIERTA)
            ->where('opened_at', '<', now()->startOfDay())
            ->count();

        if ($viejos === 0) {
            return [];
        }

        return [[
            'nivel'  => 'alta',
            'icono'  => 'wallet',
            'titulo' => $viejos === 1 ? 'Un turno de caja sin cerrar' : "{$viejos} turnos de caja sin cerrar",
            'texto'  => 'Quedaron abiertos de días anteriores.',
            'url'    => route('cash.index'),
        ]];
    }
}
