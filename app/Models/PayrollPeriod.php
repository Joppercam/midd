<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class PayrollPeriod extends TenantAwareModel
{
    protected $fillable = [
        'name',
        'period_type',
        'start_date',
        'end_date',
        'status',
        'total_gross_pay',
        'total_deductions',
        'total_net_pay',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net_pay' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_PROCESSING = 'processing';
    const STATUS_CALCULATED = 'calculated';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';

    const STATUSES = [
        self::STATUS_DRAFT => 'Borrador',
        self::STATUS_PROCESSING => 'Procesando',
        self::STATUS_CALCULATED => 'Calculado',
        self::STATUS_APPROVED => 'Aprobado',
        self::STATUS_PAID => 'Pagado',
    ];

    const PERIOD_TYPES = [
        'monthly' => 'Mensual',
        'weekly' => 'Semanal',
        'biweekly' => 'Quincenal',
    ];

    /**
     * Employee who approved this period
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /**
     * Payslips for this period
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * Draft payslips
     */
    public function draftPayslips(): HasMany
    {
        return $this->payslips()->where('status', Payslip::STATUS_DRAFT);
    }

    /**
     * Approved payslips
     */
    public function approvedPayslips(): HasMany
    {
        return $this->payslips()->whereIn('status', [Payslip::STATUS_APPROVED, Payslip::STATUS_PAID]);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get period type label
     */
    public function getPeriodTypeLabelAttribute(): string
    {
        return self::PERIOD_TYPES[$this->period_type] ?? $this->period_type;
    }

    /**
     * Get formatted dates
     */
    public function getFormattedPeriodAttribute(): string
    {
        return $this->start_date->format('d/m/Y') . ' - ' . $this->end_date->format('d/m/Y');
    }

    /**
     * Get period duration in days
     */
    public function getDurationInDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Get working days in period (excluding weekends)
     */
    public function getWorkingDaysAttribute(): int
    {
        $workingDays = 0;
        $current = $this->start_date->copy();
        
        while ($current <= $this->end_date) {
            if ($current->isWeekday()) {
                $workingDays++;
            }
            $current->addDay();
        }
        
        return $workingDays;
    }

    /**
     * Get employee count
     */
    public function getEmployeeCountAttribute(): int
    {
        return $this->payslips()->distinct('employee_id')->count();
    }

    /**
     * Get average gross pay
     */
    public function getAverageGrossPayAttribute(): float
    {
        $count = $this->employee_count;
        return $count > 0 ? $this->total_gross_pay / $count : 0;
    }

    /**
     * Get average net pay
     */
    public function getAverageNetPayAttribute(): float
    {
        $count = $this->employee_count;
        return $count > 0 ? $this->total_net_pay / $count : 0;
    }

    /**
     * Get effective deduction rate
     */
    public function getEffectiveDeductionRateAttribute(): float
    {
        return $this->total_gross_pay > 0 ? 
            round(($this->total_deductions / $this->total_gross_pay) * 100, 2) : 0;
    }

    /**
     * Check if period can be processed
     */
    public function canBeProcessed(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if period can be approved
     */
    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_CALCULATED;
    }

    /**
     * Check if period is finalized
     */
    public function isFinalized(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PAID]);
    }

    /**
     * Mark as processing
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    /**
     * Mark as calculated
     */
    public function markAsCalculated(): void
    {
        $this->recalculateTotals();
        $this->update(['status' => self::STATUS_CALCULATED]);
    }

    /**
     * Approve period
     */
    public function approve(Employee $approver): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ]);

        // Approve all payslips
        $this->draftPayslips()->update(['status' => Payslip::STATUS_APPROVED]);
    }

    /**
     * Mark as paid
     */
    public function markAsPaid(): void
    {
        $this->update(['status' => self::STATUS_PAID]);
        
        // Mark all payslips as paid
        $this->approvedPayslips()->update(['status' => Payslip::STATUS_PAID]);
    }

    /**
     * Recalculate totals from payslips
     */
    public function recalculateTotals(): void
    {
        $payslips = $this->payslips;
        
        $this->update([
            'total_gross_pay' => $payslips->sum('total_earnings'),
            'total_deductions' => $payslips->sum('total_deductions'),
            'total_net_pay' => $payslips->sum('net_pay'),
        ]);
    }

    /**
     * Get payroll summary
     */
    public function getPayrollSummary(): array
    {
        $payslips = $this->payslips;
        
        return [
            'total_employees' => $payslips->count(),
            'total_gross_pay' => $payslips->sum('total_earnings'),
            'total_basic_pay' => $payslips->sum('basic_pay'),
            'total_overtime' => $payslips->sum('overtime_pay'),
            'total_allowances' => $payslips->sum(function($p) {
                return $p->family_allowance + $p->transport_allowance + 
                       $p->meal_allowance + $p->other_allowances;
            }),
            'total_bonuses' => $payslips->sum('bonus'),
            'total_commissions' => $payslips->sum('commission'),
            'total_pension' => $payslips->sum('pension_deduction'),
            'total_health' => $payslips->sum('health_deduction'),
            'total_unemployment' => $payslips->sum('unemployment_insurance'),
            'total_tax' => $payslips->sum('income_tax'),
            'total_other_deductions' => $payslips->sum('other_deductions'),
            'total_deductions' => $payslips->sum('total_deductions'),
            'total_net_pay' => $payslips->sum('net_pay'),
        ];
    }

    /**
     * Scope for current period
     */
    public function scopeCurrent($query)
    {
        $now = now();
        return $query->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now);
    }

    /**
     * Scope by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope by period type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('period_type', $type);
    }

    /**
     * Create period for date range
     */
    public static function createForDateRange(Carbon $startDate, Carbon $endDate, string $type = 'monthly'): self
    {
        $tenant = tenant();
        
        $name = match($type) {
            'monthly' => $startDate->format('F Y'),
            'weekly' => 'Semana ' . $startDate->weekOfYear . ' ' . $startDate->format('Y'),
            'biweekly' => 'Quincena ' . ($startDate->day <= 15 ? '1' : '2') . ' ' . $startDate->format('M Y'),
            default => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
        };

        return static::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'period_type' => $type,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => self::STATUS_DRAFT,
        ]);
    }
}