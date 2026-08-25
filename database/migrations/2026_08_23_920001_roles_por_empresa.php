<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un rol puede ser de todos o de un negocio.
 *
 * ── El problema ──
 *
 * Los siete roles del sistema —Superadministrador, Administrador,
 * Supervisor, Cajero, Vendedor, Bodeguero, Reportero— sirven en casi
 * cualquier negocio. Pero no en todos por igual: una peluquería quiere
 * «Estilista», una ferretería quiere «Bodeguero de patio», una heladería
 * no necesita ni la mitad de los siete.
 *
 * Si los roles siguen siendo una lista única para todo el sistema, el día
 * que la peluquería cree «Estilista» ese rol le aparece también a la
 * ferretería y al supermercado, en el desplegable, para asignárselo a un
 * cajero. Y al revés: nadie puede crear un rol sin ensuciarle la lista a
 * los demás.
 *
 * ── La solución ──
 *
 * Una columna. `empresa_id` en nulo significa «rol del sistema, lo ve todo
 * el mundo»; con un número, «rol de ese negocio y de nadie más».
 *
 * Los siete que ya existen se quedan en nulo: son los que sirven en
 * cualquier rubro y no tiene sentido repetirlos por cliente.
 *
 * ── Lo que NO se toca, a propósito ──
 *
 * No se activa el modo «teams» de spatie/laravel-permission. Ese modo
 * reconstruye las llaves primarias de `model_has_roles` y
 * `model_has_permissions`, que ya tienen datos, y a cambio resuelve un
 * caso que aquí no existe: la misma persona con roles DISTINTOS en dos
 * negocios. William es dueño de sus dos negocios —el mismo rol en los dos—
 * y sus empleados trabajan cada uno en uno solo.
 *
 * Cirugía de llaves primarias sobre tablas con datos, para resolver algo
 * que nadie necesita todavía, es cambiar riesgo real por beneficio
 * imaginario. El día que haga falta, este mismo diseño migra a «teams»
 * sin perder nada: la columna ya está y ya se llama por su negocio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('empresas')) {
            return;
        }

        if (! Schema::hasColumn('roles', 'empresa_id')) {
            Schema::table('roles', function (Blueprint $t) {
                // En nulo = rol del sistema. `cascadeOnDelete` porque un rol
                // que solo existía para un negocio no tiene sentido cuando
                // ese negocio ya no está.
                $t->foreignId('empresa_id')->nullable()->after('id')
                    ->constrained('empresas')->cascadeOnDelete();
            });
        }

        /**
         * El único pasa de global a «por negocio».
         *
         * Sin esto, la peluquería no podría llamar «Estilista» a su rol si
         * otra peluquería ya usó ese nombre — dos negocios que ni se
         * conocen, peleándose por una palabra.
         */
        $this->rehacerUnico();
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'empresa_id')) {
            return;
        }

        // Los roles de un negocio no caben en un esquema sin negocios: se
        // borran antes de devolver el único global, o el índice fallaría
        // por nombres repetidos.
        \Illuminate\Support\Facades\DB::table('roles')->whereNotNull('empresa_id')->delete();

        Schema::table('roles', function (Blueprint $t) {
            $t->dropForeign(['empresa_id']);
            $t->dropUnique('roles_empresa_id_name_guard_name_unique');
            $t->dropColumn('empresa_id');
            $t->unique(['name', 'guard_name']);
        });
    }

    /* ═══════════════ Interno ═══════════════ */

    private function rehacerUnico(): void
    {
        $indices = collect($this->indices('roles'));

        $yaEsta = $indices->contains(
            fn ($i) => ($i['unique'] ?? false)
                && array_values($i['columns']) === ['empresa_id', 'name', 'guard_name']
        );

        if ($yaEsta) {
            return;
        }

        $viejo = $indices->first(
            fn ($i) => ($i['unique'] ?? false)
                && array_values($i['columns']) === ['name', 'guard_name']
        );

        Schema::table('roles', function (Blueprint $t) use ($viejo) {
            if ($viejo) {
                $t->dropUnique($viejo['name']);
            }

            $t->unique(['empresa_id', 'name', 'guard_name']);
        });
    }

    /** Los índices de una tabla, o vacío si el motor no los sabe listar. */
    private function indices(string $tabla): array
    {
        try {
            return Schema::getIndexes($tabla);
        } catch (\Throwable $e) {
            return [];
        }
    }
};
