<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerTienda;
use App\Models\LoyaltyTransaction;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Setting;

/**
 * Clientes por local y puntos de fidelidad (CL-TDC-001 · CL-PUN-001 · RN-38).
 *
 *   · 1 punto por cada «venta.pesos_por_punto» del total (por defecto $1.000).
 *   · Los puntos, el total gastado y las compras son POR LOCAL.
 *   · Sin cliente identificado no hay puntos.
 *   · Anular devuelve todo lo de esa venta; una devolución aprobada devuelve
 *     la parte proporcional de los puntos que ganó la venta.
 *   · Los puntos que ganó la venta se leen del movimiento, no se recalculan:
 *     si mañana cambia la regla, la reversa sigue cuadrando.
 */
class FidelizacionService
{
    public static function activa(): bool
    {
        return Setting::activo('venta.puntos_activos');
    }

    public static function pesosPorPunto(): float
    {
        return max(1, (float) Setting::obtener('venta.pesos_por_punto', '1000'));
    }

    /** Asocia el cliente al local; si ya estaba, lo reactiva. Nunca duplica. */
    public function asociar(Customer $cliente, int $tiendaId): CustomerTienda
    {
        $fila = CustomerTienda::withoutGlobalScopes()->firstOrCreate(
            ['customer_id' => $cliente->id, 'tienda_id' => $tiendaId],
            ['is_active' => true],
        );

        if (! $fila->is_active) {
            $fila->forceFill(['is_active' => true])->save();
        }

        return $fila;
    }

    public function desasociar(Customer $cliente, int $tiendaId): void
    {
        CustomerTienda::withoutGlobalScopes()
            ->where(['customer_id' => $cliente->id, 'tienda_id' => $tiendaId])
            ->update(['is_active' => false]);
    }

    /** Acumula los puntos de una venta pagada. Devuelve los puntos ganados. */
    public function acumular(Sale $venta, ?int $userId): int
    {
        if (! $venta->customer_id || (float) $venta->total <= 0) {
            return 0;
        }

        $cliente = Customer::withTrashed()->find($venta->customer_id);
        $fila    = $this->bloqueada($this->asociar($cliente, $venta->tienda_id));
        $puntos  = self::activa() ? (int) floor((float) $venta->total / self::pesosPorPunto()) : 0;

        $fila->forceFill([
            'points_balance'  => $fila->points_balance + $puntos,
            'total_spent'     => (float) $fila->total_spent + (float) $venta->total,
            'purchases_count' => $fila->purchases_count + 1,
        ])->save();

        if ($puntos > 0) {
            $this->movimiento($fila, LoyaltyTransaction::GANADOS, $puntos, $userId, $venta, null, 'Compra ' . $venta->number);
        }

        return $puntos;
    }

    /** Al anular: sale todo lo que la venta sumó (lo que quede sin devolver). */
    public function revertirVenta(Sale $venta, ?int $userId): void
    {
        if (! $venta->customer_id) {
            return;
        }

        $fila = $this->filaDe($venta);

        if (! $fila) {
            return;
        }

        $puntos = $this->puntosVigentes($venta);

        $fila->forceFill([
            'points_balance'  => $fila->points_balance - $puntos,
            'total_spent'     => max(0, (float) $fila->total_spent - (float) $venta->total),
            'purchases_count' => max(0, $fila->purchases_count - 1),
        ])->save();

        if ($puntos > 0) {
            $this->movimiento($fila, LoyaltyTransaction::REVERSA, -$puntos, $userId, $venta, null, 'Anulación de ' . $venta->number);
        }
    }

    /** Devolución aprobada: la parte proporcional de lo ganado y de lo gastado. */
    public function revertirDevolucion(SaleReturn $devolucion, Sale $venta, ?int $userId): void
    {
        if (! $venta->customer_id || (float) $venta->total <= 0) {
            return;
        }

        $fila = $this->filaDe($venta);

        if (! $fila) {
            return;
        }

        $ganados = (int) LoyaltyTransaction::withoutGlobalScopes()
            ->where('sale_id', $venta->id)->where('type', LoyaltyTransaction::GANADOS)->sum('points');
        $puntos  = min(
            $this->puntosVigentes($venta),
            (int) floor($ganados * (float) $devolucion->total / (float) $venta->total),
        );

        $fila->forceFill([
            'points_balance' => $fila->points_balance - $puntos,
            'total_spent'    => max(0, (float) $fila->total_spent - (float) $devolucion->total),
        ])->save();

        if ($puntos > 0) {
            $this->movimiento($fila, LoyaltyTransaction::REVERSA, -$puntos, $userId, $venta, $devolucion, 'Devolución ' . $devolucion->number);
        }
    }

    /** Lo que la venta ganó menos lo que ya se le devolvió. */
    private function puntosVigentes(Sale $venta): int
    {
        return max(0, (int) LoyaltyTransaction::withoutGlobalScopes()->where('sale_id', $venta->id)->sum('points'));
    }

    private function filaDe(Sale $venta): ?CustomerTienda
    {
        $fila = CustomerTienda::withoutGlobalScopes()
            ->where(['customer_id' => $venta->customer_id, 'tienda_id' => $venta->tienda_id])
            ->first();

        return $fila ? $this->bloqueada($fila) : null;
    }

    private function bloqueada(CustomerTienda $fila): CustomerTienda
    {
        return CustomerTienda::withoutGlobalScopes()->whereKey($fila->id)->lockForUpdate()->firstOrFail();
    }

    private function movimiento(CustomerTienda $fila, string $tipo, int $puntos, ?int $userId, ?Sale $venta, ?SaleReturn $devolucion, string $nota): void
    {
        LoyaltyTransaction::create([
            'customer_id'    => $fila->customer_id,
            'tienda_id'      => $fila->tienda_id,
            'sale_id'        => $venta?->id,
            'sale_return_id' => $devolucion?->id,
            'user_id'        => $userId,
            'type'           => $tipo,
            'points'         => $puntos,
            'balance_after'  => $fila->points_balance,
            'notes'          => $nota,
        ]);
    }
}
