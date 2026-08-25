<?php

namespace App\Models;

use App\Models\Concerns\ConImagen;
use App\Models\Concerns\ConSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un negocio. Quien paga la suscripción.
 *
 * Es la frontera de aislamiento del sistema: dos empresas no comparten
 * absolutamente nada, ni siquiera del mismo dueño. La ferretería de
 * William y su almacén de colchones son dos empresas, porque tienen
 * compras aparte, inventario aparte, clientes aparte y factura aparte.
 *
 * Lo que sí puede ser compartido es la PERSONA: William entra una vez y
 * cambia de negocio con un botón. Por eso la pertenencia vive en la tabla
 * `empresa_usuario` y no en una columna de `users`.
 */
class Empresa extends Model
{
    use ConImagen, ConSlug, SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'nombre', 'slug', 'rubro',
        'razon_social', 'nit', 'direccion', 'ciudad', 'telefono', 'correo', 'logo',
        'estado', 'es_demo', 'expira_en',
        'plan_id', 'periodo', 'precio_pactado', 'tiendas_contratadas', 'suscrita_desde',
    ];

    protected function casts(): array
    {
        return [
            'es_demo'        => 'boolean',
            'expira_en'      => 'datetime',
            'precio_pactado' => 'decimal:2',
            'suscrita_desde' => 'date',
        ];
    }

    /* ─────────────── Relaciones ─────────────── */

    public function tiendas(): HasMany
    {
        return $this->hasMany(Tienda::class);
    }

    /** La tienda principal, o la primera que haya. */
    public function tiendaPrincipal(): ?Tienda
    {
        return $this->tiendas()
            ->orderByDesc('es_principal')
            ->orderBy('id')
            ->first();
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'empresa_usuario')
            ->withPivot('es_dueno')
            ->withTimestamps();
    }

    public function dueno(): ?User
    {
        return $this->usuarios()->wherePivot('es_dueno', true)->first();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function movimientosMrr(): HasMany
    {
        return $this->hasMany(MrrMovimiento::class)->orderByDesc('ocurrio_en')->orderByDesc('id');
    }

    /* ─────────────── Lo que paga ─────────────── */

    /**
     * Lo que este cliente aporta al ingreso recurrente mensual.
     *
     * Tres cosas, en este orden:
     *
     *   1. El precio pactado, si lo hay. Manda sobre todo: es lo que se le
     *      prometió a esta persona, y no se puede perder porque alguien
     *      actualizó la lista de precios.
     *   2. Si no, el del plan — dividido entre doce cuando paga anual.
     *   3. Más las sedes de más, que se cobran aparte.
     *
     * Una empresa sin plan aporta cero: no es que sea gratis, es que
     * todavía no se le ha puesto plan y contar un ingreso que nadie pactó
     * sería inventarse el número.
     */
    public function mrr(): float
    {
        if ($this->plan_id === null && $this->precio_pactado === null) {
            return 0.0;
        }

        $base = $this->precio_pactado !== null
            ? $this->normalizar((float) $this->precio_pactado)
            : ($this->plan?->mrr($this->periodo ?? 'mensual') ?? 0.0);

        return round($base + $this->cobroPorSedesExtra(), 2);
    }

    /**
     * Lo que suman las sedes que exceden lo que incluye el plan.
     *
     * Se cobra por lo CONTRATADO y no por las que existen: si el cliente
     * pagó tres sedes y solo abrió dos, sigue debiendo tres — y si abrió
     * cuatro sin avisar, eso es una conversación comercial, no un cobro
     * automático a sus espaldas.
     */
    public function cobroPorSedesExtra(): float
    {
        $plan = $this->plan;

        if (! $plan) {
            return 0.0;
        }

        $incluidas = $plan->limite('tiendas');

        if ($incluidas === null) {
            return 0.0;
        }

        $deMas = max(0, (int) ($this->tiendas_contratadas ?? 1) - $incluidas);

        return round($deMas * $this->normalizar((float) $plan->precio_tienda_extra), 2);
    }

    /** Un precio anual repartido en el mes que le toca. */
    private function normalizar(float $precio): float
    {
        return ($this->periodo ?? 'mensual') === 'anual' ? round($precio / 12, 2) : $precio;
    }

    /* ─────────────── Límites ─────────────── */

    /**
     * Cuántos de ese recurso puede tener, o null si no tiene tope.
     *
     * Sin plan, no hay tope. Es deliberado: ver la migración. Un cambio de
     * facturación no puede dejar a un negocio sin poder trabajar.
     */
    public function limite(string $recurso): ?int
    {
        return $this->plan?->limite($recurso);
    }

    /** Cuántos lleva usados. */
    public function usado(string $recurso): int
    {
        return match ($recurso) {
            'tiendas'    => $this->tiendas()->count(),
            'usuarios'   => $this->usuarios()->count(),
            'productos'  => Product::sinFiltroEmpresa()->where('empresa_id', $this->id)->count(),
            'ventas_mes' => Sale::sinFiltroEmpresa()
                ->whereIn('tienda_id', $this->tiendas()->select('id'))
                ->where('sold_at', '>=', now()->startOfMonth())
                ->count(),
            default      => 0,
        };
    }

    /** Cuántos le quedan. Null = ilimitado. */
    public function disponible(string $recurso): ?int
    {
        $limite = $this->limite($recurso);

        return $limite === null ? null : max(0, $limite - $this->usado($recurso));
    }

    /** ¿Le cabe uno más? */
    public function puedeAgregar(string $recurso, int $cuantos = 1): bool
    {
        $limite = $this->limite($recurso);

        if ($limite === null) {
            return true;
        }

        return $this->usado($recurso) + $cuantos <= $limite;
    }

    /** ¿El plan incluye esta función? Sin plan, se permite todo. */
    public function incluye(string $funcion): bool
    {
        return $this->plan === null || $this->plan->incluye($funcion);
    }

    /* ─────────────── Estado ─────────────── */

    public function estaActiva(): bool
    {
        return $this->estado === 'activa' && ! $this->haVencido();
    }

    /**
     * ¿Se le acabó el tiempo?
     *
     * Solo aplica a demos y pruebas: una empresa de verdad tiene
     * `expira_en` en nulo y no vence nunca.
     */
    public function haVencido(): bool
    {
        return $this->expira_en !== null && $this->expira_en->isPast();
    }

    /** Minutos que le quedan a una demo. Null si no vence. */
    public function minutosRestantes(): ?int
    {
        if ($this->expira_en === null) {
            return null;
        }

        return max(0, (int) now()->diffInMinutes($this->expira_en, false));
    }

    /* ─────────────── Presentación ─────────────── */

    /** El logo de la empresa vive en `logo`, no en `image_url`. */
    public function campoImagen(): string
    {
        return 'logo';
    }

    public function nombreVisible(): string
    {
        return (string) $this->nombre;
    }

    public function campoSlug(): string
    {
        return 'nombre';
    }

    /**
     * En tu panel las empresas se abren por id (/empresas/7); la dirección
     * bonita por slug queda para el portal público del paso 5. Se aceptan
     * las dos: si el valor es un número es el id, si no es el slug.
     */
    public function resolveRouteBinding($valor, $campo = null)
    {
        if ($campo === null && ctype_digit((string) $valor)) {
            return $this->whereKey($valor)->firstOrFail();
        }

        return parent::resolveRouteBinding($valor, $campo);
    }
}
