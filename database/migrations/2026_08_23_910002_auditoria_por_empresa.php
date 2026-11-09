<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La bitácora también es de alguien.
 *
 * ── Qué se estaba escapando ──
 *
 * `audit_logs` guarda quién tocó qué, y guarda copia de los valores: el
 * nombre del producto, el precio anterior, el nombre del cliente. Sin
 * columna de empresa, la pantalla de Auditoría le mostraría a William
 * renglón por renglón lo que hizo Diego en su supermercado —con nombres y
 * con precios—.
 *
 * Es la fuga más silenciosa de todas, porque la bitácora no se mira todos
 * los días y porque nadie la piensa como «datos del cliente». Lo es: es la
 * historia completa de su negocio.
 *
 * ── Por qué queda nullable ──
 *
 * Hay cosas que se auditan fuera de todo negocio: un comando de consola,
 * una tarea programada. Esas filas quedan en nulo y no aparecen en la
 * pantalla de ningún cliente, que es exactamente lo correcto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        if (! Schema::hasColumn('audit_logs', 'empresa_id')) {
            Schema::table('audit_logs', function (Blueprint $t) {
                $t->foreignId('empresa_id')->nullable()->after('id')
                    ->constrained('empresas')->nullOnDelete();

                // El índice es por empresa Y fecha porque así se consulta:
                // «lo que pasó en mi negocio, lo más reciente primero».
                $t->index(['empresa_id', 'created_at']);
            });
        }

        /**
         * Lo que ya estaba escrito es de la primera empresa.
         *
         * En esta instalación no hay de otra: toda la historia previa se
         * hizo cuando había un solo negocio. Se marca en un solo UPDATE
         * porque son miles de filas y recorrerlas una por una tardaría
         * minutos sin ganar nada.
         */
        $primera = DB::table('empresas')->orderBy('id')->value('id');

        if ($primera) {
            DB::table('audit_logs')->whereNull('empresa_id')->update(['empresa_id' => $primera]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs') || ! Schema::hasColumn('audit_logs', 'empresa_id')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $t) {
            $t->dropForeign(['empresa_id']);
            $t->dropIndex(['empresa_id', 'created_at']);
            $t->dropColumn('empresa_id');
        });
    }
};
