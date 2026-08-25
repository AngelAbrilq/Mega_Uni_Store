<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kardex: el historial de por qué el stock de un producto es el que es.
 *
 * products.stock guarda el saldo actual (rápido de consultar); esta
 * tabla guarda cada movimiento que lo produjo. Si los dos no cuadran,
 * hay un problema — y con esta tabla se puede saber dónde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            // entrada | salida | ajuste | inicial | anulacion
            $table->string('type', 20);
            // venta | compra | ajuste_manual | conteo | devolucion
            $table->string('reason', 30);

            $table->decimal('quantity', 12, 3);      // con signo: negativo = sale de bodega
            $table->decimal('balance_after', 12, 3); // saldo justo después de este movimiento
            $table->decimal('unit_cost', 12, 2)->default(0);

            // De dónde vino el movimiento (una venta, una compra, un ajuste…)
            $table->nullableMorphs('source');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 255)->nullable();

            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index('reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
