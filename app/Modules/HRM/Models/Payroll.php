<?php

namespace App\Modules\HRM\Models;

use App\Models\TenantAwareModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends TenantAwareModel
{
    protected $fillable = [
        'tenant_id',
        'payroll_number',
        'month',
        'year',
        'period_start',
        'period_end',
        'payment_date',
        'total_earnings',
        'total_deductions',
        'net_pay',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'paid_at'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(PayrollDetail::class);
    }

    public function items(): HasMany
    {
        return $this->details(); // Alias for details
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeByPeriod($query, $year, $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', ['approved', 'paid']);
    }

    public function getPeriodNameAttribute(): string
    {
        $monthNames = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        return $monthNames[$this->month] . ' ' . $this->year;
    }

    public function getEmployeeCountAttribute(): int
    {
        return $this->details()->count();
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->status === 'paid';
    }

    public function getIsEditableAttribute(): bool
    {
        return $this->status === 'draft';
    }

    public function calculateTotals(): void
    {
        $totals = $this->details()
            ->selectRaw('SUM(total_earnings) as earnings, SUM(total_deductions) as deductions, SUM(net_pay) as net')
            ->first();

        $this->update([
            'total_earnings' => $totals->earnings ?? 0,
            'total_deductions' => $totals->deductions ?? 0,
            'net_pay' => $totals->net ?? 0
        ]);
    }

    public function approve(User $user): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now()
        ]);
    }

    public function markAsPaid(): void
    {
        $this->update(['status' => 'paid']);
        
        $this->details()->update([
            'status' => 'paid',
            'paid_at' => now()
        ]);
    }
}