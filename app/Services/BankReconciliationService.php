<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BankReconciliation;
use App\Models\BankTransactionMatch;
use App\Models\Payment;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class BankReconciliationService
{
    public function importBankStatement(BankAccount $bankAccount, $file, string $format = 'csv'): array
    {
        $transactions = match($format) {
            'csv' => $this->parseCSV($file),
            'ofx' => $this->parseOFX($file),
            'excel' => $this->parseExcel($file),
            default => throw new \Exception("Formato de archivo no soportado: {$format}")
        };

        return $bankAccount->importTransactions($transactions);
    }

    protected function parseCSV($file): array
    {
        $transactions = [];
        $content = file_get_contents($file->getRealPath());
        
        // Detect encoding and convert to UTF-8 if needed
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252']);
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        $lines = explode("\n", $content);
        $headers = str_getcsv(array_shift($lines));
        
        // Map common header names
        $headerMap = [
            'fecha' => 'transaction_date',
            'date' => 'transaction_date',
            'descripcion' => 'description',
            'description' => 'description',
            'monto' => 'amount',
            'amount' => 'amount',
            'cargo' => 'withdrawal',
            'abono' => 'deposit',
            'saldo' => 'balance',
            'balance' => 'balance',
            'referencia' => 'reference',
            'reference' => 'reference'
        ];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $data = str_getcsv($line);
            if (count($data) !== count($headers)) continue;
            
            $transaction = [];
            foreach ($headers as $index => $header) {
                $key = $headerMap[strtolower(trim($header))] ?? strtolower(trim($header));
                $transaction[$key] = $data[$index];
            }
            
            // Process transaction
            $processedTransaction = $this->processTransactionData($transaction);
            if ($processedTransaction) {
                $transactions[] = $processedTransaction;
            }
        }
        
        return $transactions;
    }

    protected function parseOFX($file): array
    {
        // Basic OFX parser implementation
        $content = file_get_contents($file->getRealPath());
        $transactions = [];
        
        // Extract transaction data between <STMTTRN> tags
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/s', $content, $matches);
        
        foreach ($matches[1] as $transactionXml) {
            $transaction = [];
            
            // Extract fields
            if (preg_match('/<DTPOSTED>(\d+)/', $transactionXml, $m)) {
                $transaction['transaction_date'] = Carbon::createFromFormat('YmdHis', $m[1])->format('Y-m-d');
            }
            if (preg_match('/<TRNAMT>([\-\d\.]+)/', $transactionXml, $m)) {
                $transaction['amount'] = floatval($m[1]);
            }
            if (preg_match('/<NAME>(.+)/', $transactionXml, $m)) {
                $transaction['description'] = trim($m[1]);
            }
            if (preg_match('/<FITID>(.+)/', $transactionXml, $m)) {
                $transaction['external_id'] = trim($m[1]);
            }
            if (preg_match('/<CHECKNUM>(.+)/', $transactionXml, $m)) {
                $transaction['reference'] = trim($m[1]);
            }
            
            $processedTransaction = $this->processTransactionData($transaction);
            if ($processedTransaction) {
                $transactions[] = $processedTransaction;
            }
        }
        
        return $transactions;
    }

    protected function parseExcel($file): array
    {
        // This would require a package like maatwebsite/excel
        throw new \Exception("Excel parsing not implemented yet");
    }

    protected function processTransactionData(array $data): ?array
    {
        // Handle date parsing
        $date = null;
        if (isset($data['transaction_date'])) {
            try {
                $date = Carbon::parse($data['transaction_date']);
            } catch (\Exception $e) {
                return null;
            }
        }
        
        // Handle amount
        $amount = 0;
        if (isset($data['amount'])) {
            $amount = $this->parseAmount($data['amount']);
        } elseif (isset($data['withdrawal']) && isset($data['deposit'])) {
            $withdrawal = $this->parseAmount($data['withdrawal']);
            $deposit = $this->parseAmount($data['deposit']);
            $amount = $deposit - $withdrawal;
        }
        
        if (!$date || $amount == 0) {
            return null;
        }
        
        return [
            'transaction_date' => $date->format('Y-m-d'),
            'value_date' => $data['value_date'] ?? $date->format('Y-m-d'),
            'description' => $data['description'] ?? '',
            'amount' => $amount,
            'balance' => isset($data['balance']) ? $this->parseAmount($data['balance']) : null,
            'reference' => $data['reference'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'category' => $data['category'] ?? null,
            'metadata' => array_diff_key($data, array_flip([
                'transaction_date', 'value_date', 'description', 'amount', 
                'balance', 'reference', 'external_id', 'category'
            ]))
        ];
    }

    protected function parseAmount($amount): float
    {
        if (is_numeric($amount)) {
            return floatval($amount);
        }
        
        // Remove currency symbols and spaces
        $amount = preg_replace('/[^\d\-\.,]/', '', $amount);
        
        // Handle different decimal separators
        if (substr_count($amount, ',') === 1 && substr_count($amount, '.') === 0) {
            $amount = str_replace(',', '.', $amount);
        } else {
            $amount = str_replace(',', '', $amount);
        }
        
        return floatval($amount);
    }

    public function createReconciliation(
        BankAccount $bankAccount,
        Carbon $startDate,
        Carbon $endDate,
        float $statementBalance
    ): BankReconciliation {
        $reconciliation = BankReconciliation::create([
            'bank_account_id' => $bankAccount->id,
            'tenant_id' => $bankAccount->tenant_id,
            'user_id' => auth()->id(),
            'reconciliation_date' => now(),
            'statement_start_date' => $startDate,
            'statement_end_date' => $endDate,
            'statement_balance' => $statementBalance,
            'system_balance' => 0,
            'difference' => 0,
            'status' => 'draft'
        ]);
        
        $reconciliation->calculate();
        
        return $reconciliation;
    }

    public function autoMatchTransactions(BankReconciliation $reconciliation): array
    {
        $matched = 0;
        $transactions = $reconciliation->getTransactions()->where('status', 'pending');
        
        foreach ($transactions as $transaction) {
            $potentialMatches = $transaction->findPotentialMatches();
            
            if (count($potentialMatches) === 1 && $potentialMatches[0]['score'] >= 0.9) {
                // High confidence single match - auto match
                BankTransactionMatch::createMatch(
                    $transaction,
                    $potentialMatches[0]['model'],
                    $potentialMatches[0]['type'],
                    [
                        'confidence_score' => $potentialMatches[0]['score'] * 100,
                        'match_method' => 'auto_combined',
                        'match_details' => $potentialMatches[0]['details'],
                        'bank_reconciliation_id' => $reconciliation->id
                    ]
                );
                $matched++;
            }
        }
        
        // Recalculate reconciliation
        $reconciliation->calculate();
        
        return [
            'matched' => $matched,
            'total' => $transactions->count()
        ];
    }

    public function matchTransaction(
        BankTransaction $transaction,
        Model $matchable,
        string $matchType,
        ?BankReconciliation $reconciliation = null
    ): BankTransactionMatch {
        $options = [];
        if ($reconciliation) {
            $options['bank_reconciliation_id'] = $reconciliation->id;
        }
        
        $match = BankTransactionMatch::createMatch(
            $transaction,
            $matchable,
            $matchType,
            $options
        );
        
        if ($reconciliation) {
            $reconciliation->calculate();
        }
        
        return $match;
    }

    public function unmatchTransaction(BankTransaction $transaction): void
    {
        $match = $transaction->getMatch();
        if ($match) {
            $reconciliation = $match->bankReconciliation;
            $match->unmatch();
            
            if ($reconciliation) {
                $reconciliation->calculate();
            }
        }
    }

    public function completeReconciliation(BankReconciliation $reconciliation): void
    {
        if (!$reconciliation->canBeCompleted()) {
            throw new \Exception('La conciliación no puede ser completada. Verifique que todas las transacciones estén conciliadas y que no haya diferencias.');
        }
        
        DB::transaction(function () use ($reconciliation) {
            $reconciliation->markAsCompleted();
        });
    }

    public function getSuggestedMatches(BankTransaction $transaction, int $limit = 5): Collection
    {
        $potentialMatches = $transaction->findPotentialMatches(0.5);
        
        return collect($potentialMatches)->take($limit);
    }

    public function getReconciliationSummary(BankReconciliation $reconciliation): array
    {
        $transactions = $reconciliation->getTransactions();
        
        return [
            'statement_balance' => $reconciliation->statement_balance,
            'system_balance' => $reconciliation->system_balance,
            'difference' => $reconciliation->difference,
            'adjustments' => $reconciliation->getTotalAdjustments(),
            'adjusted_difference' => $reconciliation->getAdjustedDifference(),
            'transactions' => [
                'total' => $transactions->count(),
                'matched' => $transactions->where('status', 'matched')->count(),
                'pending' => $transactions->where('status', 'pending')->count(),
                'reconciled' => $transactions->where('status', 'reconciled')->count(),
                'ignored' => $transactions->where('status', 'ignored')->count()
            ],
            'deposits' => [
                'count' => $transactions->where('amount', '>', 0)->count(),
                'total' => $transactions->where('amount', '>', 0)->sum('amount')
            ],
            'withdrawals' => [
                'count' => $transactions->where('amount', '<', 0)->count(),
                'total' => abs($transactions->where('amount', '<', 0)->sum('amount'))
            ],
            'can_complete' => $reconciliation->canBeCompleted()
        ];
    }

    public function generateReconciliationReport(BankReconciliation $reconciliation): array
    {
        $transactions = $reconciliation->getTransactions()
            ->load('matches.matchable');
        
        $matchedTransactions = $transactions->where('status', 'matched');
        $unmatchedTransactions = $transactions->where('status', 'pending');
        $ignoredTransactions = $transactions->where('status', 'ignored');
        
        return [
            'reconciliation' => [
                'id' => $reconciliation->id,
                'date' => $reconciliation->reconciliation_date,
                'period' => [
                    'start' => $reconciliation->statement_start_date,
                    'end' => $reconciliation->statement_end_date
                ],
                'status' => $reconciliation->status,
                'completed_at' => $reconciliation->completed_at,
                'completed_by' => $reconciliation->completedBy?->name
            ],
            'bank_account' => [
                'name' => $reconciliation->bankAccount->name,
                'bank_name' => $reconciliation->bankAccount->bank_name,
                'account_number' => $reconciliation->bankAccount->account_number,
                'account_type' => $reconciliation->bankAccount->account_type
            ],
            'balances' => [
                'opening_balance' => $reconciliation->opening_balance,
                'closing_balance' => $reconciliation->statement_balance,
                'system_balance' => $reconciliation->system_balance,
                'difference' => $reconciliation->difference,
                'adjustments' => $reconciliation->adjustments ?? [],
                'total_adjustments' => $reconciliation->getTotalAdjustments(),
                'final_difference' => $reconciliation->getAdjustedDifference()
            ],
            'summary' => [
                'total_transactions' => $transactions->count(),
                'matched_transactions' => $matchedTransactions->count(),
                'unmatched_transactions' => $unmatchedTransactions->count(),
                'ignored_transactions' => $ignoredTransactions->count(),
                'total_deposits' => $transactions->where('amount', '>', 0)->sum('amount'),
                'total_withdrawals' => abs($transactions->where('amount', '<', 0)->sum('amount')),
                'deposit_count' => $transactions->where('amount', '>', 0)->count(),
                'withdrawal_count' => $transactions->where('amount', '<', 0)->count()
            ],
            'matched_transactions' => $matchedTransactions->map(function ($transaction) {
                $match = $transaction->matches->first();
                return [
                    'date' => $transaction->transaction_date,
                    'description' => $transaction->description,
                    'amount' => $transaction->amount,
                    'match_type' => $match->match_type ?? null,
                    'matched_with' => $match ? [
                        'type' => class_basename($match->matchable_type),
                        'reference' => $this->getMatchableReference($match->matchable)
                    ] : null
                ];
            }),
            'unmatched_transactions' => $unmatchedTransactions->map(function ($transaction) {
                return [
                    'date' => $transaction->transaction_date,
                    'description' => $transaction->description,
                    'amount' => $transaction->amount,
                    'reference' => $transaction->reference
                ];
            }),
            'adjustments' => $reconciliation->adjustments ?? []
        ];
    }

    public function generateMonthlySummary(int $tenantId, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        
        $accounts = BankAccount::where('tenant_id', $tenantId)
            ->with(['reconciliations' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('reconciliation_date', [$startDate, $endDate]);
            }])
            ->get();
        
        $summary = [
            'period' => [
                'month' => $month,
                'year' => $year,
                'month_name' => $startDate->format('F'),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ],
            'accounts' => [],
            'totals' => [
                'reconciliations' => 0,
                'completed' => 0,
                'pending' => 0,
                'total_transactions' => 0,
                'matched_transactions' => 0,
                'unmatched_transactions' => 0
            ]
        ];
        
        foreach ($accounts as $account) {
            $accountReconciliations = $account->reconciliations;
            
            if ($accountReconciliations->isEmpty()) {
                continue;
            }
            
            $accountSummary = [
                'account_name' => $account->name,
                'bank_name' => $account->bank_name,
                'reconciliations' => $accountReconciliations->count(),
                'completed' => $accountReconciliations->where('status', 'completed')->count(),
                'pending' => $accountReconciliations->where('status', 'draft')->count(),
                'last_reconciliation' => $accountReconciliations->max('reconciliation_date')
            ];
            
            $summary['accounts'][] = $accountSummary;
            $summary['totals']['reconciliations'] += $accountSummary['reconciliations'];
            $summary['totals']['completed'] += $accountSummary['completed'];
            $summary['totals']['pending'] += $accountSummary['pending'];
        }
        
        // Get transaction statistics for the month
        $transactions = BankTransaction::whereHas('bankAccount', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId);
            })
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->get();
        
        $summary['totals']['total_transactions'] = $transactions->count();
        $summary['totals']['matched_transactions'] = $transactions->where('status', 'matched')->count();
        $summary['totals']['unmatched_transactions'] = $transactions->where('status', 'pending')->count();
        $summary['totals']['total_deposits'] = $transactions->where('amount', '>', 0)->sum('amount');
        $summary['totals']['total_withdrawals'] = abs($transactions->where('amount', '<', 0)->sum('amount'));
        
        return $summary;
    }

    protected function getMatchableReference($matchable): string
    {
        if ($matchable instanceof Payment) {
            return "Pago #{$matchable->id} - Cliente: {$matchable->customer->name}";
        } elseif ($matchable instanceof Expense) {
            return "Gasto #{$matchable->id} - Proveedor: {$matchable->supplier->name}";
        }
        
        return "Ref: {$matchable->id}";
    }
}