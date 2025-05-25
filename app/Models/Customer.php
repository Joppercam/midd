<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends TenantAwareModel
{
    use HasFactory;

    protected $fillable = [
        'rut',
        'name',
        'email',
        'phone',
        'address',
        'credit_limit',
    ];

    protected $casts = [
        'address' => 'array',
        'credit_limit' => 'decimal:2',
    ];

    public function taxDocuments(): HasMany
    {
        return $this->hasMany(TaxDocument::class);
    }

    public function getTotalDebtAttribute(): float
    {
        return $this->taxDocuments()
            ->where('status', 'accepted')
            ->where('type', 'invoice')
            ->whereNull('paid_at')
            ->sum('total');
    }

    public function getAvailableCreditAttribute(): float
    {
        return max(0, $this->credit_limit - $this->total_debt);
    }

    public function getFormattedRutAttribute(): string
    {
        if (!$this->rut) return '';
        
        $rut = preg_replace('/[^0-9kK]/', '', $this->rut);
        if (strlen($rut) < 2) return $rut;
        
        $body = substr($rut, 0, -1);
        $dv = strtoupper(substr($rut, -1));
        
        return number_format($body, 0, '', '.') . '-' . $dv;
    }
}