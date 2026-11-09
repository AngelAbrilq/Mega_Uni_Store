<?php

namespace App\Services;

use App\Models\Existencia;
use App\Models\Product;
use App\Models\Tienda;
use App\Models\User;
use App\Support\Contexto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Abrir, cerrar y poblar un local.
 *
 * ── Por qué un servicio y no tres líneas en el controlador ──
 *
 * Porque abrir un local no es insertar una fila. Es insertar la fila,
 * meter al que lo abre adentro —si no, crea un local al que él mismo no
 * puede entrar— y decidir qué del catálogo se vende ahí. Las tres cosas
 * tienen que pasar juntas o no pasar: un local a medio abrir es peor que
 * ninguno, porque se ve en el selector y no funciona.
 *
 * ── Lo que este servicio NO hace ──
 *
 * No comprueba el límite del plan. Eso lo hace `LimiteService` desde el
 * controlador, antes de llamar aquí, porque es una regla comercial y no
 * de armado: el día que se abra un local por otra puerta —la API, un
 * comando— esa puerta decide si cobra o no, y esta sigue sirviendo igual.
 */
class TiendaService
{
    /**
     * Abre un local dentro de la empresa activa.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, User $dueno, bool $copiarCatalogo = true): Tienda
    {
        return DB::transaction(function () use ($datos, $dueno, $copiarCatalogo) {
            $empresaId = Contexto::empresaId();

            $tienda = Tienda::create([
                'empresa_id'   => $empresaId,
                'nombre'       => trim((string) $datos['nombre']),
                'slug'         => $this->slugLibre($datos['nombre'], $empresaId),
                // Cadena vacía y nulo NO son lo mismo aquí: la columna es
                // única dentro de la empresa, y MySQL deja repetir el nulo
                // pero no la cadena vacía. Dos locales sin código —que es
                // lo normal— chocarían entre ellos.
                'codigo'       => $this->codigoLimpio($datos['codigo'] ?? null),
                'razon_social' => $datos['razon_social'] ?? null,
                'nit'          => $datos['nit']          ?? null,
                'direccion'    => $datos['direccion']    ?? null,
                'ciudad'       => $datos['ciudad']       ?? null,
                'telefono'     => $datos['telefono']     ?? null,
                'correo'       => $datos['correo']       ?? null,
                // La principal es la que ya estaba. Un local nuevo nunca
                // nace principal: eso se cambia después, a propósito.
                'es_principal' => false,
                'activa'       => (bool) ($datos['activa'] ?? true),
            ]);

            $this->dejarloAdentro($dueno, $tienda, $empresaId);

            if ($copiarCatalogo) {
                $this->copiarCatalogo($tienda);
            }

            return $tienda;
        });
    }

    /**
     * Enlaza al que abre el local — y de paso a los que ya existían.
     *
     * ── El fallo que esto evita (pasó de verdad, en la primera prueba) ──
     *
     * `User::tiendasEn()` tiene una regla razonable: quien no tiene ningún
     * local asignado puede pararse en TODOS los de su negocio. Es lo que
     * hace que una instalación recién sembrada funcione sin que nadie
     * reparta permisos.
     *
     * Pero esa regla se apaga sola en cuanto el usuario tiene UN local. Y
     * abrir uno lo enlaza... a ese. Resultado: Angel abrió la Sede Norte y
     * el sistema lo encerró ahí — pidió volver a la Principal y lo dejó en
     * la Norte, porque la Principal ya no era «suya». El síntoma es de los
     * peores: no hay error, no hay 403, simplemente el selector no obedece.
     *
     * Por eso, si no tenía ninguno, se le enlazan también los que ya
     * había: pasa de «todos por omisión» a «todos por escrito», que es lo
     * mismo dicho de una manera que no se rompe con el siguiente local.
     */
    private function dejarloAdentro(User $dueno, Tienda $tienda, ?int $empresaId): void
    {
        $tenia = $dueno->tiendas()
            ->where('tiendas.empresa_id', $empresaId)
            ->exists();

        $enlazar = [$tienda->id];

        if (! $tenia) {
            $enlazar = array_merge(
                $enlazar,
                Tienda::where('empresa_id', $empresaId)->pluck('id')->all(),
            );
        }

        // `syncWithoutDetaching` y no `sync`: el usuario puede trabajar en
        // locales de otros negocios y `sync` se los quitaría todos.
        $dueno->tiendas()->syncWithoutDetaching(array_unique($enlazar));
    }

    /**
     * Pone a la venta, en el local nuevo, todo lo que la empresa maneja.
     *
     * Sin fila en `existencias` un producto no existe para ese local —es
     * la regla de la que depende que la sede del norte no maneje cemento—
     * así que un local recién abierto tiene el punto de venta en blanco.
     * Correcto, pero desconcertante la primera vez.
     *
     * Se copian el mínimo de reposición y el estado activo de la sede
     * principal, y NUNCA la existencia: la mercancía llega con un traslado
     * o con una compra, que son las dos puertas que escriben kardex. Nacer
     * con stock puesto a mano sería inventar unidades que nadie movió.
     *
     * @return int cuántos productos quedaron disponibles ahí
     */
    public function copiarCatalogo(Tienda $tienda): int
    {
        $principal = $tienda->empresa?->tiendaPrincipal();

        $modelo = $principal && $principal->id !== $tienda->id
            ? Existencia::withoutGlobalScopes()
                ->where('tienda_id', $principal->id)
                ->get()
                ->keyBy('product_id')
            : collect();

        $puestos = 0;

        Product::query()
            ->where('empresa_id', $tienda->empresa_id)
            ->select('id')
            ->chunkById(200, function ($productos) use ($tienda, $modelo, &$puestos) {
                foreach ($productos as $producto) {
                    $referencia = $modelo->get($producto->id);

                    $creada = Existencia::withoutGlobalScopes()->firstOrCreate(
                        ['tienda_id' => $tienda->id, 'product_id' => $producto->id],
                        [
                            'stock'     => 0,
                            'min_stock' => $referencia?->min_stock ?? 0,
                            'activo'    => $referencia?->activo ?? true,
                        ],
                    );

                    if ($creada->wasRecentlyCreated) {
                        $puestos++;
                    }
                }
            });

        return $puestos;
    }

    /**
     * Por qué no se puede cerrar este local, o null si sí se puede.
     *
     * Devuelve el motivo en vez de un booleano para que la pantalla pueda
     * decir QUÉ pasa. «No se puede» sin explicación es lo que hace que la
     * gente vuelva a intentarlo tres veces y después llame.
     */
    public function motivoParaNoEliminar(Tienda $tienda): ?string
    {
        if ($tienda->es_principal) {
            return 'La sede principal no se cierra: es la que hereda los datos del negocio '
                . 'y la que numera los documentos. Nombra otra como principal antes.';
        }

        if (Tienda::where('empresa_id', $tienda->empresa_id)->count() <= 1) {
            return 'Es el único local del negocio. Un negocio sin local no puede vender.';
        }

        if ($tienda->sales()->withoutGlobalScopes()->exists()) {
            return 'Aquí ya se vendió. Las ventas tienen que seguir existiendo con su local, '
                . 'así que este se desactiva en vez de cerrarse.';
        }

        if ($tienda->cashSessions()->withoutGlobalScopes()->exists()) {
            return 'Aquí se abrió caja alguna vez, y esos arqueos no se pueden dejar huérfanos.';
        }

        if (Existencia::withoutGlobalScopes()->where('tienda_id', $tienda->id)->where('stock', '>', 0)->exists()) {
            return 'Todavía hay mercancía en este local. Trasládala a otra sede antes de cerrarlo.';
        }

        return null;
    }

    /* ═══════════════ Interno ═══════════════ */

    /**
     * Un slug que no choque DENTRO de la empresa.
     *
     * La restricción de la base es `(empresa_id, slug)`, no el slug solo:
     * dos negocios distintos pueden tener los dos su «Centro». Por eso se
     * busca el hueco preguntando por la empresa y no por toda la tabla.
     */
    private function slugLibre(string $nombre, ?int $empresaId): string
    {
        $base = Str::slug($nombre) ?: 'local';
        $slug = $base;
        $n    = 2;

        while (Tienda::withTrashed()->where('empresa_id', $empresaId)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    private function codigoLimpio(?string $codigo): ?string
    {
        $codigo = Str::upper(trim((string) $codigo));

        return $codigo === '' ? null : $codigo;
    }
}
