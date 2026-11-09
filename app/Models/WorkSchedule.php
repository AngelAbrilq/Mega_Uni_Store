<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** AT-HOR-001 · Horario (Turno mañana, Turno tarde…) con sus franjas (RN-44). */
class WorkSchedule extends Model
{
    use PerteneceATienda;

    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    protected $fillable = ['tienda_id', 'name', 'description', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(WorkShift::class)->orderBy('weekday')->orderBy('start_time');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeSchedule::class);
    }

    /** Empleados que lo tienen hoy. */
    public function asignacionesVigentes(): HasMany
    {
        return $this->assignments()->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', today()))
            ->where('start_date', '<=', today());
    }

    /** Horas por semana (suma de franjas). */
    public function horasSemana(): float
    {
        return round($this->shifts->sum(fn (WorkShift $s) => $s->horas()), 2);
    }
}
