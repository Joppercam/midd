<?php

namespace App\Modules\POS\Models;

use App\Models\TenantAwareModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends TenantAwareModel
{
    protected $fillable = [
        'terminal_id',
        'user_id',
        'session_number',
        'opening_balance',
        'closing_balance',
        'expected_balance',
        'difference',
        'opening_cash_count',
        'closing_cash_count',
        'status',
        'opened_at',
        'closed_at',
        'opening_notes',
        'closing_notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'opening_cash_count' => 'array',
        'closing_cash_count' => 'array',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';
    const STATUS_SUSPENDED = 'suspended';

    /**
     * Terminal this session belongs to
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(POSTerminal::class, 'terminal_id');
    }

    /**
     * User (cashier) for this session
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Transactions in this session
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(POSTransaction::class, 'session_id');
    }

    /**
     * Close the session
     */
    public function close(float $closingBalance, ?array $closingCashCount = null, ?string $notes = null): void
    {
        $expectedBalance = $this->calculateExpectedBalance();
        $difference = $closingBalance - $expectedBalance;

        $this->update([
            'status' => self::STATUS_CLOSED,
            'closing_balance' => $closingBalance,
            'expected_balance' => $expectedBalance,
            'difference' => $difference,
            'closing_cash_count' => $closingCashCount,
            'closed_at' => now(),
            'closing_notes' => $notes,
        ]);
    }

    /**
     * Calculate expected balance
     */
    protected function calculateExpectedBalance(): float
    {
        // TODO: Implement cash movement tracking
        return $this->opening_balance;
    }

    /**
     * Get total sales for this session
     */
    public function getTotalSalesAttribute(): float
    {
        return $this->transactions()
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->sum('total');
    }

    /**
     * Check if session is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}