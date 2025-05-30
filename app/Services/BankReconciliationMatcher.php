<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\BankTransactionMatch;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\TaxDocument;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class BankReconciliationMatcher
{
    /**
     * Match bank transactions with system records
     */
    public function matchTransactions(Collection $bankTransactions, int $tenantId, array $options = []): array
    {
        $matched = [];
        $unmatched = [];
        $suggestions = [];
        
        foreach ($bankTransactions as $transaction) {
            $result = $this->findMatch($transaction, $tenantId, $options);
            
            if ($result['match']) {
                $matched[] = $result;
            } else {
                $unmatched[] = $transaction;
                if (!empty($result['suggestions'])) {
                    $suggestions[$transaction->id] = $result['suggestions'];
                }
            }
        }
        
        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
            'suggestions' => $suggestions,
            'match_rate' => count($matched) / max(count($bankTransactions), 1) * 100
        ];
    }
    
    /**
     * Find match for a single bank transaction
     */
    public function findMatch(BankTransaction $transaction, int $tenantId, array $options = []): array
    {
        $dateRange = $options['date_range'] ?? 3; // Days before/after
        $amountTolerance = $options['amount_tolerance'] ?? 0.01; // 1 cent
        $minConfidence = $options['min_confidence'] ?? 0.8;
        
        // Try to match based on transaction type
        if ($transaction->transaction_type === 'credit') {
            return $this->matchCreditTransaction($transaction, $tenantId, $dateRange, $amountTolerance, $minConfidence);
        } else {
            return $this->matchDebitTransaction($transaction, $tenantId, $dateRange, $amountTolerance, $minConfidence);
        }
    }
    
    /**
     * Match credit transactions (income)
     */
    private function matchCreditTransaction(BankTransaction $transaction, int $tenantId, int $dateRange, float $amountTolerance, float $minConfidence): array
    {
        $matches = [];
        
        // 1. Try to match with payments
        $paymentMatches = $this->findPaymentMatches($transaction, $tenantId, $dateRange, $amountTolerance);
        foreach ($paymentMatches as $payment) {
            $confidence = $this->calculatePaymentConfidence($transaction, $payment);
            if ($confidence >= $minConfidence) {
                $matches[] = [
                    'type' => 'payment',
                    'record' => $payment,
                    'confidence' => $confidence,
                    'method' => $this->getMatchMethod($transaction, $payment)
                ];
            }
        }
        
        // 2. Try to match with invoices (direct payment)
        $invoiceMatches = $this->findInvoiceMatches($transaction, $tenantId, $dateRange, $amountTolerance);
        foreach ($invoiceMatches as $invoice) {
            $confidence = $this->calculateInvoiceConfidence($transaction, $invoice);
            if ($confidence >= $minConfidence) {
                $matches[] = [
                    'type' => 'invoice',
                    'record' => $invoice,
                    'confidence' => $confidence,
                    'method' => $this->getMatchMethod($transaction, $invoice)
                ];
            }
        }
        
        // Return best match
        if (!empty($matches)) {
            usort($matches, function ($a, $b) {
                return $b['confidence'] <=> $a['confidence'];
            });
            
            return [
                'match' => true,
                'type' => $matches[0]['type'],
                'record' => $matches[0]['record'],
                'confidence' => $matches[0]['confidence'],
                'method' => $matches[0]['method'],
                'suggestions' => array_slice($matches, 1, 3) // Top 3 alternatives
            ];
        }
        
        // No match found, return suggestions
        return [
            'match' => false,
            'suggestions' => $this->getSuggestions($transaction, $tenantId, 'credit')
        ];
    }
    
    /**
     * Match debit transactions (expenses)
     */
    private function matchDebitTransaction(BankTransaction $transaction, int $tenantId, int $dateRange, float $amountTolerance, float $minConfidence): array
    {
        $matches = [];
        
        // 1. Try to match with expenses
        $expenseMatches = $this->findExpenseMatches($transaction, $tenantId, $dateRange, $amountTolerance);
        foreach ($expenseMatches as $expense) {
            $confidence = $this->calculateExpenseConfidence($transaction, $expense);
            if ($confidence >= $minConfidence) {
                $matches[] = [
                    'type' => 'expense',
                    'record' => $expense,
                    'confidence' => $confidence,
                    'method' => $this->getMatchMethod($transaction, $expense)
                ];
            }
        }
        
        // 2. Try to match with supplier payments
        $supplierPaymentMatches = $this->findSupplierPaymentMatches($transaction, $tenantId, $dateRange, $amountTolerance);
        foreach ($supplierPaymentMatches as $payment) {
            $confidence = $this->calculateSupplierPaymentConfidence($transaction, $payment);
            if ($confidence >= $minConfidence) {
                $matches[] = [
                    'type' => 'supplier_payment',
                    'record' => $payment,
                    'confidence' => $confidence,
                    'method' => $this->getMatchMethod($transaction, $payment)
                ];
            }
        }
        
        // Return best match
        if (!empty($matches)) {
            usort($matches, function ($a, $b) {
                return $b['confidence'] <=> $a['confidence'];
            });
            
            return [
                'match' => true,
                'type' => $matches[0]['type'],
                'record' => $matches[0]['record'],
                'confidence' => $matches[0]['confidence'],
                'method' => $matches[0]['method'],
                'suggestions' => array_slice($matches, 1, 3)
            ];
        }
        
        return [
            'match' => false,
            'suggestions' => $this->getSuggestions($transaction, $tenantId, 'debit')
        ];
    }
    
    /**
     * Find payment matches
     */
    private function findPaymentMatches(BankTransaction $transaction, int $tenantId, int $dateRange, float $amountTolerance): Collection
    {
        $startDate = Carbon::parse($transaction->date)->subDays($dateRange);
        $endDate = Carbon::parse($transaction->date)->addDays($dateRange);
        
        return Payment::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->whereBetween('amount', [
                $transaction->amount - $amountTolerance,
                $transaction->amount + $amountTolerance
            ])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('bank_transaction_matches')
                    ->whereColumn('bank_transaction_matches.matchable_id', 'payments.id')
                    ->where('bank_transaction_matches.matchable_type', Payment::class);
            })
            ->get();
    }
    
    /**
     * Find invoice matches
     */
    private function findInvoiceMatches(BankTransaction $transaction, int $tenantId, int $dateRange, float $amountTolerance): Collection
    {
        $startDate = Carbon::parse($transaction->date)->subDays($dateRange);
        $endDate = Carbon::parse($transaction->date)->addDays($dateRange);
        
        return TaxDocument::where('tenant_id', $tenantId)
            ->whereIn('type', ['invoice', 'receipt'])
            ->whereBetween('total', [
                $transaction->amount - $amountTolerance,
                $transaction->amount + $amountTolerance
            ])
            ->where('status', 'accepted')
            ->whereNull('paid_at')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('issue_date', [$startDate, $endDate])
                    ->orWhereBetween('due_date', [$startDate, $endDate]);
            })
            ->get();
    }
    
    /**
     * Find expense matches
     */
    private function findExpenseMatches(BankTransaction $transaction, int $tenantId, int $dateRange, float $amountTolerance): Collection
    {
        $startDate = Carbon::parse($transaction->date)->subDays($dateRange);
        $endDate = Carbon::parse($transaction->date)->addDays($dateRange);
        
        return Expense::where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [$startDate, $endDate])
            ->whereBetween('total_amount', [
                $transaction->amount - $amountTolerance,
                $transaction->amount + $amountTolerance
            ])
            ->whereIn('status', ['pending', 'paid'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('bank_transaction_matches')
                    ->whereColumn('bank_transaction_matches.matchable_id', 'expenses.id')
                    ->where('bank_transaction_matches.matchable_type', Expense::class);
            })
            ->get();
    }
    
    /**
     * Find supplier payment matches
     */
    private function findSupplierPaymentMatches(BankTransaction $transaction, int $tenantId, int $dateRange, float $amountTolerance): Collection
    {
        // For now, return empty collection
        // This can be implemented when supplier payment module is ready
        return collect();
    }
    
    /**
     * Calculate confidence score for payment match
     */
    private function calculatePaymentConfidence(BankTransaction $transaction, Payment $payment): float
    {
        $confidence = 0.0;
        
        // Amount match (40% weight)
        $amountDiff = abs($transaction->amount - $payment->amount);
        $amountScore = max(0, 1 - ($amountDiff / $transaction->amount));
        $confidence += $amountScore * 0.4;
        
        // Date match (30% weight)
        $daysDiff = Carbon::parse($transaction->date)->diffInDays(Carbon::parse($payment->payment_date));
        $dateScore = max(0, 1 - ($daysDiff / 7)); // Lose confidence after 7 days
        $confidence += $dateScore * 0.3;
        
        // Reference match (20% weight)
        if ($this->referencesMatch($transaction->reference, $payment->reference)) {
            $confidence += 0.2;
        } elseif ($this->partialReferenceMatch($transaction->reference, $payment->reference)) {
            $confidence += 0.1;
        }
        
        // Description match (10% weight)
        if ($this->descriptionsMatch($transaction->description, $payment->customer->name ?? '')) {
            $confidence += 0.1;
        }
        
        return min(1.0, $confidence);
    }
    
    /**
     * Calculate confidence score for invoice match
     */
    private function calculateInvoiceConfidence(BankTransaction $transaction, TaxDocument $invoice): float
    {
        $confidence = 0.0;
        
        // Amount match (40% weight)
        $amountDiff = abs($transaction->amount - $invoice->total);
        $amountScore = max(0, 1 - ($amountDiff / $transaction->amount));
        $confidence += $amountScore * 0.4;
        
        // Date match (25% weight)
        $daysFromIssue = Carbon::parse($transaction->date)->diffInDays(Carbon::parse($invoice->issue_date));
        $daysFromDue = Carbon::parse($transaction->date)->diffInDays(Carbon::parse($invoice->due_date));
        $dateScore = max(0, 1 - (min($daysFromIssue, $daysFromDue) / 30)); // Lose confidence after 30 days
        $confidence += $dateScore * 0.25;
        
        // Customer name match (25% weight)
        if ($this->descriptionsMatch($transaction->description, $invoice->customer->name ?? '')) {
            $confidence += 0.25;
        }
        
        // Invoice number match (10% weight)
        if ($this->referencesMatch($transaction->reference, $invoice->number)) {
            $confidence += 0.1;
        }
        
        return min(1.0, $confidence);
    }
    
    /**
     * Calculate confidence score for expense match
     */
    private function calculateExpenseConfidence(BankTransaction $transaction, Expense $expense): float
    {
        $confidence = 0.0;
        
        // Amount match (40% weight)
        $amountDiff = abs($transaction->amount - $expense->total_amount);
        $amountScore = max(0, 1 - ($amountDiff / $transaction->amount));
        $confidence += $amountScore * 0.4;
        
        // Date match (30% weight)
        $daysDiff = Carbon::parse($transaction->date)->diffInDays(Carbon::parse($expense->issue_date));
        $dateScore = max(0, 1 - ($daysDiff / 10)); // Lose confidence after 10 days
        $confidence += $dateScore * 0.3;
        
        // Supplier name match (20% weight)
        if ($expense->supplier && $this->descriptionsMatch($transaction->description, $expense->supplier->name)) {
            $confidence += 0.2;
        }
        
        // Reference match (10% weight)
        if ($this->referencesMatch($transaction->reference, $expense->supplier_document_number)) {
            $confidence += 0.1;
        }
        
        return min(1.0, $confidence);
    }
    
    /**
     * Calculate confidence score for supplier payment match
     */
    private function calculateSupplierPaymentConfidence(BankTransaction $transaction, $payment): float
    {
        // Placeholder for supplier payment confidence calculation
        return 0.0;
    }
    
    /**
     * Check if references match
     */
    private function referencesMatch(string $ref1, string $ref2): bool
    {
        $ref1 = $this->normalizeReference($ref1);
        $ref2 = $this->normalizeReference($ref2);
        
        return !empty($ref1) && !empty($ref2) && $ref1 === $ref2;
    }
    
    /**
     * Check for partial reference match
     */
    private function partialReferenceMatch(string $ref1, string $ref2): bool
    {
        $ref1 = $this->normalizeReference($ref1);
        $ref2 = $this->normalizeReference($ref2);
        
        if (empty($ref1) || empty($ref2)) {
            return false;
        }
        
        return strpos($ref1, $ref2) !== false || strpos($ref2, $ref1) !== false;
    }
    
    /**
     * Check if descriptions match
     */
    private function descriptionsMatch(string $desc1, string $desc2): bool
    {
        $desc1 = $this->normalizeDescription($desc1);
        $desc2 = $this->normalizeDescription($desc2);
        
        if (empty($desc1) || empty($desc2)) {
            return false;
        }
        
        // Check exact match
        if ($desc1 === $desc2) {
            return true;
        }
        
        // Check if one contains the other
        if (strpos($desc1, $desc2) !== false || strpos($desc2, $desc1) !== false) {
            return true;
        }
        
        // Check similarity
        similar_text($desc1, $desc2, $percent);
        return $percent > 80;
    }
    
    /**
     * Normalize reference for comparison
     */
    private function normalizeReference(string $reference): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', strtoupper($reference));
    }
    
    /**
     * Normalize description for comparison
     */
    private function normalizeDescription(string $description): string
    {
        // Remove common words and normalize
        $stopWords = ['de', 'la', 'el', 'los', 'las', 'sa', 'srl', 'ltda', 'spa', 'eirl'];
        $words = explode(' ', strtolower($description));
        $filtered = array_filter($words, function ($word) use ($stopWords) {
            return !in_array($word, $stopWords) && strlen($word) > 2;
        });
        
        return implode(' ', $filtered);
    }
    
    /**
     * Get match method description
     */
    private function getMatchMethod($transaction, $record): string
    {
        $methods = [];
        
        // Amount match
        if (abs($transaction->amount - $this->getRecordAmount($record)) < 0.01) {
            $methods[] = 'exact_amount';
        } else {
            $methods[] = 'approximate_amount';
        }
        
        // Date proximity
        $daysDiff = Carbon::parse($transaction->date)->diffInDays($this->getRecordDate($record));
        if ($daysDiff === 0) {
            $methods[] = 'same_day';
        } elseif ($daysDiff <= 3) {
            $methods[] = 'close_date';
        }
        
        // Reference match
        if ($this->referencesMatch($transaction->reference, $this->getRecordReference($record))) {
            $methods[] = 'reference_match';
        }
        
        return implode(', ', $methods);
    }
    
    /**
     * Get record amount based on type
     */
    private function getRecordAmount($record): float
    {
        if ($record instanceof Payment) {
            return $record->amount;
        } elseif ($record instanceof TaxDocument) {
            return $record->total;
        } elseif ($record instanceof Expense) {
            return $record->total_amount;
        }
        
        return 0;
    }
    
    /**
     * Get record date based on type
     */
    private function getRecordDate($record): string
    {
        if ($record instanceof Payment) {
            return $record->payment_date;
        } elseif ($record instanceof TaxDocument) {
            return $record->issue_date;
        } elseif ($record instanceof Expense) {
            return $record->issue_date;
        }
        
        return now()->toDateString();
    }
    
    /**
     * Get record reference based on type
     */
    private function getRecordReference($record): string
    {
        if ($record instanceof Payment) {
            return $record->reference ?? '';
        } elseif ($record instanceof TaxDocument) {
            return $record->number;
        } elseif ($record instanceof Expense) {
            return $record->supplier_document_number ?? '';
        }
        
        return '';
    }
    
    /**
     * Get suggestions for unmatched transactions
     */
    private function getSuggestions(BankTransaction $transaction, int $tenantId, string $type): array
    {
        $suggestions = [];
        
        if ($type === 'credit') {
            // Suggest recent unpaid invoices
            $invoices = TaxDocument::where('tenant_id', $tenantId)
                ->whereIn('type', ['invoice', 'receipt'])
                ->where('status', 'accepted')
                ->whereNull('paid_at')
                ->orderBy('due_date')
                ->limit(5)
                ->get();
                
            foreach ($invoices as $invoice) {
                $suggestions[] = [
                    'type' => 'invoice',
                    'record' => $invoice,
                    'reason' => 'Factura pendiente de pago'
                ];
            }
        } else {
            // Suggest recent unpaid expenses
            $expenses = Expense::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->orderBy('due_date')
                ->limit(5)
                ->get();
                
            foreach ($expenses as $expense) {
                $suggestions[] = [
                    'type' => 'expense',
                    'record' => $expense,
                    'reason' => 'Gasto pendiente de pago'
                ];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Create match record
     */
    public function createMatch(BankTransaction $transaction, $matchable, string $matchType, float $confidence, string $method): BankTransactionMatch
    {
        return BankTransactionMatch::create([
            'bank_transaction_id' => $transaction->id,
            'matchable_type' => get_class($matchable),
            'matchable_id' => $matchable->id,
            'match_type' => $matchType,
            'amount' => $transaction->amount,
            'confidence_score' => $confidence,
            'match_method' => $method,
            'matched_by' => auth()->id(),
            'match_details' => [
                'transaction_date' => $transaction->date,
                'transaction_reference' => $transaction->reference,
                'record_reference' => $this->getRecordReference($matchable),
                'days_difference' => Carbon::parse($transaction->date)->diffInDays($this->getRecordDate($matchable))
            ]
        ]);
    }
    
    /**
     * Remove match
     */
    public function removeMatch(BankTransactionMatch $match): bool
    {
        try {
            $match->delete();
            
            // Update transaction status
            $match->bankTransaction->update(['status' => 'pending']);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error removing bank transaction match', [
                'match_id' => $match->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}