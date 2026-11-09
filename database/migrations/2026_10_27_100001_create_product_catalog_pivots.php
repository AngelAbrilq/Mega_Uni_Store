<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relaciones N:M del catálogo (AT-PRI-001, TBL-TPL-001, TBL-PPA-001).
 *
 *   product_tax        impuestos ADICIONALES del producto (impoconsumo,
 *                      bolsa plástica…). `products.tax_id` sigue siendo el
 *                      impuesto principal: así nada de lo que ya lo usa
 *                      (POS, Smart Inventory, demos) se rompe.
 *   product_supplier   qué proveedores surten el producto, a qué precio,
 *                      con qué código y en cuántos días. Uno es el
 *                      preferido y se refleja en `products.supplier_id`.
 *   attribute_product  valor de cada atributo del catálogo maestro para el
 *                      producto (Marca = Colgate, Talla = M).
 *
 * Los proveedores que ya estaban en `products.supplier_id` pasan a la
 * tabla como preferidos, con el costo actual como precio. Nombres en
 * inglés y orden alfabético de Laravel (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tax', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->foreignId('tax_id')->constrained('taxes')->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['product_id', 'tax_id']);
        });

        Schema::create('product_supplier', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $t->string('supplier_sku', 60)->nullable();
            $t->decimal('supplier_price', 12, 2)->nullable();
            $t->unsignedSmallInteger('lead_time_days')->nullable();
            $t->boolean('is_preferred')->default(false);
            $t->timestamps();

            $t->unique(['product_id', 'supplier_id']);
            $t->index(['supplier_id', 'is_preferred']);
        });

        Schema::create('attribute_product', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attribute_id')->constrained('attributes')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->string('value', 150);
            $t->timestamps();

            $t->unique(['product_id', 'attribute_id']);
            $t->index('attribute_id');
        });

        $ahora = now();

        DB::table('products')
            ->whereNotNull('supplier_id')
            ->orderBy('id')
            ->select(['id', 'supplier_id', 'cost'])
            ->chunk(500, function ($productos) use ($ahora) {
                DB::table('product_supplier')->insertOrIgnore($productos->map(fn ($p) => [
                    'product_id'     => $p->id,
                    'supplier_id'    => $p->supplier_id,
                    'supplier_price' => $p->cost,
                    'is_preferred'   => true,
                    'created_at'     => $ahora,
                    'updated_at'     => $ahora,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_product');
        Schema::dropIfExists('product_supplier');
        Schema::dropIfExists('product_tax');
    }
};
