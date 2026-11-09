<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use PerteneceATienda;

    public const ABIERTA = 'abierta';
    public const CERRADA = 'cerrada';

    protected $fillable = [
        'tienda_id', 'cash_register_id', 'user_id', 'closed_by', 'opening_amount', 'expected_amount',
        'counted_amount', 'difference', 'status', 'notes', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_amount'  => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'counted_amount'  => 'decimal:2',
            'difference'      => 'decimal:2',
            'opened_at'       => 'datetime',
            'closed_at'       => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    /** Caja física donde se trabajó el turno (null si el local no usa cajas). */
    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    /** Ingresos y retiros de efectivo durante el turno. */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->orderBy('created_at');
    }

    /** Neto de ingresos menos retiros del turno. */
    public function netoMovimientos(): float
    {
        $ingresos = (float) $this->movements()->where('type', CashMovement::INGRESO)->sum('amount');
        $retiros  = (float) $this->movements()->where('type', CashMovement::RETIRO)->sum('amount');

        return round($ingresos - $retiros, 2);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeAbierta(Builder $q): Builder
    {
        return $q->where('status', self::ABIERTA);
    }

    /** El turno abierto de un usuario, si lo tiene. */
    public static function abiertaDe(int $userId): ?self
    {
        return static::abierta()->where('user_id', $userId)->latest('opened_at')->first();
    }

    /**
     * Efectivo esperado: base inicial + lo cobrado en efectivo − el cambio.
     *
     * ── Por qué se resta el cambio (y por qué antes no cuadraba) ──
     *
     * `sale_payments.amount` guarda lo que el cliente ENTREGÓ, no lo que
     * la venta valía. Es correcto que sea así: el recibo tiene que poder
     * decir «recibido $30.000, cambio $11.436», y para eso hay que guardar
     * los treinta mil.
     *
     * Pero el arqueo pregunta otra cosa: cuánta plata debería haber en el
     * cajón. Y de esos treinta mil, once mil cuatrocientos treinta y seis
     * se le devolvieron al cliente. Sumando solo los pagos, el sistema
     * esperaba el billete completo y le marcaba al cajero un faltante del
     * tamaño exacto del cambio — en CADA venta en efectivo con vuelto.
     *
     * El cambio siempre sale del cajón y siempre es en efectivo, así que
     * se resta entero de este lado y no se reparte entre medios de pago.
     */
    public function calcularEsperado(): float
    {
        $efectivo = SalePayment::query()
            ->vigentes() // RN-32: un pago rechazado no está en el cajón
            ->whereHas('sale', fn ($q) => $q->where('cash_session_id', $this->id)
                                            ->where('status', Sale::PAGADA))
            ->whereHas('paymentMethod', fn ($q) => $q->where('name', 'like', '%fectivo%'))
            ->sum('amount');

        $cambio = Sale::query()
            ->where('cash_session_id', $this->id)
            ->where('status', Sale::PAGADA)
            ->sum('change_amount');

        // AT-CAJ-001: lo que entró o salió del cajón sin ser venta.
        return round((float) $this->opening_amount + (float) $efectivo - (float) $cambio + $this->netoMovimientos(), 2);
    }

    public function getAbiertaAttribute(): bool
    {
        return $this->status === self::ABIERTA;
    }
}
