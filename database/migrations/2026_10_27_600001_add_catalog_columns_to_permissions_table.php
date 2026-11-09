<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SA-PER-001 · El permiso con módulo, acción y descripción, y la marca de
 * «del sistema» para que el catálogo no deje borrar lo que el código usa.
 * Columnas nuevas en inglés sobre la tabla de spatie (ADR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $t) {
            $t->string('module', 40)->nullable()->after('guard_name');
            $t->string('action', 40)->nullable()->after('module');
            $t->string('description', 200)->nullable()->after('action');
            $t->boolean('is_system')->default(false)->after('description');
            $t->index('module');
        });

        // Los que ya existen salen del seeder: son del sistema.
        foreach (DB::table('permissions')->get(['id', 'name']) as $p) {
            [$modulo, $accion] = array_pad(explode('.', $p->name, 2), 2, 'ver');
            DB::table('permissions')->where('id', $p->id)->update([
                'module' => $modulo, 'action' => $accion, 'is_system' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $t) {
            $t->dropIndex(['module']);
            $t->dropColumn(['module', 'action', 'description', 'is_system']);
        });
    }
};
