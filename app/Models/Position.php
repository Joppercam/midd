<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends TenantAwareModel
{
    protected $fillable = [
        'department_id',
        'title',
        'description',
        'base_salary',
        'requirements',
        'is_active',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'requirements' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Department this position belongs to
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Employment contracts for this position
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }

    /**
     * Active contracts for this position
     */
    public function activeContracts(): HasMany
    {
        return $this->contracts()->where('status', EmploymentContract::STATUS_ACTIVE);
    }

    /**
     * Get employees in this position
     */
    public function employees()
    {
        return Employee::whereHas('currentContract', function($query) {
            $query->where('position_id', $this->id)
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
     * Get formatted base salary
     */
    public function getFormattedBaseSalaryAttribute(): string
    {
        return $this->base_salary ? '$' . number_format($this->base_salary, 0, ',', '.') : 'No definido';
    }

    /**
     * Get requirements summary
     */
    public function getRequirementsSummaryAttribute(): string
    {
        if (!$this->requirements) {
            return 'Sin requisitos específicos';
        }

        return implode(', ', array_slice($this->requirements, 0, 3)) . 
               (count($this->requirements) > 3 ? '...' : '');
    }

    /**
     * Scope for active positions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by department
     */
    public function scopeInDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope with employee counts
     */
    public function scopeWithEmployeeCounts($query)
    {
        return $query->withCount(['activeContracts as employee_count']);
    }
}