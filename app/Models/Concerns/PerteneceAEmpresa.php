<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Support\Contexto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Este registro es de un negocio, y nadie de otro negocio lo puede ver.
 *
 * ── El filtro que no se puede olvidar ──
 *
 * El `addGlobalScope` de abajo le pega un `where empresa_id = …` a TODA
 * consulta que salga de este modelo: las del controlador, las de los
 * informes, las de las relaciones, las que escriba alguien dentro de seis
 * meses sin haber leído esto.
 *
 * Es la pieza que hace segura la decisión de tener una sola base de datos
 * para todos los clientes. La alternativa —acordarse de escribir el filtro
 * en cada consulta— falla el día que alguien escriba una y se le olvide, y
 * una consulta sin filtro no da error: devuelve de más. Los datos de otro
 * cliente, en la pantalla equivocada.
 *
 * ── Cuándo NO filtra ──
 *
 * Cuando no hay contexto: comandos de consola, colas, migraciones. Ahí no
 * hay «usuario actual» del que deducir la empresa, y filtrar por nada
 * dejaría sin datos al respaldo y a las alertas nocturnas.
 *
 * Ese hueco está cerrado por el otro lado: el middleware exige contexto en
 * toda petición web, y si un usuario no tiene empresa se le niega la
 * entrada en vez de dejarlo pasar sin filtro.
 *
 * Para las pocas veces que hace falta a propósito —el panel de
 * superadministrador, un comando que recorre clientes— están
 * `Contexto::sinFiltro()` y `Contexto::comoEmpresa()`, que son explícitas
 * y se ven al leer el código.
 */
trait PerteneceAEmpresa
{
    protected static function bootPerteneceAEmpresa(): void
    {
        static::addGlobalScope('empresa', function (Builder $consulta) {
            if (Contexto::sinFiltroActivo()) {
                return;
            }

            $empresaId = Contexto::empresaId();

            if ($empresaId === null) {
                return;
            }

            // Con el nombre de la tabla por delante: sin eso, una consulta
            // que se una con otra tabla que también tenga `empresa_id`
            // falla con «Column 'empresa_id' in where clause is ambiguous».
            $consulta->where(
                $consulta->getModel()->getTable() . '.empresa_id',
                $empresaId
            );
        });

        static::creating(function (Model $modelo) {
            if (! $modelo->getAttribute('empresa_id')) {
                $modelo->setAttribute('empresa_id', Contexto::empresaId());
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Consulta sin el filtro, para cuando de verdad hace falta.
     *
     * Se escribe así —y no quitando el scope a mano en cada sitio— para
     * que sea fácil de buscar: `grep sinFiltroEmpresa` muestra todos los
     * lugares donde el aislamiento se levanta a propósito.
     */
    public static function sinFiltroEmpresa(): Builder
    {
        return static::query()->withoutGlobalScope('empresa');
    }
}
