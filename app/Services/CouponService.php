<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Sale;
use Illuminate\Validation\ValidationException;

/**
 * Aplicar cupones a una venta (VD-CUP-001 · TBL-VCU-001 · RN-35).
 *
 * El navegador solo propone el código: el descuento lo calcula siempre el
 * servidor, con el cupón bloqueado, para que dos cajas no gasten el último
 * uso al mismo tiempo.
 */
class CouponService
{
    /**
     * Vista previa para el POS: valida y calcula sin gastar un uso.
     *
     * @return array{cupon: Coupon, descuento: float}
     * @throws ValidationException
     */
    public function evaluar(string $codigo, float $base): array
    {
        $cupon = $this->buscar($codigo, bloquear: false);

        return ['cupon' => $cupon, 'descuento' => $this->validar($cupon, $base)];
    }

    /**
     * Dentro de la transacción de la venta: bloquea, valida y gasta un uso.
     *
     * @return array{cupon: Coupon, descuento: float}
     * @throws ValidationException
     */
    public function reservar(string $codigo, float $base): array
    {
        $cupon     = $this->buscar($codigo, bloquear: true);
        $descuento = $this->validar($cupon, $base);

        $cupon->increment('used_count');

        return ['cupon' => $cupon, 'descuento' => $descuento];
    }

    /**
     * Reparte el descuento entre los renglones en proporción a su valor.
     * El último renglón con valor se lleva el centavo que sobre del redondeo:
     * la suma de las partes es exactamente el descuento.
     *
     * @param  array<int, float>  $bases
     * @return array<int, float>
     */
    public function repartir(float $descuento, array $bases): array
    {
        $total = array_sum($bases);

        if ($descuento <= 0 || $total <= 0) {
            return array_fill_keys(array_keys($bases), 0.0);
        }

        $partes   = [];
        $asignado = 0.0;
        $ultimo   = null;

        foreach ($bases as $i => $base) {
            $partes[$i] = round($descuento * $base / $total, 2);
            $asignado  += $partes[$i];

            if ($base > 0) {
                $ultimo = $i;
            }
        }

        $partes[$ultimo] = round($partes[$ultimo] + ($descuento - $asignado), 2);

        return $partes;
    }

    /** Al anular una venta, sus cupones recuperan el uso (RN-35). */
    public function liberar(Sale $venta): void
    {
        foreach ($venta->coupons()->with('coupon')->get() as $uso) {
            if ($uso->coupon && $uso->coupon->used_count > 0) {
                $uso->coupon->decrement('used_count');
            }
        }
    }

    private function buscar(string $codigo, bool $bloquear): Coupon
    {
        $consulta = Coupon::withTrashed()->where('code', Coupon::normalizar($codigo));

        $cupon = ($bloquear ? $consulta->lockForUpdate() : $consulta)->first();

        if (! $cupon) {
            throw ValidationException::withMessages(['cupon' => 'El cupón «' . Coupon::normalizar($codigo) . '» no existe.']);
        }

        return $cupon;
    }

    private function validar(Coupon $cupon, float $base): float
    {
        if ($motivo = $cupon->motivoParaNoAplicar($base)) {
            throw ValidationException::withMessages(['cupon' => $motivo]);
        }

        return $cupon->calcularDescuento($base);
    }
}
