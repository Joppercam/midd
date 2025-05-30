<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EmploymentContract extends TenantAwareModel
{
    protected $fillable = [
        'employee_id',
        'department_id',
        'position_id',
        'contract_number',
        'contract_type',
        'start_date',
        'end_date',
        'base_salary',
        'work_hours_per_week',
        'benefits',
        'allowances',
        'terms_and_conditions',
        'status',
        'terminated_at',
        'termination_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'terminated_at' => 'date',
        'base_salary' => 'decimal:2',
        'work_hours_per_week' => 'integer',
        'benefits' => 'array',
        'allowances' => 'array',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_TERMINATED = 'terminated';
    const STATUS_SUSPENDED = 'suspended';

    const STATUSES = [
        self::STATUS_ACTIVE => 'Activo',
        self::STATUS_TERMINATED => 'Terminado',
        self::STATUS_SUSPENDED => 'Suspendido',
    ];

    const CONTRACT_TYPES = [
        'indefinite' => 'Indefinido',
        'fixed_term' => 'Plazo Fijo',
        'part_time' => 'Part Time',
        'temporary' => 'Temporal',
        'internship' => 'Práctica',
    ];

    /**
     * Employee this contract belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Department for this contract
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Position for this contract
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get contract type label
     */
    public function getContractTypeLabelAttribute(): string
    {
        return self::CONTRACT_TYPES[$this->contract_type] ?? $this->contract_type;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get formatted base salary
     */
    public function getFormattedBaseSalaryAttribute(): string
    {
        return '$' . number_format($this->base_salary, 0, ',', '.');
    }

    /**
     * Get contract duration in months
     */
    public function getDurationInMonthsAttribute(): ?int
    {
        if (!$this->end_date) {
            return null;
        }

        return $this->start_date->diffInMonths($this->end_date);
    }

    /**
     * Get time in current position (months)
     */
    public function getTimeInPositionAttribute(): int
    {
        $endDate = $this->status === self::STATUS_TERMINATED && $this->terminated_at 
            ? $this->terminated_at 
            : now();

        return $this->start_date->diffInMonths($endDate);
    }

    /**
     * Check if contract is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if contract is expired
     */
    public function isExpired(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    /**
     * Check if contract is expiring soon (within 30 days)
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->end_date && 
               $this->end_date->isFuture() && 
               $this->end_date->diffInDays(now()) <= $days;
    }

    /**
     * Get hourly rate
     */
    public function getHourlyRateAttribute(): float
    {
        $monthlyHours = ($this->work_hours_per_week * 52) / 12; // Average monthly hours
        return $monthlyHours > 0 ? $this->base_salary / $monthlyHours : 0;
    }

    /**
     * Get daily rate
     */
    public function getDailyRateAttribute(): float
    {
        $workDaysPerMonth = 30; // Approximate
        return $this->base_salary / $workDaysPerMonth;
    }

    /**
     * Calculate prorated salary for partial month
     */
    public function calculateProratedSalary(Carbon $startDate, Carbon $endDate): float
    {
        $daysInMonth = $startDate->daysInMonth;
        $workedDays = $startDate->diffInDays($endDate) + 1;
        
        return ($this->base_salary / $daysInMonth) * $workedDays;
    }

    /**
     * Get total allowances amount
     */
    public function getTotalAllowancesAttribute(): float
    {
        if (!$this->allowances) {
            return 0;
        }

        return collect($this->allowances)->sum('amount');
    }

    /**
     * Get allowance by type
     */
    public function getAllowance(string $type): ?array
    {
        if (!$this->allowances) {
            return null;
        }

        return collect($this->allowances)->firstWhere('type', $type);
    }

    /**
     * Get benefit by type
     */
    public function getBenefit(string $type): ?array
    {
        if (!$this->benefits) {
            return null;
        }

        return collect($this->benefits)->firstWhere('type', $type);
    }

    /**
     * Terminate contract
     */
    public function terminate(string $reason, Carbon $terminationDate = null): void
    {
        $this->update([
            'status' => self::STATUS_TERMINATED,
            'terminated_at' => $terminationDate ?? now(),
            'termination_reason' => $reason,
        ]);

        // Update employee status if this was their only active contract
        $otherActiveContracts = $this->employee->contracts()
            ->where('id', '!=', $this->id)
            ->where('status', self::STATUS_ACTIVE)
            ->count();

        if ($otherActiveContracts === 0) {
            $this->employee->update(['status' => Employee::STATUS_TERMINATED]);
        }
    }

    /**
     * Scope for active contracts
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for expired contracts
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('end_date')
                    ->where('end_date', '<', now());
    }

    /**
     * Scope for expiring contracts
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('end_date')
                    ->where('end_date', '>', now())
                    ->where('end_date', '<=', now()->addDays($days));
    }

    /**
     * Scope by contract type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('contract_type', $type);
    }

    /**
     * Generate unique contract number
     */
    public static function generateContractNumber(): string
    {
        $tenant = tenant();
        $year = now()->format('Y');
        $prefix = $tenant->getSetting('contract_number_prefix', 'CONT');
        
        $lastContract = static::where('tenant_id', $tenant->id)
            ->where('contract_number', 'like', "{$prefix}{$year}%")
            ->orderBy('contract_number', 'desc')
            ->first();

        if ($lastContract) {
            $lastNumber = (int) substr($lastContract->contract_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $year . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($contract) {
            if (!$contract->contract_number) {
                $contract->contract_number = static::generateContractNumber();
            }
        });
    }
}