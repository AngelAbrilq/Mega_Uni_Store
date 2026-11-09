<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El cliente pasa a poder iniciar sesión.
 *
 * Decisión de fondo: los clientes NO van en la tabla `users`.
 *
 * Sería más rápido crearles un rol «Cliente» y meterlos ahí, pero entonces
 * la seguridad del panel dependería de que ningún `can()` de las 22 rutas
 * administrativas esté mal escrito. Con una tabla y un guard aparte, un
 * cliente no es que «no tenga permiso» en el panel: es que ahí no existe.
 * La diferencia importa el día que alguien se equivoque.
 *
 * Y como es la MISMA tabla `customers` que usa el cajero, la persona que
 * se registra en la tienda es el mismo registro que el que compra en el
 * mostrador: un solo cliente, una sola historia de compras.
 *
 * Ojo con el correo: la columna es única y la tabla tiene borrado lógico.
 * Si borras a un cliente, su correo queda ocupado por un registro que ya no
 * se ve. Al registrarse hay que buscar primero entre los borrados y
 * restaurarlo — que además es lo correcto: es la misma persona volviendo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $tabla) {
            if (! Schema::hasColumn('customers', 'password')) {
                $tabla->string('password')->nullable()->after('email');
            }

            if (! Schema::hasColumn('customers', 'email_verified_at')) {
                $tabla->timestamp('email_verified_at')->nullable()->after('password');
            }

            if (! Schema::hasColumn('customers', 'remember_token')) {
                $tabla->rememberToken();
            }

            if (! Schema::hasColumn('customers', 'last_login_at')) {
                $tabla->timestamp('last_login_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $tabla) {
            $tabla->dropColumn([
                'password', 'email_verified_at', 'remember_token', 'last_login_at',
            ]);
        });
    }
};
