<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Setting;
use App\Models\Tienda;
use App\Models\User;
use App\Support\Contexto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * La empresa y la tienda con las que arranca una instalación.
 *
 * ── Por qué existe si la migración ya las crea ──
 *
 * La migración las crea para una base que YA tenía datos. Este seeder
 * cubre el otro camino: `migrate:fresh --seed`, donde la migración corre
 * sobre una base vacía —sin usuarios que enlazar, sin ajustes de los que
 * sacar el nombre— y todo lo demás se siembra después.
 *
 * Va de primero en la lista porque todo lo que se siembre después
 * —productos, clientes, proveedores— pregunta por el contexto para saber
 * de quién es. Sin empresa, esa pregunta no tiene respuesta.
 *
 * Es idempotente: correrlo dos veces no crea una segunda empresa.
 */
class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::query()->orderBy('id')->first();

        if (! $empresa) {
            $cfg    = $this->ajustes();
            $nombre = $cfg['negocio.nombre'] ?? 'MEGA UNI STORE';

            $empresa = Empresa::create([
                'nombre'       => $nombre,
                'slug'         => Str::slug($nombre) ?: 'empresa',
                'rubro'        => 'general',
                'razon_social' => $nombre,
                'nit'          => $cfg['negocio.nit'] ?? null,
                'direccion'    => $cfg['negocio.direccion'] ?? null,
                'ciudad'       => $cfg['negocio.ciudad'] ?? null,
                'telefono'     => $cfg['negocio.telefono'] ?? null,
                'correo'       => $cfg['negocio.correo'] ?? null,
                'estado'       => 'activa',
            ]);
        }

        $tienda = $empresa->tiendas()->orderBy('id')->first();

        if (! $tienda) {
            $tienda = $empresa->tiendas()->create([
                'nombre'       => 'Principal',
                'slug'         => 'principal',
                // Sin prefijo: los V-000001 que ya existan siguen valiendo.
                'codigo'       => null,
                'es_principal' => true,
                'activa'       => true,
            ]);
        }

        // Que todo lo que se siembre a continuación sepa de quién es.
        Contexto::usar($empresa->id, $tienda->id);

        $this->command?->line("  empresa «{$empresa->nombre}» · tienda «{$tienda->nombre}»");
    }

    /**
     * Enlaza a la empresa los usuarios que quedaron sueltos.
     *
     * Se llama al final de la siembra porque los usuarios se crean en el
     * paso 2 y esta clase corre en el paso 0. También sirve de red de
     * seguridad en una instalación que ya venía andando: si por lo que sea
     * quedó un usuario sin empresa, no puede entrar a ninguna parte.
     */
    public static function enlazarUsuarios(): void
    {
        if (! Schema::hasTable('empresa_usuario')) {
            return;
        }

        $empresa = Empresa::query()->orderBy('id')->first();

        if (! $empresa) {
            return;
        }

        $tienda = $empresa->tiendas()->orderByDesc('es_principal')->orderBy('id')->first();

        $sueltos = User::query()
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('empresa_usuario')
                  ->whereColumn('empresa_usuario.user_id', 'users.id');
            })
            ->orderBy('id')
            ->get();

        // Si todavía no hay dueño, el usuario más antiguo lo es: es quien
        // instaló el sistema.
        $hayDueno = $empresa->usuarios()->wherePivot('es_dueno', true)->exists();

        foreach ($sueltos as $i => $usuario) {
            $empresa->usuarios()->attach($usuario->id, [
                'es_dueno' => ! $hayDueno && $i === 0,
            ]);

            $tienda?->usuarios()->syncWithoutDetaching([$usuario->id]);
        }
    }

    /** @return array<string,string> */
    private function ajustes(): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        // Se lee en crudo: el modelo Setting pregunta por el contexto, y el
        // contexto todavía no existe cuando corre este seeder.
        return DB::table('settings')->pluck('value', 'key')->all();
    }
}
