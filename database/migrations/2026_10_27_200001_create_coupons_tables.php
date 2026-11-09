<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cupones de descuento (AT-CUP-001, VD-CUP-001, TBL-VCU-001).
 *
 *   coupons        el cupón del negocio: código único, porcentaje o valor
 *                  fijo, tope de descuento, compra mínima, vigencia y
 *                  límite de usos. Vale en todos los locales del negocio
 *                  (la doc lo ataba a una tienda; el catálogo y los precios
 *                  ya son por empresa).
 *   sale_coupons   qué cupón se aplicó en cada venta y cuánto descontó,
 *                  con el código congelado.
 *
 * Equivalen a las tablas documentadas `cupones` y `ventas_cupones`.
 * Nombres en inglés (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('code', 40);
            $t->string('description', 200)->nullable();
            $t->string('type', 12)->default('percentage');
            $t->decimal('value', 12, 2);
            $t->decimal('max_discount', 12, 2)->nullable();
            $t->decimal('min_purchase', 12, 2)->nullable();
            $t->unsignedInteger('max_uses')->nullable();
            $t->unsignedInteger('used_count')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();

            $t->unique(['empresa_id', 'code']);
            $t->index(['empresa_id', 'is_active']);
        });

        Schema::create('sale_coupons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $t->foreignId('coupon_id')->constrained('coupons');
            $t->string('code', 40);
            $t->decimal('discount_amount', 12, 2);
            $t->timestamps();

            $t->unique(['sale_id', 'coupon_id']);
            $t->index('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_coupons');
        Schema::dropIfExists('coupons');
    }
};
