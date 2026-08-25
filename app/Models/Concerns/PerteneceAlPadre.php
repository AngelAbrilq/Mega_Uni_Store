<?php

namespace App\Models\Concerns;

use App\Support\Contexto;
use Illuminate\Database\Eloquent\Builder;

/**
 * Este registro no tiene dueño propio: hereda el de su documento.
 *
 * ── Por qué hace falta ──
 *
 * Las líneas de una venta no llevan `tienda_id`. No es un descuido: una
 * línea sin su venta no significa nada, y repetir la tienda en cada renglón
 * sería guardar el mismo dato dos veces con la posibilidad de que un día no
 * coincidan.
 *
 * El problema es que sin columna tampoco hay filtro, y una consulta como
 *
 *     SaleItem::query()->sum('quantity')
 *
 * —que existe, de verdad, en los informes— sumaría las líneas de TODOS los
 * clientes. El total de unidades vendidas de William incluiría las de
 * Diego. Nadie lo notaría hasta que los números no cuadren, y para entonces
 * ya se habrían enseñado.
 *
 * ── Cómo lo cierra ──
 *
 * El filtro no mira una columna: mira el documento padre.
 *
 *     where sale_id in (select id from sales where tienda_id in (…))
 *
 * Y como la consulta de adentro es una consulta normal de `Sale`, le cae
 * encima su propio filtro global. Es decir: este archivo no repite la regla
 * de aislamiento, la reutiliza. Si mañana cambia cómo se aísla una venta,
 * las líneas de venta se aíslan igual sin tocar nada aquí.
 *
 * Al insertar no se hace nada: una línea se crea siempre desde su documento,
 * que ya trae la tienda puesta.
 */
trait PerteneceAlPadre
{
    protected static function bootPerteneceAlPadre(): void
    {
        static::addGlobalScope('empresa', function (Builder $consulta) {
            if (Contexto::sinFiltroActivo()) {
                return;
            }

            if (Contexto::empresaId() === null) {
                return;
            }

            $modelo = $consulta->getModel();

            [$clase, $llave] = $modelo->documentoPadre();

            $padre = new $clase;

            $consulta->whereIn(
                $modelo->getTable() . '.' . $llave,
                $clase::query()->select($padre->getQualifiedKeyName())
            );
        });
    }

    /**
     * De qué documento cuelga esto.
     *
     * @return array{0: class-string, 1: string} [clase del padre, columna]
     */
    abstract public function documentoPadre(): array;

    /** Consulta sin el filtro de empresa. Fácil de buscar a propósito. */
    public static function sinFiltroEmpresa(): Builder
    {
        return static::query()->withoutGlobalScope('empresa');
    }
}
