<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** AT-ARE-001 · Área de trabajo del local, con responsable (RN-44). */
class Department extends Model
{
    use PerteneceATienda;

    protected $fillable = ['tienda_id', 'name', 'description', 'manager_id', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id')->withTrashed();
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'department_employee')
            ->withPivot(['assigned_at', 'is_primary'])->withTimestamps();
    }
}
