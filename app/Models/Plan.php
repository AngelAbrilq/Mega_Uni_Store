<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un plan: lo que cuesta y lo que le cabe.
 *
 * ── Nulo no es cero ──
 *
 * En los límites, `null` significa «sin límite» y `0` significaría
 * «ninguno». Es la distinción de la que dependen los planes de arriba: el
 * plan Empresa no limita productos, y eso no es lo mismo que permitir cero
 * productos. Todo el archivo trata el nulo como infinito, nunca como falta
 * de dato.
 *
 * ── El MRR normalizado ──
 *
 * Un cliente anual no aporta su cuota completa al MRR del mes: aporta la
 * doceava parte. Si no se normalizara, el mes en que alguien paga el año
 * se vería un pico que no es ingreso recurrente nuevo, sino el mismo
 * ingreso cobrado de golpe. Ese error hace que las proyecciones de los
 * meses siguientes salgan todas mal.
 */
class Plan extends Model
{
    protected $table = 'planes';

    protected $fillable = [
        'nombre', 'slug', 'descripcion',
        'precio_mensual', 'precio_anual', 'precio_tienda_extra',
        'max_tiendas', 'max_usuarios', 'max_productos', 'max_ventas_mes',
        'funciones', 'dias_prueba', 'es_demo', 'visible', 'orden',
    ];

    protected function casts(): array
    {
        return [
            'precio_mensual'      => 'decimal:2',
            'precio_anual'        => 'decimal:2',
            'precio_tienda_extra' => 'decimal:2',
            'funciones'           => 'array',
            'es_demo'             => 'boolean',
            'visible'             => 'boolean',
        ];
    }

    /** Los recursos que un plan puede limitar. */
    public const RECURSOS = ['tiendas', 'usuarios', 'productos', 'ventas_mes'];

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }

    /* ─────────────── Consultas ─────────────── */

    public function scopeVendibles(Builder $q): Builder
    {
        return $q->where('visible', true)->where('es_demo', false)->orderBy('orden');
    }

    /* ─────────────── Precio ─────────────── */

    /** Lo que se le cobra al cliente de una, según cómo pague. */
    public function precio(string $periodo = 'mensual'): float
    {
        return (float) ($periodo === 'anual' ? $this->precio_anual : $this->precio_mensual);
    }

    /**
     * Lo que este plan aporta al MRR.
     *
     * El anual se divide entre doce. Ver la nota de arriba: sin esa
     * división, el MRR sube y baja con la fecha de cobro en vez de con el
     * negocio.
     */
    public function mrr(string $periodo = 'mensual'): float
    {
        return $periodo === 'anual'
            ? round((float) $this->precio_anual / 12, 2)
            : (float) $this->precio_mensual;
    }

    /** Cuánto se ahorra pagando el año. Para mostrarlo en el comparativo. */
    public function ahorroAnual(): float
    {
        $doceMeses = (float) $this->precio_mensual * 12;

        return max(0, $doceMeses - (float) $this->precio_anual);
    }

    public function porcentajeAhorro(): float
    {
        $doceMeses = (float) $this->precio_mensual * 12;

        return $doceMeses > 0 ? round($this->ahorroAnual() / $doceMeses * 100) : 0;
    }

    /* ─────────────── Límites ─────────────── */

    /**
     * Cuántos de ese recurso permite, o null si no limita.
     *
     * @return int|null
     */
    public function limite(string $recurso): ?int
    {
        $columna = 'max_' . $recurso;

        if (! in_array($recurso, self::RECURSOS, true)) {
            return null;
        }

        $valor = $this->getAttribute($columna);

        return $valor === null ? null : (int) $valor;
    }

    public function limita(string $recurso): bool
    {
        return $this->limite($recurso) !== null;
    }

    /** ¿Incluye esta función? Sin lista de funciones, no incluye ninguna. */
    public function incluye(string $funcion): bool
    {
        return in_array($funcion, $this->funciones ?? [], true);
    }

    /** Texto corto del límite, para la tabla comparativa. */
    public function limiteTexto(string $recurso): string
    {
        $limite = $this->limite($recurso);

        return $limite === null ? 'Sin límite' : number_format($limite, 0, ',', '.');
    }
}
