<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Aritmética contable de la entrada inteligente de inventario.
 *
 * Fuente de verdad de los cálculos caja ↔ unidad y de los márgenes: la IA
 * propone, este servicio decide. Todo el dinero se opera en CENTAVOS
 * enteros para que 0.1 + 0.2 nunca sea 0.30000000000000004 en un kardex.
 *
 * Sin dependencias de framework → se prueba con PHPUnit puro.
 */
class InventoryMathService
{
    public function __construct(private float $tolerance = 0.01) {}

    /**
     * Calcula todas las cifras derivadas de un renglón a partir de sus datos
     * primarios. Precio de venta: manda el unitario; si solo llega el de la
     * caja, el unitario se deriva de él.
     *
     * @param  float       $boxes            cantidad_cajas (> 0)
     * @param  float       $unitsPerBox      cantidad_unidades por caja (> 0)
     * @param  float       $totalCost        costo_total_compra del renglón, antes de IVA
     * @param  float|null  $unitSalePrice    precio_venta_unitario
     * @param  float|null  $boxSalePrice     precio_venta_caja (solo si no hay unitario)
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException si un dato primario es imposible
     */
    public function calculate(
        float $boxes,
        float $unitsPerBox,
        float $totalCost,
        ?float $unitSalePrice = null,
        ?float $boxSalePrice = null,
    ): array {
        if ($boxes <= 0) {
            throw new InvalidArgumentException('cantidad_cajas debe ser mayor que cero.');
        }
        if ($unitsPerBox <= 0) {
            throw new InvalidArgumentException('cantidad_unidades debe ser mayor que cero.');
        }
        if ($totalCost < 0) {
            throw new InvalidArgumentException('costo_total_compra no puede ser negativo.');
        }

        $totalUnits     = round($boxes * $unitsPerBox, 3);
        $totalCostCents = $this->toCents($totalCost);
        $boxCostCents   = $this->divide($totalCostCents, $boxes);
        $unitCostCents  = $this->divide($totalCostCents, $totalUnits);

        // Diferencia que deja redondear el costo unitario a centavos
        // (ej. $20 / 3 = 6.67 → 6.67 × 3 = 20.01). Se informa, no se esconde.
        $roundingCents = (int) round($unitCostCents * $totalUnits) - $totalCostCents;

        $unitSaleCents = match (true) {
            $unitSalePrice !== null => $this->toCents($unitSalePrice),
            $boxSalePrice  !== null => $this->divide($this->toCents($boxSalePrice), $unitsPerBox),
            default                 => null,
        };

        $result = [
            'cantidad_cajas'        => $boxes,
            'cantidad_unidades'     => $unitsPerBox,
            'unidades_totales'      => $totalUnits,
            'costo_total_compra'    => $this->fromCents($totalCostCents),
            'costo_caja'            => $this->fromCents($boxCostCents),
            'costo_unitario'        => $this->fromCents($unitCostCents),
            'ajuste_redondeo_costo' => $this->fromCents($roundingCents),
            'precio_venta_unitario' => null,
            'precio_venta_caja'     => null,
            'precio_venta_total'    => null,
            'margen_ganancia'       => [
                'ganancia_neta_caja' => null,
                'ganancia_unitaria'  => null,
                'ganancia_total'     => null,
                'margen_porcentaje'  => null,
                'markup_porcentaje'  => null,
            ],
            'alertas' => [],
        ];

        if (abs($boxes - round($boxes)) > 0.0001 || abs($unitsPerBox - round($unitsPerBox)) > 0.0001) {
            $result['alertas'][] = 'CANTIDAD_FRACCIONARIA';
        }
        if ($roundingCents !== 0) {
            $result['alertas'][] = 'REDONDEO_COSTO_UNITARIO';
        }

        if ($unitSaleCents === null) {
            $result['alertas'][] = 'SIN_PRECIO_VENTA';

            return $result;
        }

        if ($unitSaleCents < 0) {
            throw new InvalidArgumentException('precio_venta_unitario no puede ser negativo.');
        }

        $boxSaleCents   = (int) round($unitSaleCents * $unitsPerBox);
        $totalSaleCents = (int) round($unitSaleCents * $totalUnits);
        $profitCents    = $totalSaleCents - $totalCostCents;

        $result['precio_venta_unitario'] = $this->fromCents($unitSaleCents);
        $result['precio_venta_caja']     = $this->fromCents($boxSaleCents);
        $result['precio_venta_total']    = $this->fromCents($totalSaleCents);
        $result['margen_ganancia'] = [
            'ganancia_neta_caja' => $this->fromCents($boxSaleCents - $boxCostCents),
            'ganancia_unitaria'  => $this->fromCents($unitSaleCents - $unitCostCents),
            'ganancia_total'     => $this->fromCents($profitCents),
            'margen_porcentaje'  => $this->percent($profitCents, $totalSaleCents),
            'markup_porcentaje'  => $this->percent($profitCents, $totalCostCents),
        ];

        if ($unitSaleCents < $unitCostCents) {
            $result['alertas'][] = 'VENTA_BAJO_COSTO';
        }

        return $result;
    }

    /** Precio unitario sugerido = costo unitario × (1 + markup%). */
    public function suggestUnitPrice(float $unitCost, float $markupPercent): float
    {
        return $this->fromCents((int) round($this->toCents($unitCost) * (1 + $markupPercent / 100)));
    }

    /**
     * Compara las cifras de la IA contra las calculadas y lista las que no
     * coinciden dentro de la tolerancia. Campos nulos en la IA se ignoran.
     *
     * @param  array<string, mixed>  $ai
     * @param  array<string, mixed>  $computed
     * @return list<array{campo:string, valor_ia:float, valor_calculado:float|null}>
     */
    public function discrepancies(array $ai, array $computed): array
    {
        $fields = [
            'unidades_totales', 'costo_caja', 'costo_unitario',
            'precio_venta_caja', 'precio_venta_total',
            'margen_ganancia.ganancia_neta_caja', 'margen_ganancia.ganancia_unitaria',
            'margen_ganancia.ganancia_total', 'margen_ganancia.margen_porcentaje',
            'margen_ganancia.markup_porcentaje',
        ];

        $out = [];
        foreach ($fields as $field) {
            $aiValue  = data_get($ai, $field);
            $ourValue = data_get($computed, $field);

            if (! is_numeric($aiValue)) {
                continue;
            }
            if ($ourValue === null || abs((float) $aiValue - (float) $ourValue) > $this->tolerance) {
                $out[] = ['campo' => $field, 'valor_ia' => (float) $aiValue, 'valor_calculado' => $ourValue];
            }
        }

        return $out;
    }

    /**
     * Cuadre de la factura: suma de renglones vs. pie de factura.
     *
     * @param  list<float>               $lineTotals  costo_total_compra de cada renglón
     * @param  array<string, mixed>|null $footer      {subtotal, descuento, iva, total}
     * @return array{suma_renglones:float, cuadra:bool|null, diferencia:float|null, detalle:string|null}
     */
    public function reconcileInvoice(array $lineTotals, ?array $footer): array
    {
        $sumCents = array_sum(array_map(fn ($v) => $this->toCents((float) $v), $lineTotals));
        $sum      = $this->fromCents($sumCents);
        $subtotal = $footer['subtotal'] ?? null;

        if (! is_numeric($subtotal)) {
            return ['suma_renglones' => $sum, 'cuadra' => null, 'diferencia' => null,
                    'detalle' => 'La factura no muestra subtotal legible; no se pudo cuadrar.'];
        }

        $discountCents = $this->toCents((float) ($footer['descuento'] ?? 0));
        $subtotalCents = $this->toCents((float) $subtotal);
        // Tolerancia: 1 centavo por renglón (redondeos del proveedor), mínimo 1 peso.
        $toleranceCents = max(100, count($lineTotals));

        // Los proveedores imprimen el subtotal antes o después del descuento: se aceptan ambos.
        $diffs = [$sumCents - $subtotalCents, $sumCents - ($subtotalCents - $discountCents)];
        $best  = min(array_map('abs', $diffs));
        $ok    = $best <= $toleranceCents;

        if ($ok && isset($footer['total'], $footer['iva']) && is_numeric($footer['total'])) {
            $expected = $subtotalCents - $discountCents + $this->toCents((float) $footer['iva']);
            $ok = abs($expected - $this->toCents((float) $footer['total'])) <= $toleranceCents
               || abs($subtotalCents + $this->toCents((float) $footer['iva']) - $this->toCents((float) $footer['total'])) <= $toleranceCents;
        }

        return [
            'suma_renglones' => $sum,
            'cuadra'         => $ok,
            'diferencia'     => $this->fromCents($best),
            'detalle'        => $ok ? null : 'La suma de los renglones no coincide con el pie de la factura: puede faltar un renglón o haber un valor mal leído.',
        ];
    }

    // ───────────────────────── helpers de centavos ─────────────────────────

    public function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    public function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    private function divide(int $cents, float $divisor): int
    {
        return (int) round($cents / $divisor);
    }

    private function percent(int $numerator, int $denominator): ?float
    {
        return $denominator === 0 ? null : round($numerator / $denominator * 100, 2);
    }
}
