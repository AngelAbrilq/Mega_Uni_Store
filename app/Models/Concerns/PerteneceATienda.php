<?php

namespace App\Models\Concerns;

use App\Models\Tienda;
use App\Support\Contexto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Este registro pasó en un local.
 *
 * ── Por qué el filtro es por EMPRESA y no por tienda ──
 *
 * Es la decisión más importante de este archivo y vale la pena explicarla,
 * porque lo intuitivo sería filtrar por la tienda activa.
 *
 * La frontera de aislamiento es la empresa, no el local. Una venta hecha
 * en la otra tienda de William sigue siendo de William: tiene derecho a
 * verla, a sumarla en su informe y a buscarla. Una venta de Diego no lo
 * es, y no debe existir para él.
 *
 * Si el filtro fuera por tienda activa, el informe consolidado —«¿cómo va
 * el negocio completo?»— no podría escribirse sin levantar el filtro, y
 * levantar el filtro es exactamente lo que no queremos que nadie tenga que
 * hacer a menudo. También se rompería `StockService`, que necesita sumar
 * la existencia de TODOS los locales para mantener el total del producto.
 *
 * Ver solo un local es un filtro más, explícito, en la pantalla que lo
 * pida. Eso se elige; el aislamiento no.
 *
 * ── Al escribir ──
 *
 * La tienda de un documento nuevo sale del contexto: si estás trabajando
 * en el norte, la venta es del norte. Eso no se pregunta ni se puede
 * cambiar desde el formulario.
 */
trait PerteneceATienda
{
    protected static function bootPerteneceATienda(): void
    {
        static::addGlobalScope('empresa', function (Builder $consulta) {
            if (Contexto::sinFiltroActivo()) {
                return;
            }

            $tiendas = Contexto::tiendaIds();

            if ($tiendas === null) {
                return;
            }

            $columna = $consulta->getModel()->getTable() . '.tienda_id';

            // Sin locales, no hay nada que ver. Se dice explícitamente en
            // vez de dejar pasar la consulta sin filtro.
            if ($tiendas === []) {
                $consulta->whereRaw('1 = 0');

                return;
            }

            $consulta->whereIn($columna, $tiendas);
        });

        static::creating(function (Model $modelo) {
            if (! $modelo->getAttribute('tienda_id')) {
                $modelo->setAttribute('tienda_id', Contexto::tiendaId());
            }
        });
    }

    public function tienda(): BelongsTo
    {
        return $this->belongsTo(Tienda::class);
    }

    /** Solo lo de un local concreto. Lo usan el POS, la caja y los informes. */
    public function scopeDeTienda(Builder $q, ?int $tiendaId): Builder
    {
        $tiendaId ??= Contexto::tiendaId();

        return $tiendaId
            ? $q->where($q->getModel()->getTable() . '.tienda_id', $tiendaId)
            : $q->whereRaw('1 = 0');
    }

    /** Consulta sin el filtro de empresa. Fácil de buscar a propósito. */
    public static function sinFiltroEmpresa(): Builder
    {
        return static::query()->withoutGlobalScope('empresa');
    }
}
