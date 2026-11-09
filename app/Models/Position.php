<?php

namespace App\Models;

use App\Models\Concerns\PerteneceATienda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** AT-CGO-001 · Cargo con nivel jerárquico (1 = el más alto) (RN-44). */
class Position extends Model
{
    use PerteneceATienda;

    protected $fillable = ['tienda_id', 'name', 'description', 'level', 'is_active'];

    protected $attributes = ['is_active' => true, 'level' => 5];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'level' => 'integer'];
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_position')
            ->withPivot(['start_date', 'end_date', 'is_active'])->withTimestamps();
    }
}
