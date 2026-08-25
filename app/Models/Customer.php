<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\ConImagen;
use App\Models\Concerns\PerteneceAEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * El cliente de la tienda.
 *
 * Es el MISMO registro para las dos caras del negocio: el que el cajero
 * escoge en el punto de venta y el que se registra en la tienda en línea.
 * Un solo cliente, una sola historia de compras.
 *
 * Hereda de Authenticatable —no de Model— porque puede iniciar sesión,
 * pero por el guard 'cliente', nunca por 'web'.
 */
class Customer extends Authenticatable
{
    use Auditable, ConImagen, Notifiable, PerteneceAEmpresa, SoftDeletes;
    protected $fillable = [
        'empresa_id', 'first_name', 'last_name', 'email', 'phone',
        'document_type', 'document_number', 'address', 'image_url',
    ];

    /** Nunca salen en un JSON ni en un dd() por accidente. */
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'          => 'hashed',
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
        ];
    }

    /** ¿Tiene cuenta para entrar a la tienda, o solo existe en el mostrador? */
    public function getTieneCuentaAttribute(): bool
    {
        return filled($this->password);
    }

    /** El avatar se arma con el nombre completo, no solo con el primero. */
    public function nombreVisible(): string
    {
        return $this->full_name;
    }

    /** Compras que ha hecho este cliente. */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class)->latest('sold_at');
    }

    /** Cuánto ha comprado en total (sin contar anuladas). */
    public function getTotalCompradoAttribute(): float
    {
        return (float) $this->sales()->where('status', Sale::PAGADA)->sum('total');
    }

    /** Nombre y apellido en una sola cadena. */
    public function getFullNameAttribute(): string
    {
        $n = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));

        return $n !== '' ? $n : 'Sin nombre';
    }

    /** "CC 1023456789" */
    public function getDocumentAttribute(): string
    {
        return trim(($this->document_type ?? '') . ' ' . ($this->document_number ?? ''));
    }

    public function scopeSearch(Builder $q, ?string $t): Builder
    {
        $t = trim((string) $t);

        if ($t === '') {
            return $q;
        }

        return $q->where(fn (Builder $s) => $s->where('first_name', 'like', "%{$t}%")
            ->orWhere('last_name', 'like', "%{$t}%")
            ->orWhere('email', 'like', "%{$t}%")
            ->orWhere('document_number', 'like', "%{$t}%")
            ->orWhere('phone', 'like', "%{$t}%"));
    }
}
