<?php

namespace App\Services;

use App\Support\Contexto;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el sistema quiere avisarle a quien está mirando el panel.
 *
 * Se cachea dos minutos: la campana se pinta en todas las páginas y no
 * tiene sentido consultar el inventario entero en cada clic.
 *
 * ── H-01: la clave lleva empresa Y local ──
 *
 * Antes la clave era una sola (`mus.alertas`) para todo el sistema: durante
 * 120 s cualquier negocio veía la campana del primero que la pidió —stock,
 * compras y cajas de OTRO cliente—. El filtro de los modelos estaba bien;
 * la fuga era la caché, que guardaba el resultado ya filtrado y se lo
 * servía a todos.
 *
 * Va el local además de la empresa porque el stock se cuenta por
 * existencias del local activo: dos locales del mismo negocio tampoco
 * deben compartir campana.
 */
class AlertService
{
    private const PREFIJO = 'mus.alertas';
    private const SEGUNDOS = 120;

    /**
     * @return array{total:int, criticas:int, items:array<int, array<string,mixed>>}
     */
    public function todas(): array
    {
        $clave = self::clave(Contexto::empresaId(), Contexto::tiendaId());

        return Cache::remember($clave, self::SEGUNDOS, function () {
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

    /**
     * Se llama cuando algo cambió y la campana debe refrescarse.
     *
     * Borra la campana de TODOS los locales de la empresa, no solo la del
     * activo: una compra recibida en el local norte cambia el contador de
     * «compras sin recibir» que ve el dueño parado en el local centro.
     *
     * Sin contexto (consola, colas) no se sabe de quién era el cambio, así
     * que se borran todas. Son claves pequeñas y es raro: mejor una campana
     * recalculada de más que una desactualizada.
     *
     * Se lee `tiendas` con DB y no con el modelo para no depender de ningún
     * filtro: aquí se necesitan los ids tal cual, y nada más.
     */
    public static function olvidar(?int $empresaId = null): void
    {
        $empresaId ??= Contexto::empresaId();

        $consulta = DB::table('tiendas')->select(['id', 'empresa_id']);

        if ($empresaId !== null) {
            $consulta->where('empresa_id', $empresaId);
        }

        foreach ($consulta->get() as $tienda) {
            Cache::forget(self::clave((int) $tienda->empresa_id, (int) $tienda->id));
            Cache::forget(self::clave((int) $tienda->empresa_id, null));
        }

        // La de «sin contexto», que usan consola y las instalaciones viejas.
        Cache::forget(self::clave($empresaId, null));
    }

    /** `mus.alertas.{empresa}.{tienda}` — 0 cuando no hay. */
    private static function clave(?int $empresaId, ?int $tiendaId): string
    {
        return self::PREFIJO . '.' . ($empresaId ?? 0) . '.' . ($tiendaId ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    private function stock(): array
    {
        if (! Schema::hasTable('existencias') || ! Contexto::tiendaId()) {
            return [];
        }

        // H-F02 · Configuración › Inventario › «Alertas de stock».
        if (! \App\Models\Setting::activo('inventario.alerta_activa')) {
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
