<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * AT-EMP-001 · Un empleado del local (RN-44).
 *
 * Puede estar vinculado a un usuario del sistema (quien además entra al
 * panel) o no (quien solo figura en el equipo). `scopeActivos()` es la
 * vista `v_empleados_activos` (VW-EMP-001): sin borrar y en estado activo.
 */
class Employee extends Model
{
    use Auditable, PerteneceATienda, SoftDeletes;

    public const ACTIVO   = 'activo';
    public const INACTIVO = 'inactivo';

    protected $fillable = [
        'tienda_id', 'user_id', 'code', 'first_name', 'last_name', 'document_type', 'document_number',
        'phone', 'email', 'hire_date', 'base_salary', 'status', 'termination_date', 'termination_reason',
    ];

    protected $attributes = ['status' => self::ACTIVO];

    protected function casts(): array
    {
        return [
            'hire_date'        => 'date',
            'termination_date' => 'date',
            'base_salary'      => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Historial de cargos (empleados_cargos). */
    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Position::class, 'employee_position')
            ->withPivot(['id', 'start_date', 'end_date', 'is_active'])->withTimestamps()
            ->orderByPivot('start_date', 'desc');
    }

    /** Áreas (empleados_areas). */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_employee')
            ->withPivot(['assigned_at', 'is_primary'])->withTimestamps()
            ->orderByPivot('is_primary', 'desc');
    }

    /** Horarios con su vigencia (empleados_horarios). */
    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class)->orderByDesc('start_date');
    }

    /** NR-NOM-001 · Contratos, el más reciente primero. */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class)->orderByDesc('start_date')->orderByDesc('id');
    }

    public function contratoActivo(): ?EmploymentContract
    {
        return $this->contracts->firstWhere('status', EmploymentContract::ACTIVO);
    }

    /** NR-NOM-008 · Desprendibles (una fila por nómina liquidada). */
    public function payslips(): HasMany
    {
        return $this->hasMany(PayrollEmployee::class);
    }

    public function cargoActual(): ?Position
    {
        return $this->positions->first(fn ($p) => (bool) $p->pivot->is_active);
    }

    public function areaPrincipal(): ?Department
    {
        return $this->departments->first(fn ($d) => (bool) $d->pivot->is_primary);
    }

    public function horarioActual(): ?EmployeeSchedule
    {
        return $this->scheduleAssignments->first(fn ($a) => $a->vigente());
    }

    /** VW-EMP-001 · v_empleados_activos. */
    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('status', self::ACTIVO);
    }

    public function scopeSearch(Builder $q, ?string $t): Builder
    {
        $t = trim((string) $t);

        return $t === '' ? $q : $q->where(fn ($w) => $w
            ->where('first_name', 'like', "%{$t}%")->orWhere('last_name', 'like', "%{$t}%")
            ->orWhere('code', 'like', "%{$t}%")->orWhere('document_number', 'like', "%{$t}%"));
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getInicialesAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1) . mb_substr((string) $this->last_name, 0, 1));
    }
}
