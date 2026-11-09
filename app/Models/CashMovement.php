<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAlPadre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrada o salida de efectivo durante un turno (AT-CAJ-001).
 *
 * Ingreso: plata que entra al cajón sin ser venta (más sencillo, un
 * préstamo de otra caja). Retiro: plata que sale sin ser vuelto ni
 * devolución (consignación, pago a un proveedor menor). Ambos ajustan el
 * efectivo esperado del arqueo.
 */
class CashMovement extends Model
{
    use PerteneceAlPadre;

    public const INGRESO = 'ingreso';
    public const RETIRO  = 'retiro';
    public const TIPOS   = [self::INGRESO, self::RETIRO];

    protected $fillable = ['cash_session_id', 'user_id', 'type', 'amount', 'reason'];

    /** Cuelga del turno: de ahí saca a qué negocio pertenece. */
    public function documentoPadre(): array
    {
        return [CashSession::class, 'cash_session_id'];
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Ingreso suma, retiro resta. */
    public function getEfectoAttribute(): float
    {
        return $this->type === self::RETIRO ? -(float) $this->amount : (float) $this->amount;
    }
}
