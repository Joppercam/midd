<?php

namespace App\Modules\POS\Models;

use App\Models\TenantAwareModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Terminal extends TenantAwareModel
{
    protected $table = 'pos_terminals';

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'email',
        'settings',
        'is_active'
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean'
    ];

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function printers(): HasMany
    {
        return $this->hasMany(Printer::class);
    }

    public function quickProducts(): HasMany
    {
        return $this->hasMany(QuickProduct::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function offlineQueue(): HasMany
    {
        return $this->hasMany(OfflineQueue::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getActiveCashRegistersAttribute()
    {
        return $this->cashRegisters()->where('is_active', true)->get();
    }

    public function getOpenCashSessionsAttribute()
    {
        return CashSession::whereIn('cash_register_id', $this->cashRegisters->pluck('id'))
            ->where('status', 'open')
            ->get();
    }

    public function getTodaysSalesAttribute(): float
    {
        return $this->transactions()
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereDate('created_at', now())
            ->sum('total');
    }

    public function getTodaysTransactionCountAttribute(): int
    {
        return $this->transactions()
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereDate('created_at', now())
            ->count();
    }

    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    public function getDefaultPrinter(string $type = 'receipt'): ?Printer
    {
        return $this->printers()
            ->where('type', $type)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    public function generateTransactionNumber(): string
    {
        $prefix = $this->code;
        $date = now()->format('Ymd');
        
        $lastTransaction = $this->transactions()
            ->whereDate('created_at', now())
            ->latest()
            ->first();
        
        if ($lastTransaction) {
            $lastNumber = intval(substr($lastTransaction->transaction_number, -4));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . '-' . $date . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}