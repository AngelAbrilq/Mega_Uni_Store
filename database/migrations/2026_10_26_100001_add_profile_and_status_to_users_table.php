<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuarios: perfil completo, estado y borrado lógico.
 *
 * Cierra SA-USR-001 (apellido, teléfono, estado), SA-USR-003 / VW-USR-001
 * (soft delete) y H-F04.
 *
 * ── Por qué el borrado lógico no es opcional ──
 *
 * `sales.user_id`, `purchases.user_id`, `sale_returns.user_id` y
 * `cash_sessions.user_id` apuntan a `users` SIN `onDelete`. Borrar de verdad
 * a un vendedor con ventas reventaba con un error de integridad (500), y si
 * la FK se hubiera relajado se perdía quién vendió. Con `deleted_at` el
 * usuario deja de existir para el sistema, pero la historia queda intacta.
 *
 *   is_active   false → no puede iniciar sesión y su sesión abierta se cierra.
 *               Es reversible: para ausencias, vacaciones, retiros temporales.
 *   deleted_at  baja definitiva desde el panel. Se conserva por trazabilidad.
 *
 * Columnas nuevas en inglés (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'last_name')) {
                $t->string('last_name', 100)->nullable()->after('name');
            }
            if (! Schema::hasColumn('users', 'phone')) {
                $t->string('phone', 30)->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $t->boolean('is_active')->default(true)->after('phone')->index();
            }
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $t->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'is_active')) {
                $t->dropIndex(['is_active']);
            }
        });

        Schema::table('users', function (Blueprint $t) {
            foreach (['last_name', 'phone', 'is_active', 'deleted_at'] as $columna) {
                if (Schema::hasColumn('users', $columna)) {
                    $t->dropColumn($columna);
                }
            }
        });
    }
};
