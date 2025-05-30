<?php

namespace App\Modules\POS\Models;

use App\Models\TenantAwareModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class POSTerminal extends TenantAwareModel
{
    protected $table = 'pos_terminals';
    
    protected $fillable = [
        'name',
        'identifier',
        'description',
        'location',
        'settings',
        'is_active',
        'last_sync_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Cash sessions for this terminal
     */
    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'terminal_id');
    }

    /**
     * Current active session
     */
    public function currentSession(): HasOne
    {
        return $this->hasOne(CashSession::class, 'terminal_id')
            ->where('status', 'open')
            ->latest();
    }

    /**
     * Transactions for this terminal
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(POSTransaction::class, 'terminal_id');
    }

    /**
     * Product shortcuts for this terminal
     */
    public function productShortcuts(): HasMany
    {
        return $this->hasMany(POSProductShortcut::class, 'terminal_id')
            ->orderBy('position');
    }

    /**
     * Check if terminal has an active session
     */
    public function hasActiveSession(): bool
    {
        return $this->currentSession()->exists();
    }

    /**
     * Get current cashier
     */
    public function getCurrentCashier(): ?User
    {
        $session = $this->currentSession;
        return $session ? $session->user : null;
    }

    /**
     * Update last sync timestamp
     */
    public function updateLastSync(): void
    {
        $this->update(['last_sync_at' => now()]);
    }

    /**
     * Get terminal status
     */
    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if ($this->hasActiveSession()) {
            return 'active';
        }

        return 'available';
    }

    /**
     * Get terminal status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'active' => 'green',
            'available' => 'blue',
            'inactive' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get sales today
     */
    public function getSalesTodayAttribute(): float
    {
        return $this->transactions()
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->sum('total');
    }

    /**
     * Get transactions count today
     */
    public function getTransactionsTodayAttribute(): int
    {
        return $this->transactions()
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * Scope for active terminals
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for terminals with active sessions
     */
    public function scopeWithActiveSessions($query)
    {
        return $query->whereHas('currentSession');
    }

    /**
     * Scope for available terminals (active but no session)
     */
    public function scopeAvailable($query)
    {
        return $query->active()->whereDoesntHave('currentSession');
    }
}