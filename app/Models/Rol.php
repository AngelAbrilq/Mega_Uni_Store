<?php

namespace App\Models;

use App\Support\Contexto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role as RolDeSpatie;

/**
 * Un rol: del sistema, o de un negocio.
 *
 * ── Las dos clases de rol ──
 *
 * `empresa_id` en nulo → rol del sistema. Los siete de siempre
 * (Superadministrador, Administrador, Supervisor, Cajero, Vendedor,
 * Bodeguero, Reportero). Sirven en cualquier rubro y los ve todo el mundo.
 *
 * `empresa_id` con número → rol de ese negocio. «Estilista» en la
 * peluquería, «Bodeguero de patio» en la ferretería. No existe para nadie
 * más: no aparece en su desplegable, no se les puede asignar, no se
 * enteran de que existe.
 *
 * ── Por qué se extiende la clase de spatie ──
 *
 * Porque hay un punto donde el paquete busca un rol por nombre —al hacer
 * `assignRole('Cajero')`— y ese punto tiene que saber en qué negocio
 * estamos. Si no, dos peluquerías que le pusieron «Estilista» a su rol se
 * pisarían: la de Angel podría terminar asignando el rol de la otra.
 *
 * Todo pasa por `findByParam`, que es el único sitio donde spatie busca.
 * Cambiándolo ahí quedan cubiertos `findByName`, `findById`, `findOrCreate`
 * y `create` de una sola vez.
 *
 * ── Lo que este archivo NO hace ──
 *
 * No pone un filtro global. Sería lo elegante, pero un filtro global sobre
 * los roles es lo que hace que alguien pierda sus permisos cuando el
 * contexto no es el que se esperaba —y quedarse sin permisos por un
 * descuido del contexto es un daño mucho peor que ver el nombre de un rol
 * ajeno en una lista—.
 *
 * En su lugar hay `visibles()`, que se escribe a mano en los tres sitios
 * donde se listan roles. Son tres, están a la vista, y `grep visibles`
 * los muestra todos.
 */
class Rol extends RolDeSpatie
{
    /** El negocio dueño de este rol, o nada si es del sistema. */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /* ─────────────── Consultas ─────────────── */

    /**
     * Los roles que este negocio puede ver y asignar.
     *
     * Los del sistema más los suyos. Nunca los de otro.
     */
    public function scopeVisibles(Builder $consulta, ?int $empresaId = null): Builder
    {
        $empresaId ??= Contexto::empresaId();

        if ($empresaId === null) {
            return $consulta;
        }

        return $consulta->where(
            fn (Builder $q) => $q->whereNull('roles.empresa_id')
                ->orWhere('roles.empresa_id', $empresaId)
        );
    }

    /** Solo los que un negocio se creó para sí. */
    public function scopePropios(Builder $consulta, ?int $empresaId = null): Builder
    {
        $empresaId ??= Contexto::empresaId();

        return $consulta->where('roles.empresa_id', $empresaId);
    }

    /* ─────────────── Preguntas ─────────────── */

    /** ¿Es uno de los siete que trae el sistema? */
    public function esDelSistema(): bool
    {
        return $this->empresa_id === null;
    }

    /**
     * ¿Se puede borrar o renombrar?
     *
     * Los del sistema no. No es por proteger la base de datos: es que los
     * controladores exigen «Superadministrador» por nombre, y renombrarlo
     * dejaría a todo el mundo por fuera de todo.
     */
    public function esEditable(): bool
    {
        return ! $this->esDelSistema();
    }

    /* ─────────────── El punto donde spatie busca ─────────────── */

    /**
     * Buscar un rol, sin salirse del negocio.
     *
     * Es el método que usan por dentro `findByName`, `findById`,
     * `findOrCreate` y `create`. Se le agregan dos cosas:
     *
     *   1. Que solo mire los del sistema y los de esta empresa.
     *   2. Que si hay dos con el mismo nombre, gane el de la empresa.
     *
     * Lo segundo casi nunca pasa —crear un rol con el nombre de uno del
     * sistema lo impide `create`, que encuentra el global y se niega— pero
     * si un día pasa, es mejor que gane el más específico que dejarlo al
     * azar del orden de inserción.
     *
     * Sin contexto —consola, colas— no se filtra nada: ahí el que ejecuta
     * es el instalador, y lo que necesita es ver todo.
     */
    protected static function findByParam(array $params = []): ?RoleContract
    {
        $consulta = static::query();

        $empresaId = Contexto::empresaId();

        if ($empresaId !== null) {
            $consulta->where(
                fn (Builder $q) => $q->whereNull('empresa_id')->orWhere('empresa_id', $empresaId)
            );
        }

        foreach ($params as $campo => $valor) {
            $consulta->where($campo, $valor);
        }

        // `empresa_id IS NULL` da 0 para el rol propio y 1 para el del
        // sistema; ordenando de menor a mayor, el propio queda primero.
        return $consulta->orderByRaw('empresa_id IS NULL')->first();
    }
}
