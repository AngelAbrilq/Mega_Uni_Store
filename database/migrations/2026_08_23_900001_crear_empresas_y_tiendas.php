<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Paso 4 · Fase 1 — Las dos tablas que convierten esto en un producto.
 *
 * ── Los dos niveles ──
 *
 *   empresa  →  quien paga. Un negocio. Su catálogo, su gente, sus
 *               proveedores, sus clientes. Nada se cruza entre empresas.
 *   tienda   →  cada local de ese negocio. Su inventario, su caja, sus
 *               ventas y su numeración de documentos.
 *
 * ── Por qué la identidad fiscal vive en la tienda ──
 *
 * Van a llegar negocios con un solo NIT y varios locales, y negocios con
 * un NIT por local. Si el NIT viviera solo en la empresa, el segundo caso
 * obligaría a partir el negocio en dos empresas aunque compartan catálogo
 * y clientes. Poniéndolo en la tienda —nullable, heredando el de la
 * empresa cuando está vacío— los dos casos caben sin condicionales.
 *
 * ── Por qué el usuario NO lleva empresa_id ──
 *
 * Porque hay dueños con dos negocios. Si el usuario perteneciera a una
 * sola empresa tendría que existir dos veces: dos correos, dos claves, dos
 * veces cambiarlas. La pertenencia va en una tabla de enlace, y así
 * «cambiar de negocio» es un botón y no volver a entrar.
 *
 * ── Qué pasa con lo que ya existe ──
 *
 * Se crea la empresa 1 con los datos que hoy están en Configuración, su
 * tienda principal, y se enlazan todos los usuarios. No se borra ni se
 * mueve una sola fila: al terminar, el sistema se ve igual que antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ═══════════════ empresas ═══════════════ */
        if (! Schema::hasTable('empresas')) {
            Schema::create('empresas', function (Blueprint $t) {
                $t->id();
                $t->string('nombre', 150);
                $t->string('slug', 160)->unique();

                // Para saber qué catálogo y qué roles sembrarle al crearla.
                $t->string('rubro', 40)->default('general');

                /* Identidad por defecto. Cada tienda puede pisarla. */
                $t->string('razon_social', 180)->nullable();
                $t->string('nit', 30)->nullable();
                $t->string('direccion', 180)->nullable();
                $t->string('ciudad', 80)->nullable();
                $t->string('telefono', 30)->nullable();
                $t->string('correo', 150)->nullable();
                $t->string('logo', 255)->nullable();

                /* Estado de la cuenta */
                $t->string('estado', 20)->default('activa');   // activa | suspendida | vencida
                $t->boolean('es_demo')->default(false);

                // Las demos y las pruebas se limpian solas cuando vence.
                // Una empresa de verdad la deja en nulo: no vence nunca.
                $t->timestamp('expira_en')->nullable();

                $t->timestamps();
                $t->softDeletes();

                $t->index(['estado', 'es_demo']);
                $t->index('expira_en');
            });
        }

        /* ═══════════════ tiendas ═══════════════ */
        if (! Schema::hasTable('tiendas')) {
            Schema::create('tiendas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

                $t->string('nombre', 150);
                $t->string('slug', 160);

                /**
                 * Prefijo de los documentos de este local: V-A-000001.
                 * La tienda principal lo deja vacío para que los números
                 * que ya existen —V-000001— sigan siendo válidos.
                 */
                $t->string('codigo', 6)->nullable();

                /* Identidad propia. Vacío = hereda la de la empresa. */
                $t->string('razon_social', 180)->nullable();
                $t->string('nit', 30)->nullable();
                $t->string('direccion', 180)->nullable();
                $t->string('ciudad', 80)->nullable();
                $t->string('telefono', 30)->nullable();
                $t->string('correo', 150)->nullable();
                $t->string('logo', 255)->nullable();

                $t->boolean('es_principal')->default(false);
                $t->boolean('activa')->default(true);

                $t->timestamps();
                $t->softDeletes();

                // Únicos DENTRO de la empresa: dos negocios distintos pueden
                // tener los dos un local llamado «Centro».
                $t->unique(['empresa_id', 'slug']);
                $t->unique(['empresa_id', 'codigo']);
                $t->index(['empresa_id', 'activa']);
            });
        }

        /* ═══════════════ quién pertenece a qué ═══════════════ */
        if (! Schema::hasTable('empresa_usuario')) {
            Schema::create('empresa_usuario', function (Blueprint $t) {
                $t->id();
                $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();

                // El dueño no se puede quedar sin acceso a su propio negocio.
                $t->boolean('es_dueno')->default(false);

                $t->timestamps();

                $t->unique(['empresa_id', 'user_id']);
                $t->index('user_id');
            });
        }

        if (! Schema::hasTable('tienda_usuario')) {
            Schema::create('tienda_usuario', function (Blueprint $t) {
                $t->id();
                $t->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->timestamps();

                $t->unique(['tienda_id', 'user_id']);
                $t->index('user_id');
            });
        }

        /* ═══════════════ Lo que ya existe, adentro ═══════════════ */
        $this->sembrarLaPrimera();
    }

    /**
     * Crea la empresa 1 con los datos que hoy están en Configuración.
     *
     * Es idempotente a propósito: si alguien vuelve a correr la migración
     * —o si `migrate:fresh` la corre sobre una base ya sembrada— no crea
     * una segunda empresa ni duplica enlaces.
     */
    private function sembrarLaPrimera(): void
    {
        if (DB::table('empresas')->exists()) {
            return;
        }

        $cfg   = $this->ajustesActuales();
        $ahora = now();

        $nombre = $cfg['negocio.nombre'] ?? 'MEGA UNI STORE';

        $empresaId = DB::table('empresas')->insertGetId([
            'nombre'       => $nombre,
            'slug'         => Str::slug($nombre) ?: 'empresa',
            'rubro'        => 'general',
            'razon_social' => $nombre,
            'nit'          => $cfg['negocio.nit'] ?? null,
            'direccion'    => $cfg['negocio.direccion'] ?? null,
            'ciudad'       => $cfg['negocio.ciudad'] ?? null,
            'telefono'     => $cfg['negocio.telefono'] ?? null,
            'correo'       => $cfg['negocio.correo'] ?? null,
            'logo'         => $cfg['negocio.logo'] ?? null,
            'estado'       => 'activa',
            'es_demo'      => false,
            'created_at'   => $ahora,
            'updated_at'   => $ahora,
        ]);

        $tiendaId = DB::table('tiendas')->insertGetId([
            'empresa_id'   => $empresaId,
            'nombre'       => 'Principal',
            'slug'         => 'principal',
            // Sin prefijo: los V-000001 que ya existen siguen siendo válidos.
            'codigo'       => null,
            'es_principal' => true,
            'activa'       => true,
            'created_at'   => $ahora,
            'updated_at'   => $ahora,
        ]);

        /* Todos los usuarios que ya existen entran a esta empresa y a esta
           tienda. El más antiguo queda como dueño: es el que instaló el
           sistema. */
        $usuarios = DB::table('users')->orderBy('id')->pluck('id');

        foreach ($usuarios as $i => $userId) {
            DB::table('empresa_usuario')->insert([
                'empresa_id' => $empresaId,
                'user_id'    => $userId,
                'es_dueno'   => $i === 0,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            DB::table('tienda_usuario')->insert([
                'tienda_id'  => $tiendaId,
                'user_id'    => $userId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    /** @return array<string,string> */
    private function ajustesActuales(): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        return DB::table('settings')->pluck('value', 'key')->all();
    }

    public function down(): void
    {
        Schema::dropIfExists('tienda_usuario');
        Schema::dropIfExists('empresa_usuario');
        Schema::dropIfExists('tiendas');
        Schema::dropIfExists('empresas');
    }
};
