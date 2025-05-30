<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends TenantAwareModel
{
    protected $fillable = [
        'name',
        'description',
        'manager_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Manager of this department
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Positions in this department
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * Active positions in this department
     */
    public function activePositions(): HasMany
    {
        return $this->positions()->where('is_active', true);
    }

    /**
     * Employment contracts in this department
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }

    /**
     * Active contracts in this department
     */
    public function activeContracts(): HasMany
    {
        return $this->contracts()->where('status', EmploymentContract::STATUS_ACTIVE);
    }

    /**
     * Get employees in this department
     */
    public function employees()
    {
        return Employee::whereHas('currentContract', function($query) {
            $query->where('department_id', $this->id)
                  ->where('status', EmploymentContract::STATUS_ACTIVE);
        });
    }

    /**
     * Get employee count
     */
    public function getEmployeeCountAttribute(): int
    {
        return $this->employees()->count();
    }

    /**
     * Get total payroll for this department
     */
    public function getTotalPayrollAttribute(): float
    {
        return $this->activeContracts()->sum('base_salary');
    }

    /**
     * Get average salary in this department
     */
    public function getAverageSalaryAttribute(): float
    {
        $count = $this->activeContracts()->count();
        return $count > 0 ? $this->total_payroll / $count : 0;
    }

    /**
     * Check if department has manager
     */
    public function hasManager(): bool
    {
        return $this->manager_id !== null && $this->manager !== null;
    }

    /**
     * Scope for active departments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope with employee counts
     */
    public function scopeWithEmployeeCounts($query)
    {
        return $query->withCount(['activeContracts as employee_count']);
    }
}