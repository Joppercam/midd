<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PayrollSettings extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'pension_rate',
        'health_rate',
        'unemployment_rate',
        'overtime_rate',
        'family_allowance_amount',
        'tax_brackets',
        'working_hours',
        'holiday_calendar',
    ];

    protected $casts = [
        'pension_rate' => 'decimal:4',
        'health_rate' => 'decimal:4',
        'unemployment_rate' => 'decimal:4',
        'overtime_rate' => 'decimal:2',
        'family_allowance_amount' => 'decimal:2',
        'tax_brackets' => 'array',
        'working_hours' => 'array',
        'holiday_calendar' => 'array',
    ];

    /**
     * Get formatted rates as percentages
     */
    public function getFormattedPensionRateAttribute(): string
    {
        return ($this->pension_rate * 100) . '%';
    }

    public function getFormattedHealthRateAttribute(): string
    {
        return ($this->health_rate * 100) . '%';
    }

    public function getFormattedUnemploymentRateAttribute(): string
    {
        return ($this->unemployment_rate * 100) . '%';
    }
}