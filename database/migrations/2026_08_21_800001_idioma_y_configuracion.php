<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paso 3: idioma por usuario y ajustes nuevos.
 *
 * 1. `users.locale`
 *
 *    El idioma es del usuario, no del navegador. Un negocio puede tener un
 *    cajero que prefiere inglés y un administrador que trabaja en español,
 *    y cada uno debe ver el panel a su manera desde cualquier computador.
 *    Va nullable: nulo significa «el que tenga configurado el negocio», que
 *    es distinto de «español» — si mañana la tienda cambia su idioma por
 *    defecto, los que nunca eligieron uno lo siguen siguiendo.
 *
 * 2. Ajustes nuevos
 *
 *    Se insertan las filas que faltan sin tocar las que ya existan: alguien
 *    pudo haber cambiado el nombre del negocio y esta migración no tiene por
 *    qué devolvérselo al valor de fábrica. `insertOrIgnore` sobre la clave
 *    primaria hace exactamente eso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $tabla) {
                $tabla->string('locale', 5)->nullable()->after('email');
            });
        }

        if (! Schema::hasTable('settings')) {
            return;
        }

        $ahora  = now();
        $nuevos = [];

        foreach (\App\Models\Setting::PREDETERMINADOS as $clave => $valor) {
            $nuevos[] = [
                'key'        => $clave,
                'value'      => (string) $valor,
                'group'      => \Illuminate\Support\Str::before($clave, '.'),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        // insertOrIgnore respeta lo que el usuario ya configuró.
        DB::table('settings')->insertOrIgnore($nuevos);

        // El grupo de las filas viejas se guardaba como 'negocio' para todo.
        // Ahora la pestaña se decide por el grupo, así que hay que corregirlo
        // o los ajustes de recibo aparecerían bajo Negocio.
        foreach (\App\Models\Setting::GRUPOS as $grupo) {
            DB::table('settings')
                ->where('key', 'like', $grupo . '.%')
                ->update(['group' => $grupo]);
        }

        cache()->forget('mus.settings');
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $tabla) {
                $tabla->dropColumn('locale');
            });
        }

        // Los ajustes no se borran: son datos del negocio, no estructura.
        // Deshacer la migración no debería vaciarle la configuración a nadie.
        cache()->forget('mus.settings');
    }
};
