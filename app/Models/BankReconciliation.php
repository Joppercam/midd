<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenant;

class BankReconciliation extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'bank_account_id',
        'tenant_id',
        'user_id',
        'reconciliation_date',
        'statement_start_date',
        'statement_end_date',
        'statement_balance',
        'system_balance',
        'difference',
        'status',
        'transactions_count',
        'matched_count',
        'unmatched_count',
        'adjustments',
        'notes',
        'completed_at',
        'approved_at',
        'approved_by'
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'statement_start_date' => 'date',
        'statement_end_date' => 'date',
        'statement_balance' => 'decimal:2',
        'system_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'adjustments' => 'array',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime'
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function matches()
    {
        return BankTransactionMatch::where('bank_reconciliation_id', $this->id)->get();
    }

    public function getTransactions()
    {
        return $this->bankAccount->transactions()
            ->whereBetween('transaction_date', [$this->statement_start_date, $this->statement_end_date])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    public function calculate(): void
    {
        $transactions = $this->getTransactions();
        
        $this->transactions_count = $transactions->count();
        $this->matched_count = $transactions->where('status', 'matched')->count();
        $this->unmatched_count = $transactions->where('status', 'pending')->count();
        
        // Calculate system balance
        $this->system_balance = $this->bankAccount->calculateSystemBalance($this->statement_end_date);
        
        // Add unreconciled transactions to get expected balance
        $unreconciledSum = $transactions->whereIn('status', ['pending', 'matched'])->sum('amount');
        $this->system_balance += $unreconciledSum;
        
        // Calculate difference
        $this->difference = $this->statement_balance - $this->system_balance;
        
        $this->save();
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
        
        // Mark all matched transactions as reconciled
        $transactions = $this->getTransactions();
        $transactions->where('status', 'matched')->each->markAsReconciled();
        
        // Update bank account reconciled balance
        $this->bankAccount->update([
            'reconciled_balance' => $this->statement_balance,
            'last_reconciled_date' => $this->reconciliation_date
        ]);
    }

    public function markAsApproved($userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $userId
        ]);
    }

    public function addAdjustment($description, $amount, $type = 'manual'): void
    {
        $adjustments = $this->adjustments ?? [];
        $adjustments[] = [
            'description' => $description,
            'amount' => $amount,
            'type' => $type,
            'created_at' => now()->toDateTimeString()
        ];
        
        $this->adjustments = $adjustments;
        $this->save();
        
        // Recalculate
        $this->calculate();
    }

    public function removeAdjustment($index): void
    {
        $adjustments = $this->adjustments ?? [];
        if (isset($adjustments[$index])) {
            array_splice($adjustments, $index, 1);
            $this->adjustments = $adjustments;
            $this->save();
            
            // Recalculate
            $this->calculate();
        }
    }

    public function getTotalAdjustments(): float
    {
        if (!$this->adjustments) {
            return 0;
        }
        
        return collect($this->adjustments)->sum('amount');
    }

    public function getAdjustedDifference(): float
    {
        return $this->difference - $this->getTotalAdjustments();
    }

    public function canBeCompleted(): bool
    {
        return $this->status === 'draft' && 
               abs($this->getAdjustedDifference()) < 0.01 &&
               $this->unmatched_count === 0;
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'draft' => 'Borrador',
            'completed' => 'Completado',
            'approved' => 'Aprobado',
            default => ucfirst($this->status)
        };
    }

    public function getProgress(): array
    {
        $total = $this->transactions_count ?: 1;
        
        return [
            'total' => $total,
            'matched' => $this->matched_count,
            'unmatched' => $this->unmatched_count,
            'percentage' => round(($this->matched_count / $total) * 100, 1)
        ];
    }
}