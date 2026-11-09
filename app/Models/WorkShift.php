<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** TBL-TUR-001 · Franja de un horario: día (1 = lunes) y horas. Hereda el local de su horario. */
class WorkShift extends Model
{
    protected $fillable = ['work_schedule_id', 'weekday', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    public function horas(): float
    {
        return round(Carbon::parse($this->start_time)->diffInMinutes(Carbon::parse($this->end_time)) / 60, 2);
    }

    public function getDiaAttribute(): string
    {
        return WorkSchedule::DIAS[$this->weekday] ?? '—';
    }

    public function getRangoAttribute(): string
    {
        return substr((string) $this->start_time, 0, 5) . ' – ' . substr((string) $this->end_time, 0, 5);
    }
}
