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
 *
 * ── H-08 (2026-10-21): reconstruir sin perder datos ──
 *
 * La primera versión hacía `drop` y guardaba las filas solo en memoria de
 * PHP. En MySQL cada DDL hace commit implícito: si el proceso moría entre
 * el `drop` y los `insert`, los consecutivos (RN-07) y la configuración
 * se perdían sin respaldo. Ahora:
 *
 *   1. La tabla vieja se RENOMBRA a `<tabla>_respaldo_h08` (nunca se borra primero).
 *   2. Se crea la nueva y se copian las filas desde el respaldo.
 *   3. Solo si el conteo cuadra se elimina el respaldo; si no, queda ahí
 *      y la migración se detiene con un error que lo dice.
 *
 * Y el `down()` ya no aplana en silencio a una sola empresa/tienda: si
 * hay datos de más de una, se niega (borraría consecutivos y repetiría
 * números de factura).
 */
return new class extends Migration
{
    private const SUFIJO_RESPALDO = '_respaldo_h08';

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

        $this->reconstruir(
            'counters',
            function (Blueprint $t) {
                $t->id();
                $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
                $t->string('key', 40);
                $t->unsignedBigInteger('value')->default(0);
                $t->timestamps();

                $t->unique(['tienda_id', 'key']);
            },
            fn ($fila, $ahora) => [
                'tienda_id'  => $tiendaId,
                'key'        => $fila->key,
                'value'      => $fila->value,
                'created_at' => $fila->created_at ?? $ahora,
                'updated_at' => $fila->updated_at ?? $ahora,
            ],
        );
    }

    /* ═══════════════ Ajustes ═══════════════ */

    private function ajustes(int $empresaId): void
    {
        if (! Schema::hasTable('settings') || Schema::hasColumn('settings', 'empresa_id')) {
            return;
        }

        $this->reconstruir(
            'settings',
            function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $t->string('key', 80);
                $t->text('value')->nullable();
                $t->string('group', 40)->default('general');
                $t->timestamps();

                $t->unique(['empresa_id', 'key']);
            },
            fn ($fila, $ahora) => [
                'empresa_id' => $empresaId,
                'key'        => $fila->key,
                'value'      => $fila->value,
                'group'      => $fila->group ?? 'general',
                'created_at' => $fila->created_at ?? $ahora,
                'updated_at' => $fila->updated_at ?? $ahora,
            ],
        );

        cache()->forget('mus.settings.v2');
    }

    /* ═══════════════ Reversa ═══════════════ */

    public function down(): void
    {
        /*
         * La forma vieja solo admite UNA empresa y UNA tienda. Si ya hay
         * datos de varias, volver atrás borraría la configuración de los
         * demás clientes y reiniciaría consecutivos (números de factura
         * repetidos). Eso no se hace en silencio: se detiene aquí.
         */
        $this->exigirUnSoloAmbito('counters', 'tienda_id', 'tiendas');
        $this->exigirUnSoloAmbito('settings', 'empresa_id', 'empresas');

        if (Schema::hasTable('counters') && Schema::hasColumn('counters', 'tienda_id')) {
            $this->reconstruir(
                'counters',
                function (Blueprint $t) {
                    $t->string('key', 40)->primary();
                    $t->unsignedBigInteger('value')->default(0);
                    $t->timestamps();
                },
                fn ($fila) => [
                    'key'        => $fila->key,
                    'value'      => $fila->value,
                    'created_at' => $fila->created_at,
                    'updated_at' => $fila->updated_at,
                ],
            );
        }

        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'empresa_id')) {
            $this->reconstruir(
                'settings',
                function (Blueprint $t) {
                    $t->string('key', 80)->primary();
                    $t->text('value')->nullable();
                    $t->string('group', 40)->default('general');
                    $t->timestamps();
                },
                fn ($fila) => [
                    'key'        => $fila->key,
                    'value'      => $fila->value,
                    'group'      => $fila->group,
                    'created_at' => $fila->created_at,
                    'updated_at' => $fila->updated_at,
                ],
            );
        }

        cache()->forget('mus.settings.v2');
    }

    /* ═══════════════ Apoyo ═══════════════ */

    /**
     * Reconstruye una tabla sin que sus filas vivan nunca solo en memoria.
     *
     * @param  string    $tabla    Tabla a reconstruir.
     * @param  callable  $esquema  Definición nueva (Blueprint).
     * @param  callable  $mapear   (object $fila, Carbon $ahora): array → fila nueva.
     *
     * @throws \RuntimeException si la copia no cuadra; el respaldo queda intacto.
     */
    private function reconstruir(string $tabla, callable $esquema, callable $mapear): void
    {
        $respaldo = $tabla . self::SUFIJO_RESPALDO;

        if (Schema::hasTable($respaldo)) {
            throw new \RuntimeException(
                "Ya existe «{$respaldo}» de un intento anterior. Revísala y elimínala antes de reintentar."
            );
        }

        Schema::rename($tabla, $respaldo);
        Schema::create($tabla, $esquema);

        $ahora   = now();
        $origen  = DB::table($respaldo)->count();

        DB::table($respaldo)->orderBy('key')->chunk(500, function ($filas) use ($tabla, $mapear, $ahora) {
            DB::table($tabla)->insert($filas->map(fn ($f) => $mapear($f, $ahora))->all());
        });

        $copiadas = DB::table($tabla)->count();

        if ($copiadas !== $origen) {
            throw new \RuntimeException(
                "«{$tabla}»: se copiaron {$copiadas} de {$origen} filas. "
                . "Los datos originales siguen en «{$respaldo}»."
            );
        }

        Schema::drop($respaldo);
    }

    /** Detiene el rollback si la tabla ya tiene datos de más de un ámbito. */
    private function exigirUnSoloAmbito(string $tabla, string $columna, string $nombreAmbito): void
    {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
            return;
        }

        $ambitos = DB::table($tabla)->distinct()->count($columna);

        if ($ambitos > 1) {
            throw new \RuntimeException(
                "Rollback bloqueado (H-08): «{$tabla}» tiene datos de {$ambitos} {$nombreAmbito}. "
                . 'Volver al esquema anterior borraría información de los demás y repetiría consecutivos.'
            );
        }
    }
};
