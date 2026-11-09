<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AT-HOR-001 · Horario asignado a un empleado, con vigencia (historial). */
class EmployeeSchedule extends Model
{
    protected $table = 'employee_schedule';

    protected $fillable = ['employee_id', 'work_schedule_id', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    public function vigente(): bool
    {
        return $this->start_date->lte(today()) && ($this->end_date === null || $this->end_date->gte(today()));
    }
}
