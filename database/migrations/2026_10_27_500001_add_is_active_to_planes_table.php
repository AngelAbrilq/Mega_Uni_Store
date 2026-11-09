<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SA-PLN-001 · Desactivar un plan.
 *
 * `visible` dice si sale en la página de precios; `is_active` dice si todavía
 * se vende. Un plan a la medida de un cliente puede estar activo y oculto;
 * uno retirado está inactivo: no se ofrece ni se asigna, pero los negocios
 * que ya lo tienen siguen igual. Columna nueva en inglés (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $t) {
            $t->boolean('is_active')->default(true)->after('visible');
            $t->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('planes', function (Blueprint $t) {
            $t->dropIndex(['is_active']);
            $t->dropColumn('is_active');
        });
    }
};
