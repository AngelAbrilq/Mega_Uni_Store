<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Dirección amable del registro: «Cuaderno argollado» → «cuaderno-argollado».
 *
 * Se genera sola al crear, y al cambiar el nombre **no** se regenera: si un
 * producto ya salió publicado como /producto/cuaderno-argollado y alguien
 * corrige una tilde del nombre, cambiar la dirección rompería el enlace que
 * la gente guardó y lo que Google ya indexó. Las direcciones son un
 * compromiso público, no un reflejo del nombre.
 *
 * Si de verdad hay que cambiarla, se vacía el campo y el modelo la arma de
 * nuevo al guardar.
 */
trait ConSlug
{
    protected static function bootConSlug(): void
    {
        static::saving(function (Model $modelo) {
            if (blank($modelo->slug)) {
                $modelo->slug = $modelo->generarSlug();
            }
        });
    }

    /** Campo del que sale la dirección. */
    public function campoSlug(): string
    {
        return 'name';
    }

    /**
     * Arma una dirección única. Si «cuaderno-argollado» ya existe, prueba
     * «cuaderno-argollado-2», «-3», y así. Nunca devuelve una repetida
     * porque la columna es única y una colisión sería un error 500.
     */
    public function generarSlug(): string
    {
        $base = Str::slug((string) $this->getAttribute($this->campoSlug()));

        if ($base === '') {
            $base = 'registro';
        }

        $base = Str::limit($base, 200, '');
        $slug = $base;
        $n    = 1;

        while ($this->slugOcupado($slug)) {
            $n++;
            $slug = $base . '-' . $n;
        }

        return $slug;
    }

    /** ¿Otro registro —incluso uno borrado— ya usa esta dirección? */
    protected function slugOcupado(string $slug): bool
    {
        $consulta = static::query()->where('slug', $slug);

        if (method_exists(static::class, 'bootSoftDeletes')) {
            $consulta->withTrashed();
        }

        if ($this->exists) {
            $consulta->whereKeyNot($this->getKey());
        }

        return $consulta->exists();
    }

    /**
     * Route model binding por id O por slug.
     *
     * ── El error que esto arregla ──
     *
     * Antes buscaba SIEMPRE por slug. Y el panel arma sus direcciones con
     * el id —`route('products.show', $producto)` da `/products/5`, porque
     * la clave de ruta del modelo sigue siendo la primaria—, así que la
     * consulta era «dame el producto cuyo slug es "5"». No existe: 404 al
     * abrir cualquier producto o categoría desde el listado.
     *
     * Es la clase de error que no se ve al escribirlo, porque el nombre del
     * método promete lo que uno espera y el comentario decía «para la
     * tienda pública» — que es donde sí llega un slug.
     *
     * Ahora se mira lo que llega: si es un número es un id, y si no, un
     * slug. Un slug nunca es solo dígitos, porque sale de un nombre; y si
     * alguien lograra crear uno que sí, el id gana — que es la respuesta
     * correcta para el panel, que es quien manda ids.
     *
     * Cuando la ruta pide un campo concreto (`{producto:slug}`), manda ese.
     */
    public function resolveRouteBinding($valor, $campo = null)
    {
        if ($campo !== null) {
            return $this->where($campo, $valor)->firstOrFail();
        }

        return is_numeric($valor)
            ? $this->whereKey($valor)->firstOrFail()
            : $this->where('slug', $valor)->firstOrFail();
    }
}
