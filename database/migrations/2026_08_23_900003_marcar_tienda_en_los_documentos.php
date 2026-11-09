<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 4 · Fase 1 — Los documentos pasan a ocurrir en un local.
 *
 * Una venta no le pasa «al negocio»: le pasa a un mostrador concreto, con
 * una caja concreta y un cajero concreto. Lo mismo la compra que se
 * recibe, el turno que se abre y cada movimiento del kardex.
 *
 * ── El choque de números que esto evita ──
 *
 * `sales.number` es único en toda la tabla. El día que la ferretería del
 * norte haga su primera venta, el sistema va a intentar guardar
 * «V-000001» — que ya existe, porque el local del centro lo usó hace
 * meses— y va a fallar. El único pasa a ser por tienda, y cada local
 * lleva su propia serie con su prefijo.
 *
 * No se toca `sale_items`, `sale_payments` ni `purchase_items`: esos
 * cuelgan de su documento y heredan la tienda por ahí. Ponerles la
 * columna sería guardar dos veces la misma verdad, con el riesgo de que
 * algún día no coincidan.
 */
return new class extends Migration
{
    /** Documentos que ocurren en un local. */
    private const TABLAS = [
        'sales',
        'purchases',
        'sale_returns',
        'cash_sessions',
        'stock_movements',
    ];

    /** Consecutivos que dejan de ser únicos globalmente. */
    private const NUMEROS = [
        'sales'        => 'sales_number_unique',
        'purchases'    => 'purchases_number_unique',
        'sale_returns' => 'sale_returns_number_unique',
    ];

    public function up(): void
    {
        $tiendaId = (int) (DB::table('tiendas')->min('id') ?: 1);

        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'tienda_id')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $t) {
                $t->unsignedBigInteger('tienda_id')->nullable()->after('id');
            });

            DB::table($tabla)->whereNull('tienda_id')->update(['tienda_id' => $tiendaId]);

            Schema::table($tabla, function (Blueprint $t) {
                $t->unsignedBigInteger('tienda_id')->nullable(false)->change();
                $t->foreign('tienda_id')->references('id')->on('tiendas')->cascadeOnDelete();
                $t->index('tienda_id');
            });
        }

        /* Índices compuestos para las consultas que de verdad se hacen:
           «las ventas de ESTE local en ESTE mes». Sin ellos, cada informe
           recorre la tabla entera y filtra después. */
        $this->indicePorFecha('sales', 'sold_at');
        $this->indicePorFecha('purchases', 'purchased_at');
        $this->indicePorFecha('sale_returns', 'returned_at');
        $this->indicePorFecha('stock_movements', 'created_at');

        $this->numerosPorTienda();
    }

    private function indicePorFecha(string $tabla, string $columna): void
    {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
            return;
        }

        try {
            Schema::table($tabla, function (Blueprint $t) use ($columna) {
                $t->index(['tienda_id', $columna]);
            });
        } catch (\Throwable $e) {
            // Ya existía de una corrida anterior.
        }
    }

    /**
     * «V-000001» deja de ser único en el mundo y pasa a serlo dentro del
     * local. Dos tiendas pueden tener cada una su V-000001 — que es
     * exactamente como funcionan los talonarios de la vida real.
     */
    private function numerosPorTienda(): void
    {
        foreach (self::NUMEROS as $tabla => $nombreViejo) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'number')) {
                continue;
            }

            try {
                Schema::table($tabla, function (Blueprint $t) use ($nombreViejo) {
                    $t->dropUnique($nombreViejo);
                });
            } catch (\Throwable $e) {
                // No estaba con ese nombre; el nuevo igual se crea.
            }

            try {
                Schema::table($tabla, function (Blueprint $t) {
                    $t->unique(['tienda_id', 'number']);
                });
            } catch (\Throwable $e) {
                // Ya existía.
            }
        }
    }

    public function down(): void
    {
        foreach (self::NUMEROS as $tabla => $nombreViejo) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            try {
                Schema::table($tabla, function (Blueprint $t) {
                    $t->dropUnique(['tienda_id', 'number']);
                });
            } catch (\Throwable $e) {
            }

            try {
                Schema::table($tabla, function (Blueprint $t) use ($nombreViejo) {
                    $t->unique('number', $nombreViejo);
                });
            } catch (\Throwable $e) {
            }
        }

        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'tienda_id')) {
                continue;
            }

            Schema::table($tabla, function (Blueprint $t) {
                $t->dropForeign(['tienda_id']);
                $t->dropColumn('tienda_id');
            });
        }
    }
};
