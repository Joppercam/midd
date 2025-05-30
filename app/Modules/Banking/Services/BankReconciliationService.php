<?php

namespace App\Modules\Banking\Services;

use App\Models\BankReconciliation;
use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\TaxDocument;
use App\Models\Payment;
use App\Models\Expense;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BankReconciliationService
{
    protected $matchingRules;

    public function __construct()
    {
        $this->matchingRules = config('banking.matching_rules', []);
    }

    public function startReconciliation(array $data): BankReconciliation
    {
        return DB::transaction(function () use ($data) {
            $reconciliation = BankReconciliation::create([
                'tenant_id' => auth()->user()->tenant_id,
                'user_id' => auth()->id(),
                'bank_account_id' => $data['bank_account_id'],
                'period_start' => $data['period_start'],
                'period_end' => $data['period_end'],
                'bank_starting_balance' => $data['bank_starting_balance'],
                'bank_ending_balance' => $data['bank_ending_balance'],
                'system_starting_balance' => $this->calculateSystemBalance($data['bank_account_id'], $data['period_start']),
                'system_ending_balance' => $this->calculateSystemBalance($data['bank_account_id'], $data['period_end']),
                'status' => 'pending',
            ]);

            $reconciliation->difference = $reconciliation->bank_ending_balance - $reconciliation->system_ending_balance;
            $reconciliation->save();

            return $reconciliation;
        });
    }

    public function findMatches(BankReconciliation $reconciliation): Collection
    {
        $transactions = BankTransaction::where('bank_account_id', $reconciliation->bank_account_id)
            ->whereBetween('date', [$reconciliation->period_start, $reconciliation->period_end])
            ->whereNull('matched_at')
            ->get();

        $suggestedMatches = collect();

        foreach ($transactions as $transaction) {
            $matches = $this->findPotentialMatches($transaction);
            if ($matches->isNotEmpty()) {
                $suggestedMatches->push([
                    'transaction' => $transaction,
                    'matches' => $matches,
                ]);
            }
        }

        return $suggestedMatches;
    }

    public function autoMatch(BankReconciliation $reconciliation): int
    {
        $matchedCount = 0;
        $transactions = BankTransaction::where('bank_account_id', $reconciliation->bank_account_id)
            ->whereBetween('date', [$reconciliation->period_start, $reconciliation->period_end])
            ->whereNull('matched_at')
            ->get();

        foreach ($transactions as $transaction) {
            $matches = $this->findPotentialMatches($transaction);
            
            // Only auto-match if there's exactly one match with high confidence
            if ($matches->count() === 1 && $matches->first()['confidence'] >= 90) {
                $this->createMatch($reconciliation, $transaction, $matches->first());
                $matchedCount++;
            }
        }

        return $matchedCount;
    }

    public function createMatch(BankReconciliation $reconciliation, BankTransaction $transaction, array $matchData): BankTransactionMatch
    {
        return DB::transaction(function () use ($reconciliation, $transaction, $matchData) {
            $match = BankTransactionMatch::create([
                'tenant_id' => auth()->user()->tenant_id,
                'bank_reconciliation_id' => $reconciliation->id,
                'bank_transaction_id' => $transaction->id,
                'matchable_type' => $matchData['type'],
                'matchable_id' => $matchData['id'],
                'amount' => $transaction->amount,
                'confidence_score' => $matchData['confidence'],
                'matched_by' => auth()->id(),
                'matched_at' => now(),
            ]);

            $transaction->update([
                'matched_at' => now(),
                'status' => 'matched',
            ]);

            // Update the matchable entity
            $this->updateMatchableEntity($matchData['type'], $matchData['id'], $transaction);

            return $match;
        });
    }

    public function completeReconciliation(BankReconciliation $reconciliation): void
    {
        DB::transaction(function () use ($reconciliation) {
            // Recalculate balances
            $reconciliation->system_ending_balance = $this->calculateSystemBalance(
                $reconciliation->bank_account_id,
                $reconciliation->period_end
            );
            $reconciliation->difference = $reconciliation->bank_ending_balance - $reconciliation->system_ending_balance;
            $reconciliation->status = 'completed';
            $reconciliation->completed_at = now();
            $reconciliation->save();

            // Update bank account balance
            $reconciliation->bankAccount->update([
                'current_balance' => $reconciliation->bank_ending_balance,
                'last_reconciled_at' => now(),
            ]);
        });
    }

    protected function findPotentialMatches(BankTransaction $transaction): Collection
    {
        $matches = collect();

        // Match invoices/payments
        if ($transaction->type === 'deposit') {
            $matches = $matches->merge($this->matchInvoices($transaction));
            $matches = $matches->merge($this->matchPayments($transaction));
        }

        // Match expenses
        if ($transaction->type === 'withdrawal') {
            $matches = $matches->merge($this->matchExpenses($transaction));
        }

        return $matches->sortByDesc('confidence')->take(5);
    }

    protected function matchInvoices(BankTransaction $transaction): Collection
    {
        $dateRange = $this->getDateRange($transaction->date);
        
        return TaxDocument::where('tenant_id', $transaction->tenant_id)
            ->whereIn('type', ['factura_electronica', 'factura_exenta_electronica', 'boleta_electronica'])
            ->whereBetween('date', $dateRange)
            ->where(function ($query) use ($transaction) {
                $query->where('total', $transaction->amount)
                    ->orWhere('total', 'like', $this->getAmountVariations($transaction->amount));
            })
            ->get()
            ->map(function ($invoice) use ($transaction) {
                return [
                    'id' => $invoice->id,
                    'type' => TaxDocument::class,
                    'description' => "Invoice #{$invoice->number} - {$invoice->customer->name}",
                    'amount' => $invoice->total,
                    'date' => $invoice->date,
                    'confidence' => $this->calculateConfidence($transaction, $invoice),
                ];
            });
    }

    protected function matchPayments(BankTransaction $transaction): Collection
    {
        $dateRange = $this->getDateRange($transaction->date);
        
        return Payment::where('tenant_id', $transaction->tenant_id)
            ->whereBetween('date', $dateRange)
            ->where('amount', $transaction->amount)
            ->whereNull('bank_transaction_id')
            ->get()
            ->map(function ($payment) use ($transaction) {
                return [
                    'id' => $payment->id,
                    'type' => Payment::class,
                    'description' => "Payment from {$payment->customer->name}",
                    'amount' => $payment->amount,
                    'date' => $payment->date,
                    'confidence' => $this->calculateConfidence($transaction, $payment),
                ];
            });
    }

    protected function matchExpenses(BankTransaction $transaction): Collection
    {
        $dateRange = $this->getDateRange($transaction->date);
        
        return Expense::where('tenant_id', $transaction->tenant_id)
            ->whereBetween('date', $dateRange)
            ->where('amount', abs($transaction->amount))
            ->get()
            ->map(function ($expense) use ($transaction) {
                return [
                    'id' => $expense->id,
                    'type' => Expense::class,
                    'description' => "Expense to {$expense->supplier->name}",
                    'amount' => $expense->amount,
                    'date' => $expense->date,
                    'confidence' => $this->calculateConfidence($transaction, $expense),
                ];
            });
    }

    protected function calculateConfidence(BankTransaction $transaction, $entity): int
    {
        $confidence = 0;

        // Exact amount match
        if (abs($transaction->amount) == abs($entity->total ?? $entity->amount)) {
            $confidence += 50;
        }

        // Date proximity
        $daysDiff = Carbon::parse($transaction->date)->diffInDays($entity->date);
        if ($daysDiff === 0) {
            $confidence += 30;
        } elseif ($daysDiff <= 3) {
            $confidence += 20;
        } elseif ($daysDiff <= 7) {
            $confidence += 10;
        }

        // Reference match
        if ($transaction->reference && method_exists($entity, 'getReference')) {
            if (str_contains(strtolower($transaction->reference), strtolower($entity->getReference()))) {
                $confidence += 20;
            }
        }

        return min($confidence, 100);
    }

    protected function calculateSystemBalance(int $accountId, string $date): float
    {
        // This would calculate the system balance based on matched transactions
        // For now, returning a placeholder
        return 0;
    }

    protected function getDateRange(string $date): array
    {
        $carbonDate = Carbon::parse($date);
        return [
            $carbonDate->copy()->subDays(7)->format('Y-m-d'),
            $carbonDate->copy()->addDays(7)->format('Y-m-d'),
        ];
    }

    protected function getAmountVariations(float $amount): string
    {
        // Return variations for fuzzy matching (e.g., with tax variations)
        return '%' . number_format($amount * 0.95, 2, '.', '') . '%';
    }

    protected function updateMatchableEntity(string $type, int $id, BankTransaction $transaction): void
    {
        switch ($type) {
            case Payment::class:
                Payment::find($id)->update(['bank_transaction_id' => $transaction->id]);
                break;
            case TaxDocument::class:
                // Update payment status if fully paid
                $invoice = TaxDocument::find($id);
                if ($invoice->total <= $transaction->amount) {
                    $invoice->update(['payment_status' => 'paid']);
                }
                break;
        }
    }
}