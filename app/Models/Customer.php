<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;
use App\Traits\InvalidatesCache;

class Customer extends TenantAwareModel
{
    use HasFactory, Auditable, InvalidatesCache;

    protected $fillable = [
        'rut',
        'name',
        'type',
        'email',
        'phone',
        'address',
        'commune',
        'city',
        'business_activity',
        'contact_name',
        'notes',
        'credit_limit',
        'payment_term_days',
        'is_active',
    ];

    protected $casts = [
        'address' => 'array',
        'credit_limit' => 'decimal:2',
        'payment_term_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function taxDocuments(): HasMany
    {
        return $this->hasMany(TaxDocument::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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
    
    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();
        
        // Invalidate cache on create, update, or delete
        static::saved(function ($customer) {
            $customer->invalidateDashboardCache($customer->tenant_id);
        });
        
        static::deleted(function ($customer) {
            $customer->invalidateDashboardCache($customer->tenant_id);
        });
    }
}