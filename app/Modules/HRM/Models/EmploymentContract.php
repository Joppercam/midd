<?php

namespace App\Modules\HRM\Models;

use App\Models\TenantAwareModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmploymentContract extends TenantAwareModel
{
    protected $fillable = [
        'employee_id',
        'position_id',
        'department_id',
        'contract_number',
        'type',
        'start_date',
        'end_date',
        'base_salary',
        'salary_type',
        'working_hours_per_week',
        'benefits',
        'terms',
        'status',
        'termination_date',
        'termination_reason'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'termination_date' => 'date',
        'base_salary' => 'decimal:2',
        'benefits' => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function payrollDetails(): HasMany
    {
        return $this->hasMany(PayrollDetail::class, 'contract_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('type', 'fixed_term')
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->start_date) return null;
        
        $end = $this->end_date ?? now();
        $diff = $this->start_date->diff($end);
        
        $parts = [];
        if ($diff->y > 0) $parts[] = $diff->y . ' año' . ($diff->y > 1 ? 's' : '');
        if ($diff->m > 0) $parts[] = $diff->m . ' mes' . ($diff->m > 1 ? 'es' : '');
        
        return implode(' y ', $parts) ?: 'Menos de un mes';
    }

    public function terminate(string $reason, ?string $date = null): void
    {
        $this->update([
            'status' => 'terminated',
            'termination_date' => $date ?? now(),
            'termination_reason' => $reason
        ]);
    }
}