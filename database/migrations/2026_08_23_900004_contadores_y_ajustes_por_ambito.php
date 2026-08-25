<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 4 · Fase 1 — Los contadores bajan a la tienda, los ajustes suben
 * a la empresa.
 *
 * ── Los contadores ──
 *
 * La numeración de documentos es del local, no del negocio. Dos tiendas
 * compartiendo un solo contador producen recibos entreverados —V-000012
 * en el centro, V-000013 en el norte, V-000014 en el centro otra vez— que
 * nadie puede cuadrar. Y el día de la facturación electrónica, la
 * resolución de la DIAN va por establecimiento.
 *
 * ── Los ajustes ──
 *
 * Hoy `settings.key` es la llave primaria, o sea que solo puede existir
 * un «negocio.nombre» en toda la base. Con dos empresas eso es imposible:
 * cada una necesita el suyo. La llave pasa a ser la pareja
 * (empresa, clave).
 *
 * ── Por qué se reconstruyen en vez de alterarse ──
 *
 * Las dos tablas tienen una columna de texto como llave primaria. Cambiar
 * una llave primaria en MySQL sobre una tabla con datos es de las
 * operaciones que fallan a la mitad y dejan la tabla en un estado raro.
 * Son tablas chicas —una decena de filas—, así que se leen enteras, se
 * vuelven a crear con la forma nueva y se devuelven los datos. Más pasos,
 * cero sorpresas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $empresaId = (int) (DB::table('empresas')->min('id') ?: 1);
        $tiendaId  = (int) (DB::table('tiendas')->min('id') ?: 1);

        $this->contadores($tiendaId);
        $this->ajustes($empresaId);
    }

    /* ═══════════════ Contadores ═══════════════ */

    private function contadores(int $tiendaId): void
    {
        if (! Schema::hasTable('counters') || Schema::hasColumn('counters', 'tienda_id')) {
            return;
        }

        // Se guarda lo que hay ANTES de tocar nada. El valor no se puede
        // recalcular: puede ir por delante del documento más alto si se
        // anuló el último, y volver atrás repetiría un número.
        $viejos = DB::table('counters')->get();

        Schema::drop('counters');

        Schema::create('counters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $t->string('key', 40);
            $t->unsignedBigInteger('value')->default(0);
            $t->timestamps();

            $t->unique(['tienda_id', 'key']);
        });

        $ahora = now();

        foreach ($viejos as $fila) {
            DB::table('counters')->insert([
                'tienda_id'  => $tiendaId,
                'key'        => $fila->key,
                'value'      => $fila->value,
                'created_at' => $fila->created_at ?? $ahora,
                'updated_at' => $fila->updated_at ?? $ahora,
            ]);
        }
    }

    /* ═══════════════ Ajustes ═══════════════ */

    private function ajustes(int $empresaId): void
    {
        if (! Schema::hasTable('settings') || Schema::hasColumn('settings', 'empresa_id')) {
            return;
        }

        $viejos = DB::table('settings')->get();

        Schema::drop('settings');

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->string('key', 80);
            $t->text('value')->nullable();
            $t->string('group', 40)->default('general');
            $t->timestamps();

            $t->unique(['empresa_id', 'key']);
        });

        $ahora = now();

        foreach ($viejos as $fila) {
            DB::table('settings')->insert([
                'empresa_id' => $empresaId,
                'key'        => $fila->key,
                'value'      => $fila->value,
                'group'      => $fila->group ?? 'general',
                'created_at' => $fila->created_at ?? $ahora,
                'updated_at' => $fila->updated_at ?? $ahora,
            ]);
        }

        cache()->forget('mus.settings.v2');
    }

    public function down(): void
    {
        /* Se vuelve a la forma vieja conservando los datos de la primera
           empresa y la primera tienda, que es lo único que cabe allá. */

        if (Schema::hasTable('counters') && Schema::hasColumn('counters', 'tienda_id')) {
            $viejos = DB::table('counters')->orderBy('tienda_id')->get()->unique('key');

            Schema::drop('counters');

            Schema::create('counters', function (Blueprint $t) {
                $t->string('key', 40)->primary();
                $t->unsignedBigInteger('value')->default(0);
                $t->timestamps();
            });

            foreach ($viejos as $fila) {
                DB::table('counters')->insert([
                    'key'        => $fila->key,
                    'value'      => $fila->value,
                    'created_at' => $fila->created_at,
                    'updated_at' => $fila->updated_at,
                ]);
            }
        }

        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'empresa_id')) {
            $viejos = DB::table('settings')->orderBy('empresa_id')->get()->unique('key');

            Schema::drop('settings');

            Schema::create('settings', function (Blueprint $t) {
                $t->string('key', 80)->primary();
                $t->text('value')->nullable();
                $t->string('group', 40)->default('general');
                $t->timestamps();
            });

            foreach ($viejos as $fila) {
                DB::table('settings')->insert([
                    'key'        => $fila->key,
                    'value'      => $fila->value,
                    'group'      => $fila->group,
                    'created_at' => $fila->created_at,
                    'updated_at' => $fila->updated_at,
                ]);
            }
        }

        cache()->forget('mus.settings.v2');
    }
};
