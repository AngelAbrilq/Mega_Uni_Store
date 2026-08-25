<?php

namespace Database\Seeders;

use App\Models\Existencia;
use App\Support\Contexto;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Movimiento de demostración: turnos de caja, ventas repartidas sobre los
 * últimos 60 días y una compra recibida.
 *
 * No usa SaleService a propósito: aquí se escriben fechas del pasado y
 * saldos calculados, cosa que el servicio (correctamente) no permite.
 * Es solo para tener datos con los que probar el panel y los reportes.
 */
class DemoSeeder extends Seeder
{
    /* Sin eventos: sembrar no debe llenar la bitácora de auditoría. */
    use WithoutModelEvents;

    /** Semilla fija: correr el seeder dos veces da los mismos datos. */
    private int $semilla = 20260819;

    private function azar(int $min, int $max): int
    {
        // Generador propio y determinista (mt_rand con semilla cambia entre versiones de PHP).
        $this->semilla = ($this->semilla * 1103515245 + 12345) & 0x7FFFFFFF;

        return $min + (int) ($this->semilla % max(1, $max - $min + 1));
    }

    public function run(): void
    {
        $vendedores = User::role(['Vendedor', 'Cajero', 'Administrador'])->get();

        if ($vendedores->isEmpty()) {
            $vendedores = User::query()->take(3)->get();
        }

        if ($vendedores->isEmpty() || Product::count() === 0) {
            $this->command?->warn('  DemoSeeder: faltan usuarios o productos. Se omite.');

            return;
        }

        $productos = Product::query()->where('is_active', true)->where('price', '>', 0)->get();
        $clientes  = Customer::query()->pluck('id')->all();
        $medios    = PaymentMethod::query()->where('is_active', true)->get();
        $efectivo  = $medios->firstWhere('name', 'Efectivo') ?? $medios->first();

        if ($productos->isEmpty() || $medios->isEmpty()) {
            $this->command?->warn('  DemoSeeder: faltan productos o medios de pago. Se omite.');

            return;
        }

        $this->compraDemo($vendedores->first());

        $ventas = 0;
        $turnos = 0;

        // 60 días hacia atrás, un turno por día con varias ventas.
        for ($dia = 59; $dia >= 0; $dia--) {
            $fecha = Carbon::today()->subDays($dia);

            // Domingo cerrado.
            if ($fecha->isSunday()) {
                continue;
            }

            $vendedor = $vendedores[$this->azar(0, $vendedores->count() - 1)];

            $turno = CashSession::create([
                'user_id'        => $vendedor->id,
                'opening_amount' => 200000,
                'status'         => CashSession::CERRADA,
                'opened_at'      => $fecha->copy()->setTime(7, 30),
                'closed_at'      => $fecha->copy()->setTime(18, 0),
            ]);
            $turnos++;

            // Más movimiento entre semana; menos los sábados.
            $cuantas = $fecha->isSaturday() ? $this->azar(3, 7) : $this->azar(6, 14);

            $efectivoDia = 0.0;

            for ($v = 0; $v < $cuantas; $v++) {
                $hora   = $this->azar(8, 17);
                $minuto = $this->azar(0, 59);
                $cuando = $fecha->copy()->setTime($hora, $minuto);

                $venta = $this->venta($turno, $vendedor, $cuando, $productos, $clientes, $medios, $efectivo);

                if ($venta) {
                    $ventas++;
                    $efectivoDia += $venta['efectivo'];
                }
            }

            $esperado = 200000 + $efectivoDia;
            // Una de cada seis cajas queda descuadrada: es lo que pasa en la vida real.
            $descuadre = $this->azar(1, 6) === 1 ? $this->azar(-4000, 3000) : 0;

            $turno->forceFill([
                'expected_amount' => round($esperado, 2),
                'counted_amount'  => round($esperado + $descuadre, 2),
                'difference'      => $descuadre,
                'closed_by'       => $vendedor->id,
            ])->save();
        }

        // Turno de hoy, abierto, para poder vender de una vez.
        CashSession::create([
            'user_id'        => $vendedores->first()->id,
            'opening_amount' => 200000,
            'status'         => CashSession::ABIERTA,
            'opened_at'      => now()->setTime(7, 30),
        ]);

        $this->command?->info("  Demo: {$ventas} ventas en {$turnos} turnos de caja");
    }

    /**
     * @return array{efectivo: float}|null
     */
    private function venta(CashSession $turno, User $vendedor, Carbon $cuando, $productos, array $clientes, $medios, $efectivo): ?array
    {
        // El local donde ocurre todo esto. Los datos de demostración viven
        // en la tienda principal, igual que el resto de la instalación.
        $tiendaId = Contexto::tiendaId();

        $cuantosItems = $this->azar(1, 4);
        $lineas = [];

        for ($i = 0; $i < $cuantosItems; $i++) {
            $p = $productos[$this->azar(0, $productos->count() - 1)];

            if (isset($lineas[$p->id])) {
                continue;
            }

            $lineas[$p->id] = [
                'producto' => $p,
                'cantidad' => (float) $this->azar(1, (int) $p->price > 40000 ? 2 : 5),
            ];
        }

        if (empty($lineas)) {
            return null;
        }

        return DB::transaction(function () use ($turno, $vendedor, $cuando, $lineas, $clientes, $medios, $efectivo) {

            $venta = new Sale([
                'number'          => Sale::siguienteNumero(),
                'customer_id'     => $clientes && $this->azar(1, 3) > 1 ? $clientes[$this->azar(0, count($clientes) - 1)] : null,
                'user_id'         => $vendedor->id,
                'cash_session_id' => $turno->id,
                'status'          => Sale::PAGADA,
                'sold_at'         => $cuando,
            ]);
            $venta->timestamps = false;
            $venta->created_at = $cuando;
            $venta->updated_at = $cuando;
            $venta->save();

            $sub = 0.0; $imp = 0.0; $cos = 0.0;

            foreach ($lineas as $l) {
                $p    = $l['producto'];
                $cant = $l['cantidad'];

                $precio  = (float) $p->price;
                $lineaSub = round($precio * $cant, 2);

                $tasa = 0.0; $impLinea = 0.0;
                if ($p->tax) {
                    if ($p->tax->type === 'fixed') {
                        $impLinea = round((float) $p->tax->rate * $cant, 2);
                    } else {
                        $tasa = (float) $p->tax->rate;
                        $impLinea = round($lineaSub * $tasa / 100, 2);
                    }
                }

                SaleItem::create([
                    'sale_id'    => $venta->id,
                    'product_id' => $p->id,
                    'name'       => $p->name,
                    'sku'        => $p->sku,
                    'quantity'   => $cant,
                    'unit_price' => $precio,
                    'unit_cost'  => (float) $p->cost,
                    'discount'   => 0,
                    'tax_name'   => $p->tax?->name,
                    'tax_rate'   => $tasa,
                    'tax_amount' => $impLinea,
                    'subtotal'   => $lineaSub,
                    'total'      => round($lineaSub + $impLinea, 2),
                ]);

                $sub += $lineaSub;
                $imp += $impLinea;
                $cos += (float) $p->cost * $cant;

                // Se descuenta la existencia del local y se deja el rastro
                // en el kardex. No se usa StockService a propósito: aquí hay
                // que poder fechar los movimientos hacia atrás para que los
                // informes tengan historia, y el servicio siempre escribe
                // con la fecha de hoy.
                $existencia = Existencia::firstOrCreate(
                    ['tienda_id' => $tiendaId, 'product_id' => $p->id],
                    ['stock' => 0, 'min_stock' => 0, 'activo' => true]
                );

                $saldo = max(0, (float) $existencia->stock - $cant);
                $existencia->forceFill(['stock' => $saldo])->save();

                $mov = new StockMovement([
                    'tienda_id'     => $tiendaId,
                    'product_id'    => $p->id,
                    'type'          => StockMovement::SALIDA,
                    'reason'        => 'venta',
                    'quantity'      => -$cant,
                    'balance_after' => $saldo,
                    'unit_cost'     => (float) $p->cost,
                    'source_type'   => Sale::class,
                    'source_id'     => $venta->id,
                    'user_id'       => $vendedor->id,
                    'notes'         => 'Venta ' . $venta->number,
                ]);
                $mov->timestamps = false;
                $mov->created_at = $cuando;
                $mov->updated_at = $cuando;
                $mov->save();
            }

            $total = round($sub + $imp, 2);

            // Dos de cada tres ventas se pagan en efectivo.
            $enEfectivo = $this->azar(1, 3) > 1;
            $medio = $enEfectivo ? $efectivo : $medios[$this->azar(0, $medios->count() - 1)];
            $esEfectivo = str_contains(mb_strtolower($medio->name), 'efectivo');

            $recibido = $esEfectivo
                ? (float) (ceil($total / 1000) * 1000)   // se paga con billete redondo
                : $total;

            SalePayment::create([
                'sale_id'           => $venta->id,
                'payment_method_id' => $medio->id,
                'method_name'       => $medio->name,
                'amount'            => $recibido,
            ]);

            $venta->forceFill([
                'subtotal'      => round($sub, 2),
                'tax_total'     => round($imp, 2),
                'total'         => $total,
                'paid_total'    => $recibido,
                'change_amount' => round(max(0, $recibido - $total), 2),
                'cost_total'    => round($cos, 2),
                'profit_total'  => round($sub - $cos, 2),
            ])->save();

            return ['efectivo' => $esEfectivo ? $recibido : 0.0];
        });
    }

    /** Una compra ya recibida, para que el módulo no aparezca vacío. */
    private function compraDemo(User $usuario): void
    {
        $proveedor = Supplier::query()->where('is_active', true)->first();

        if (! $proveedor) {
            return;
        }

        $productos = Product::query()->take(4)->get();

        if ($productos->isEmpty()) {
            return;
        }

        $compra = Purchase::create([
            'number'         => Purchase::siguienteNumero(),
            'invoice_number' => 'FV-24188',
            'supplier_id'    => $proveedor->id,
            'user_id'        => $usuario->id,
            'status'         => Purchase::BORRADOR,
            'ordered_at'     => now()->subDays(3)->toDateString(),
        ]);

        $sub = 0.0; $imp = 0.0;

        foreach ($productos as $p) {
            $cant  = (float) $this->azar(20, 80);
            $costo = (float) $p->cost;
            $ls    = round($costo * $cant, 2);
            $li    = round($ls * 0.19, 2);

            $compra->items()->create([
                'product_id'  => $p->id,
                'name'        => $p->name,
                'quantity'    => $cant,
                'unit_cost'   => $costo,
                'tax_rate'    => 19,
                'tax_amount'  => $li,
                'subtotal'    => $ls,
                'total'       => round($ls + $li, 2),
                'update_cost' => true,
            ]);

            $sub += $ls;
            $imp += $li;
        }

        $compra->forceFill([
            'subtotal'  => round($sub, 2),
            'tax_total' => round($imp, 2),
            'total'     => round($sub + $imp, 2),
        ])->save();
    }
}
