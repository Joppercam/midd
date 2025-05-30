<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenant;
use App\Traits\Auditable;

class BankAccount extends Model
{
    use HasFactory, BelongsToTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'name',
        'account_number',
        'bank_name',
        'account_type',
        'currency',
        'current_balance',
        'reconciled_balance',
        'last_reconciled_date',
        'is_active',
        'notes'
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'reconciled_balance' => 'decimal:2',
        'last_reconciled_date' => 'date',
        'is_active' => 'boolean'
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class);
    }

    public function getLatestReconciliation()
    {
        return $this->reconciliations()
            ->where('status', 'completed')
            ->orderBy('reconciliation_date', 'desc')
            ->first();
    }

    public function updateBalance(): void
    {
        $lastTransaction = $this->transactions()
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastTransaction && $lastTransaction->balance !== null) {
            $this->current_balance = $lastTransaction->balance;
            $this->save();
        }
    }

    public function getUnreconciledTransactions()
    {
        return $this->transactions()
            ->whereIn('status', ['pending', 'matched'])
            ->orderBy('transaction_date', 'asc')
            ->get();
    }

    public function getAccountTypeLabel(): string
    {
        return match($this->account_type) {
            'checking' => 'Cuenta Corriente',
            'savings' => 'Cuenta de Ahorros',
            'credit_card' => 'Tarjeta de Crédito',
            default => ucfirst($this->account_type)
        };
    }

    public function calculateSystemBalance($untilDate = null): float
    {
        $query = $this->transactions()->where('status', 'reconciled');
        
        if ($untilDate) {
            $query->where('transaction_date', '<=', $untilDate);
        }
        
        $lastTransaction = $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
            
        return $lastTransaction ? $lastTransaction->balance : 0;
    }

    public function importTransactions(array $transactions, array $options = []): array
    {
        $imported = 0;
        $duplicates = 0;
        $errors = [];

        foreach ($transactions as $transaction) {
            try {
                $existing = $this->transactions()
                    ->where('external_id', $transaction['external_id'] ?? null)
                    ->orWhere(function ($query) use ($transaction) {
                        $query->where('transaction_date', $transaction['transaction_date'])
                            ->where('amount', $transaction['amount'])
                            ->where('description', $transaction['description']);
                    })
                    ->first();

                if (!$existing) {
                    $this->transactions()->create([
                        'tenant_id' => $this->tenant_id,
                        'transaction_date' => $transaction['transaction_date'],
                        'value_date' => $transaction['value_date'] ?? $transaction['transaction_date'],
                        'reference' => $transaction['reference'] ?? null,
                        'description' => $transaction['description'],
                        'amount' => $transaction['amount'],
                        'balance' => $transaction['balance'] ?? null,
                        'transaction_type' => $this->determineTransactionType($transaction['amount']),
                        'category' => $transaction['category'] ?? null,
                        'external_id' => $transaction['external_id'] ?? null,
                        'status' => 'pending',
                        'metadata' => $transaction['metadata'] ?? null
                    ]);
                    $imported++;
                } else {
                    $duplicates++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'transaction' => $transaction,
                    'error' => $e->getMessage()
                ];
            }
        }

        $this->updateBalance();

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'errors' => $errors,
            'total' => count($transactions)
        ];
    }

    private function determineTransactionType($amount): string
    {
        return $amount > 0 ? 'deposit' : 'withdrawal';
    }
}