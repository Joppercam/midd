<?php

namespace App\Modules\HRM\Models;

use App\Models\TenantAwareModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends TenantAwareModel
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'manager_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function employees()
    {
        return $this->hasManyThrough(
            Employee::class,
            EmploymentContract::class,
            'department_id',
            'id',
            'id',
            'employee_id'
        )->where('employment_contracts.status', 'active');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getEmployeeCountAttribute(): int
    {
        return $this->employees()->count();
    }
}