<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario y relaciones comerciales del producto.
 *
 * La tabla original solo guardaba nombre, precio y costo. Una tienda
 * necesita además saber cuántas unidades hay, cuándo reponer, qué
 * impuesto aplica y a quién se le compra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Código interno del producto (distinto del código de barras).
            $table->string('sku', 50)->nullable()->unique()->after('name');

            // Existencias
            $table->integer('stock')->default(0)->after('cost');          // unidades disponibles
            $table->integer('min_stock')->default(0)->after('stock');     // punto de reorden

            // Impuesto que se le aplica al vender
            $table->foreignId('tax_id')->nullable()->after('unit_id')
                  ->constrained('taxes')->nullOnDelete();

            // Proveedor habitual
            $table->foreignId('supplier_id')->nullable()->after('tax_id')
                  ->constrained('suppliers')->nullOnDelete();

            // Índices para las búsquedas y filtros del listado
            $table->index('is_active');
            $table->index('stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tax_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['stock']);
            $table->dropColumn(['sku', 'stock', 'min_stock', 'tax_id', 'supplier_id']);
        });
    }
};
