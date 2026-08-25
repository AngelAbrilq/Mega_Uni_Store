<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un local de un negocio.
 *
 * Aquí ocurren las cosas: se vende, se abre caja, se recibe mercancía, se
 * mueve el kardex. La empresa dice QUÉ maneja el negocio; la tienda dice
 * QUÉ pasa y CUÁNTO hay en cada sitio.
 *
 * ── La identidad heredada ──
 *
 * Los campos de identidad —NIT, razón social, dirección— están en nulo
 * casi siempre, y entonces salen los de la empresa. Se llenan solo cuando
 * el local necesita los suyos: una cadena que factura con un NIT por
 * establecimiento, o simplemente un local con otra dirección y otro
 * teléfono.
 *
 * Es una decisión de diseño, no una comodidad: si el NIT viviera solo en
 * la empresa, un negocio con dos NIT habría que partirlo en dos empresas
 * aunque comparta catálogo y clientes.
 */
class Tienda extends Model
{
    use SoftDeletes;

    protected $table = 'tiendas';

    protected $fillable = [
        'empresa_id', 'nombre', 'slug', 'codigo',
        'razon_social', 'nit', 'direccion', 'ciudad', 'telefono', 'correo', 'logo',
        'es_principal', 'activa',
    ];

    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'activa'       => 'boolean',
        ];
    }

    /* ─────────────── Relaciones ─────────────── */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tienda_usuario')->withTimestamps();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /* ─────────────── Identidad heredada ─────────────── */

    /**
     * Devuelve el dato del local, y si está vacío el de la empresa.
     *
     *     $tienda->dato('nit')        →  el del local, o el de la empresa
     *     $tienda->dato('direccion')  →  igual
     */
    public function dato(string $campo): ?string
    {
        $propio = $this->getAttribute($campo);

        if ($propio !== null && $propio !== '') {
            return (string) $propio;
        }

        return $this->relationLoaded('empresa') || $this->empresa_id
            ? ($this->empresa?->getAttribute($campo) ?: null)
            : null;
    }

    /** Cómo se llama este local de cara al cliente, en el recibo. */
    public function nombreCompleto(): string
    {
        $empresa = $this->empresa?->nombre;

        if (! $empresa || $empresa === $this->nombre) {
            return (string) $this->nombre;
        }

        return $empresa . ' · ' . $this->nombre;
    }

    /**
     * ¿Este local factura con un NIT distinto al de su empresa?
     *
     * Lo pregunta el informe consolidado: cuando la respuesta es sí, sumar
     * las tiendas sirve para que el dueño sepa cómo va, pero ese número no
     * es un reporte fiscal — son contribuyentes distintos.
     */
    public function tieneNitPropio(): bool
    {
        return ! empty($this->nit) && $this->nit !== $this->empresa?->nit;
    }

    /**
     * Prefijo de los documentos de este local.
     *
     * La tienda principal lo deja vacío para que los V-000001 que ya
     * existen sigan siendo válidos; las que se abren después llevan el
     * suyo: V-B-000001.
     */
    public function prefijo(): string
    {
        return $this->codigo ? $this->codigo . '-' : '';
    }
}
