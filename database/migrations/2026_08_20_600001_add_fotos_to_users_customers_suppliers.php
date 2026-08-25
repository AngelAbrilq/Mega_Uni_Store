<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto de perfil para usuarios, clientes y proveedores.
 *
 * Igual que en productos: la columna es un VARCHAR con la ruta del
 * archivo ("usuarios/9f3a2b.jpg"), no la imagen. La foto vive en
 * storage/app/public.
 *
 * Se admite hasta 255 caracteres porque ahí también puede caber una URL
 * completa —por ejemplo la foto que devuelve una cuenta de Google si
 * algún día se agrega ese inicio de sesión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $tabla) {
            if (! Schema::hasColumn('users', 'avatar_url')) {
                $tabla->string('avatar_url')->nullable()->after('email');
            }
        });

        Schema::table('customers', function (Blueprint $tabla) {
            if (! Schema::hasColumn('customers', 'image_url')) {
                $tabla->string('image_url')->nullable()->after('address');
            }
        });

        Schema::table('suppliers', function (Blueprint $tabla) {
            if (! Schema::hasColumn('suppliers', 'image_url')) {
                $tabla->string('image_url')->nullable()->after('contact_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('avatar_url'));
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('image_url'));
        Schema::table('suppliers', fn (Blueprint $t) => $t->dropColumn('image_url'));
    }
};
