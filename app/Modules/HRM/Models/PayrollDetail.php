<?php

namespace App\Modules\HRM\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollDetail extends Model
{
    protected $table = 'payroll_items'; // Using payroll_items table
    
    protected $fillable = [
        'payroll_id',
        'employee_id',
        'base_salary',
        'worked_days',
        'worked_hours',
        'overtime_hours',
        'overtime_amount',
        'bonuses_amount',
        'other_earnings',
        'total_earnings',
        'afp_amount',
        'health_amount',
        'tax_amount',
        'other_deductions',
        'total_deductions',
        'net_pay',
        'bonuses',
        'deductions',
        'status',
        'paid_at'
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'worked_days' => 'decimal:2',
        'worked_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'bonuses_amount' => 'decimal:2',
        'other_earnings' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'afp_amount' => 'decimal:2',
        'health_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'bonuses' => 'array',
        'deductions' => 'array',
        'paid_at' => 'datetime'
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}