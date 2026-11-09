<?php

namespace App\Models;

use App\Exceptions\NominaInmutable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * NR-NOM-003/010 · La nómina de un período (tabla `nominas` del documento).
 *
 *   borrador ──aprobar──▶ aprobada ──registrar pago──▶ pagada
 *
 * Inmutabilidad (NR-NOM-010 E4): fuera del borrador solo se permite la
 * transición a pagada (y sus campos de pago). Cualquier otro UPDATE o un
 * DELETE lanza NominaInmutable y deja el intento en la bitácora. Las
 * correcciones se hacen con una nómina de ajuste (kind = ajuste).
 */
class Payroll extends Model
{
    use Auditable, PerteneceATienda;

    public const BORRADOR = 'borrador', APROBADA = 'aprobada', PAGADA = 'pagada';

    public const ESTADOS = [self::BORRADOR => 'Borrador', self::APROBADA => 'Aprobada', self::PAGADA => 'Pagada'];

    /** Lo único que puede cambiar en una nómina aprobada (registrar el pago). */
    private const CAMPOS_DE_PAGO = ['status', 'paid_by', 'paid_at', 'updated_at'];

    protected $fillable = [
        'tienda_id', 'number', 'period_start', 'period_end', 'kind', 'adjusts_payroll_id', 'status', 'employees_count',
        'total_earned', 'total_deductions', 'total_net', 'total_employer', 'parameters',
        'created_by', 'approved_by', 'approved_at', 'paid_by', 'paid_at', 'notes',
    ];

    protected $attributes = ['status' => self::BORRADOR, 'kind' => 'normal'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'approved_at' => 'datetime', 'paid_at' => 'datetime',
            'total_earned' => 'decimal:2', 'total_deductions' => 'decimal:2', 'total_net' => 'decimal:2',
            'total_employer' => 'decimal:2', 'parameters' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Payroll $n) {
            $antes = $n->getOriginal('status');

            if ($antes === self::BORRADOR) {
                return;
            }

            $cambios = array_keys($n->getDirty());
            $esPago  = $antes === self::APROBADA && $n->status === self::PAGADA
                && array_diff($cambios, self::CAMPOS_DE_PAGO) === [];

            if (! $esPago) {
                $n->intentoBloqueado('UPDATE', $n->getDirty());
                throw new NominaInmutable();
            }
        });

        static::deleting(function (Payroll $n) {
            if ($n->getOriginal('status') !== self::BORRADOR) {
                $n->intentoBloqueado('DELETE', []);
                throw new NominaInmutable();
            }
        });
    }

    /** NR-NOM-010 E4 · «…y registra el intento en audit_log». */
    public function intentoBloqueado(string $accion, array $cambios): void
    {
        $this->registrarAuditoria('bloqueado', ['status' => $this->getOriginal('status'), 'accion' => $accion], $cambios);
    }

    public function esBorrador(): bool
    {
        return $this->status === self::BORRADOR;
    }

    public function esAjuste(): bool
    {
        return $this->kind === 'ajuste';
    }

    public function employees(): HasMany
    {
        return $this->hasMany(PayrollEmployee::class);
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(PayrollItem::class, PayrollEmployee::class);
    }

    public function adjusts(): BelongsTo
    {
        return $this->belongsTo(self::class, 'adjusts_payroll_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(self::class, 'adjusts_payroll_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by')->withTrashed();
    }

    public function tienda(): BelongsTo
    {
        return $this->belongsTo(Tienda::class);
    }

    public function periodo(): string
    {
        return $this->period_start->translatedFormat('j M') . ' – ' . $this->period_end->translatedFormat('j M Y');
    }

    /** «2026-10» para nombres de archivo. */
    public function periodoCorto(): string
    {
        return $this->period_start->format('Y-m') . ($this->esAjuste() ? '-ajuste' . $this->id : '');
    }
}
