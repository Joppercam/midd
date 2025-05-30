<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenant;

class BankTransaction extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'bank_account_id',
        'tenant_id',
        'transaction_date',
        'value_date',
        'reference',
        'description',
        'amount',
        'balance',
        'transaction_type',
        'category',
        'external_id',
        'status',
        'metadata'
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'value_date' => 'date',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'metadata' => 'array'
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankTransactionMatch::class);
    }

    public function getMatch()
    {
        return $this->matches()->first();
    }

    public function isMatched(): bool
    {
        return $this->status === 'matched' || $this->status === 'reconciled';
    }

    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }

    public function markAsMatched(): void
    {
        $this->update(['status' => 'matched']);
    }

    public function markAsReconciled(): void
    {
        $this->update(['status' => 'reconciled']);
    }

    public function markAsIgnored(): void
    {
        $this->update(['status' => 'ignored']);
    }

    public function unmatch(): void
    {
        $this->matches()->delete();
        $this->update(['status' => 'pending']);
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Pendiente',
            'matched' => 'Conciliado',
            'reconciled' => 'Reconciliado',
            'ignored' => 'Ignorado',
            default => ucfirst($this->status)
        };
    }

    public function getTransactionTypeLabel(): string
    {
        return match($this->transaction_type) {
            'deposit' => 'Depósito',
            'withdrawal' => 'Retiro',
            'fee' => 'Comisión',
            'interest' => 'Interés',
            default => ucfirst($this->transaction_type)
        };
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnreconciled($query)
    {
        return $query->whereIn('status', ['pending', 'matched']);
    }

    public function scopeReconciled($query)
    {
        return $query->where('status', 'reconciled');
    }

    public function scopeDeposits($query)
    {
        return $query->where('amount', '>', 0);
    }

    public function scopeWithdrawals($query)
    {
        return $query->where('amount', '<', 0);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    public function findPotentialMatches($threshold = 0.7)
    {
        $potentialMatches = [];
        
        // Search in payments
        if ($this->amount > 0) {
            // Look for payment receipts
            $payments = Payment::where('tenant_id', $this->tenant_id)
                ->where('amount', abs($this->amount))
                ->whereDate('payment_date', '>=', $this->transaction_date->subDays(5))
                ->whereDate('payment_date', '<=', $this->transaction_date->addDays(5))
                ->whereDoesntHave('bankTransactionMatch')
                ->get();
                
            foreach ($payments as $payment) {
                $score = $this->calculateMatchScore($payment);
                if ($score >= $threshold) {
                    $potentialMatches[] = [
                        'model' => $payment,
                        'type' => 'payment_received',
                        'score' => $score,
                        'details' => $this->getMatchDetails($payment)
                    ];
                }
            }
        } else {
            // Look for expenses
            $expenses = Expense::where('tenant_id', $this->tenant_id)
                ->where('total_amount', abs($this->amount))
                ->whereDate('expense_date', '>=', $this->transaction_date->subDays(5))
                ->whereDate('expense_date', '<=', $this->transaction_date->addDays(5))
                ->whereDoesntHave('bankTransactionMatch')
                ->get();
                
            foreach ($expenses as $expense) {
                $score = $this->calculateMatchScore($expense);
                if ($score >= $threshold) {
                    $potentialMatches[] = [
                        'model' => $expense,
                        'type' => 'expense_payment',
                        'score' => $score,
                        'details' => $this->getMatchDetails($expense)
                    ];
                }
            }
        }
        
        // Sort by score descending
        usort($potentialMatches, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return $potentialMatches;
    }

    private function calculateMatchScore($model): float
    {
        $score = 0;
        $weights = [
            'amount' => 0.4,
            'date' => 0.3,
            'reference' => 0.3
        ];
        
        // Amount match
        $modelAmount = $model instanceof Payment ? $model->amount : $model->total_amount;
        if (abs($this->amount) == $modelAmount) {
            $score += $weights['amount'];
        }
        
        // Date match
        $modelDate = $model instanceof Payment ? $model->payment_date : $model->expense_date;
        $daysDiff = abs($this->transaction_date->diffInDays($modelDate));
        if ($daysDiff == 0) {
            $score += $weights['date'];
        } elseif ($daysDiff <= 2) {
            $score += $weights['date'] * 0.5;
        } elseif ($daysDiff <= 5) {
            $score += $weights['date'] * 0.2;
        }
        
        // Reference match
        if ($this->reference && $model->reference) {
            similar_text(strtolower($this->reference), strtolower($model->reference), $percent);
            $score += $weights['reference'] * ($percent / 100);
        }
        
        return $score;
    }

    private function getMatchDetails($model): array
    {
        return [
            'amount_match' => abs($this->amount) == ($model instanceof Payment ? $model->amount : $model->total_amount),
            'date_diff' => $this->transaction_date->diffInDays($model instanceof Payment ? $model->payment_date : $model->expense_date),
            'reference_similarity' => $this->reference && $model->reference ? 
                similar_text(strtolower($this->reference), strtolower($model->reference)) : 0
        ];
    }
}