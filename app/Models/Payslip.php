<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends TenantAwareModel
{
    protected $fillable = [
        'employee_id',
        'payroll_period_id',
        'payslip_number',
        'pay_date',
        'base_salary',
        'worked_days',
        'total_days',
        'regular_hours',
        'overtime_hours',
        'basic_pay',
        'overtime_pay',
        'family_allowance',
        'transport_allowance',
        'meal_allowance',
        'other_allowances',
        'bonus',
        'commission',
        'total_earnings',
        'pension_deduction',
        'health_deduction',
        'unemployment_insurance',
        'income_tax',
        'other_deductions',
        'total_deductions',
        'net_pay',
        'detailed_earnings',
        'detailed_deductions',
        'status',
        'notes',
    ];

    protected $casts = [
        'pay_date' => 'date',
        'base_salary' => 'decimal:2',
        'worked_days' => 'decimal:1',
        'total_days' => 'decimal:1',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'basic_pay' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'family_allowance' => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'meal_allowance' => 'decimal:2',
        'other_allowances' => 'decimal:2',
        'bonus' => 'decimal:2',
        'commission' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'pension_deduction' => 'decimal:2',
        'health_deduction' => 'decimal:2',
        'unemployment_insurance' => 'decimal:2',
        'income_tax' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'detailed_earnings' => 'array',
        'detailed_deductions' => 'array',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';

    const STATUSES = [
        self::STATUS_DRAFT => 'Borrador',
        self::STATUS_APPROVED => 'Aprobado',
        self::STATUS_PAID => 'Pagado',
    ];

    /**
     * Employee this payslip belongs to
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Payroll period this payslip belongs to
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get formatted amounts
     */
    public function getFormattedBasicPayAttribute(): string
    {
        return '$' . number_format($this->basic_pay, 0, ',', '.');
    }

    public function getFormattedTotalEarningsAttribute(): string
    {
        return '$' . number_format($this->total_earnings, 0, ',', '.');
    }

    public function getFormattedTotalDeductionsAttribute(): string
    {
        return '$' . number_format($this->total_deductions, 0, ',', '.');
    }

    public function getFormattedNetPayAttribute(): string
    {
        return '$' . number_format($this->net_pay, 0, ',', '.');
    }

    /**
     * Get attendance percentage
     */
    public function getAttendancePercentageAttribute(): float
    {
        return $this->total_days > 0 ? round(($this->worked_days / $this->total_days) * 100, 1) : 0;
    }

    /**
     * Get effective tax rate
     */
    public function getEffectiveTaxRateAttribute(): float
    {
        return $this->total_earnings > 0 ? round(($this->income_tax / $this->total_earnings) * 100, 2) : 0;
    }

    /**
     * Get total social security deductions
     */
    public function getTotalSocialSecurityAttribute(): float
    {
        return $this->pension_deduction + $this->health_deduction + $this->unemployment_insurance;
    }

    /**
     * Get breakdown of earnings
     */
    public function getEarningsBreakdown(): array
    {
        $breakdown = [
            'Sueldo Base' => $this->basic_pay,
        ];

        if ($this->overtime_pay > 0) {
            $breakdown['Horas Extras'] = $this->overtime_pay;
        }

        if ($this->family_allowance > 0) {
            $breakdown['Asignación Familiar'] = $this->family_allowance;
        }

        if ($this->transport_allowance > 0) {
            $breakdown['Movilización'] = $this->transport_allowance;
        }

        if ($this->meal_allowance > 0) {
            $breakdown['Colación'] = $this->meal_allowance;
        }

        if ($this->bonus > 0) {
            $breakdown['Bonos'] = $this->bonus;
        }

        if ($this->commission > 0) {
            $breakdown['Comisiones'] = $this->commission;
        }

        if ($this->other_allowances > 0) {
            $breakdown['Otras Asignaciones'] = $this->other_allowances;
        }

        // Add detailed earnings if available
        if ($this->detailed_earnings) {
            foreach ($this->detailed_earnings as $item) {
                $breakdown[$item['name']] = $item['amount'];
            }
        }

        return $breakdown;
    }

    /**
     * Get breakdown of deductions
     */
    public function getDeductionsBreakdown(): array
    {
        $breakdown = [];

        if ($this->pension_deduction > 0) {
            $breakdown['AFP (10%)'] = $this->pension_deduction;
        }

        if ($this->health_deduction > 0) {
            $breakdown['Salud (7%)'] = $this->health_deduction;
        }

        if ($this->unemployment_insurance > 0) {
            $breakdown['Seguro Cesantía (0.6%)'] = $this->unemployment_insurance;
        }

        if ($this->income_tax > 0) {
            $breakdown['Impuesto a la Renta'] = $this->income_tax;
        }

        if ($this->other_deductions > 0) {
            $breakdown['Otros Descuentos'] = $this->other_deductions;
        }

        // Add detailed deductions if available
        if ($this->detailed_deductions) {
            foreach ($this->detailed_deductions as $item) {
                $breakdown[$item['name']] = $item['amount'];
            }
        }

        return $breakdown;
    }

    /**
     * Calculate net pay
     */
    public function calculateNetPay(): float
    {
        return $this->total_earnings - $this->total_deductions;
    }

    /**
     * Approve payslip
     */
    public function approve(): void
    {
        $this->update(['status' => self::STATUS_APPROVED]);
    }

    /**
     * Mark as paid
     */
    public function markAsPaid(): void
    {
        $this->update(['status' => self::STATUS_PAID]);
    }

    /**
     * Check if payslip can be edited
     */
    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if payslip is approved
     */
    public function isApproved(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PAID]);
    }

    /**
     * Check if payslip is paid
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Scope for approved payslips
     */
    public function scopeApproved($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PAID]);
    }

    /**
     * Scope for paid payslips
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope for draft payslips
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope by payroll period
     */
    public function scopeForPeriod($query, $periodId)
    {
        return $query->where('payroll_period_id', $periodId);
    }

    /**
     * Scope by employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Generate unique payslip number
     */
    public static function generatePayslipNumber(PayrollPeriod $period, Employee $employee): string
    {
        $tenant = tenant();
        $prefix = $tenant->getSetting('payslip_number_prefix', 'LIQ');
        
        $periodCode = $period->start_date->format('Ym');
        $employeeCode = str_pad($employee->employee_number, 4, '0', STR_PAD_LEFT);
        
        return $prefix . $periodCode . $employeeCode;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payslip) {
            if (!$payslip->payslip_number) {
                $payslip->payslip_number = static::generatePayslipNumber(
                    $payslip->payrollPeriod, 
                    $payslip->employee
                );
            }
        });

        static::saving(function ($payslip) {
            // Recalculate net pay
            $payslip->net_pay = $payslip->calculateNetPay();
        });
    }
}