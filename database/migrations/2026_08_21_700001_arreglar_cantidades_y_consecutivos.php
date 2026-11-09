<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Arreglos de fondo del esquema, antes de abrirle la puerta al público.
 *
 * 1. Las existencias pasan a admitir decimales.
 *
 *    Todo el sistema mueve cantidades como decimal(12,3): los renglones de
 *    venta, los de compra, los de devolución y el propio kardex. La única
 *    columna que había quedado como entero era `products.stock`. Con
 *    productos que se venden por unidad no se nota; el día que se venda
 *    medio kilo de café, el kardex guarda 0,500 y la existencia del
 *    producto trunca a 0. Quedarían peleando dos verdades.
 *
 * 2. Tabla de consecutivos.
 *
 *    Los números V-000001 / C-000001 / D-000001 se sacaban con
 *    `max(id) + 1`. Si dos cajeros cobran en el mismo segundo, los dos leen
 *    el mismo máximo y el segundo choca contra el índice único: error 500
 *    delante del cliente. Con una fila por contador, el `UPDATE … SET valor
 *    = valor + 1` bloquea esa fila y la base serializa por nosotros.
 *
 * 3. Documento del cliente único de verdad.
 *
 *    El controlador ya lo validaba, pero una validación no es una garantía:
 *    entre que consulta y guarda cabe otra petición. El índice sí lo es.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ───────── 1. Existencias con decimales ───────── */
        Schema::table('products', function (Blueprint $tabla) {
            $tabla->decimal('stock', 12, 3)->default(0)->change();
            $tabla->decimal('min_stock', 12, 3)->default(0)->change();
        });

        /* ───────── 2. Consecutivos ───────── */
        if (! Schema::hasTable('counters')) {
            Schema::create('counters', function (Blueprint $tabla) {
                $tabla->string('key', 40)->primary();   // ventas | compras | devoluciones
                $tabla->unsignedBigInteger('value')->default(0);
                $tabla->timestamps();
            });
        }

        // Se arranca cada contador donde va la numeración actual, para que
        // no se repitan números con los documentos que ya existen.
        foreach ([
            'ventas'       => ['sales', 'V-'],
            'compras'      => ['purchases', 'C-'],
            'devoluciones' => ['sale_returns', 'D-'],
        ] as $clave => [$tabla, $prefijo]) {
            $desde = 0;

            if (Schema::hasTable($tabla)) {
                $maximo = DB::table($tabla)->max('number');

                // «V-000123» → 123
                if ($maximo !== null) {
                    $desde = (int) ltrim((string) str_replace($prefijo, '', $maximo), '0');
                }

                $desde = max($desde, (int) DB::table($tabla)->max('id'));
            }

            DB::table('counters')->updateOrInsert(
                ['key' => $clave],
                ['value' => $desde, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        /* ───────── 3. Documento único ───────── */
        if ($this->documentosRepetidos()) {
            // No se fuerza el índice sobre datos que ya vienen repetidos:
            // se avisa y se deja al usuario decidir cuál se queda.
            echo PHP_EOL
                . '  ! Hay clientes con el mismo número de documento. No se creó el'
                . PHP_EOL
                . '    índice único. Únelos o corrígelos y vuelve a correr la migración.'
                . PHP_EOL;
        } else {
            Schema::table('customers', function (Blueprint $tabla) {
                $tabla->unique('document_number', 'customers_document_number_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $tabla) {
            $tabla->integer('stock')->default(0)->change();
            $tabla->integer('min_stock')->default(0)->change();
        });

        Schema::dropIfExists('counters');

        // El índice solo existe si en su momento no había duplicados.
        try {
            Schema::table('customers', function (Blueprint $tabla) {
                $tabla->dropUnique('customers_document_number_unique');
            });
        } catch (\Throwable $e) {
            // No estaba: no hay nada que deshacer.
        }
    }

    /**
     * ¿Hay dos clientes con el mismo documento?
     *
     * Cuenta también los borrados: un índice único cubre TODAS las filas de
     * la tabla, incluidas las que el borrado lógico esconde. Si no se
     * miraran, la migración se caería a la mitad al crear el índice.
     */
    private function documentosRepetidos(): bool
    {
        if (! Schema::hasTable('customers')) {
            return false;
        }

        $repetidos = DB::table('customers')
            ->selectRaw('document_number, COUNT(*) as n')
            ->whereNotNull('document_number')
            ->where('document_number', '!=', '')
            ->groupBy('document_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        return $repetidos->isNotEmpty();
    }
};
