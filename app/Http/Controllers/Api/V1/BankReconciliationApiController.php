<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BankReconciliation;
use App\Models\BankTransactionMatch;
use App\Services\BankStatementParser;
use App\Services\BankReconciliationMatcher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class BankReconciliationApiController extends BaseApiController
{
    protected $parser;
    protected $matcher;

    public function __construct(BankStatementParser $parser, BankReconciliationMatcher $matcher)
    {
        $this->parser = $parser;
        $this->matcher = $matcher;
    }

    public function accounts(): JsonResponse
    {
        if (!$this->checkApiPermission('bank-reconciliation.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $accounts = BankAccount::with(['latestReconciliation'])
                ->orderBy('name')
                ->get();

            $this->logApiActivity('bank-reconciliation.accounts', request());

            return response()->json(['data' => $accounts]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving bank accounts');
        }
    }

    public function uploadStatement(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('bank-reconciliation.create')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'file' => 'required|file|mimes:csv,txt,xls,xlsx|max:10240',
            'format' => 'required|in:csv,excel,banco_estado,santander,bci,scotiabank',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $bankAccount = BankAccount::findOrFail($request->bank_account_id);
            
            if (!$this->verifyTenantAccess($bankAccount)) {
                return response()->json(['error' => 'Resource not found'], 404);
            }

            $file = $request->file('file');
            $path = $file->store('bank-statements', 'local');

            $result = $this->parser->parseStatement(
                Storage::path($path),
                $request->format,
                $request->bank_account_id
            );

            Storage::delete($path);

            $this->logApiActivity('bank-reconciliation.upload', $request, $bankAccount->id);

            return response()->json([
                'message' => 'Statement processed successfully',
                'data' => [
                    'transactions_imported' => $result['imported'],
                    'transactions_skipped' => $result['skipped'],
                    'errors' => $result['errors'] ?? []
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error processing bank statement');
        }
    }

    public function transactions(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('bank-reconciliation.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'status' => 'nullable|in:pending,matched,reconciled',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'amount_min' => 'nullable|numeric',
            'amount_max' => 'nullable|numeric|gte:amount_min',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $bankAccount = BankAccount::findOrFail($request->bank_account_id);
            
            if (!$this->verifyTenantAccess($bankAccount)) {
                return response()->json(['error' => 'Resource not found'], 404);
            }

            $query = BankTransaction::where('bank_account_id', $request->bank_account_id)
                ->with(['matches.payment', 'matches.taxDocument']);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('date_from')) {
                $query->where('transaction_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('transaction_date', '<=', $request->date_to);
            }

            if ($request->filled('amount_min')) {
                $query->where('amount', '>=', $request->amount_min);
            }

            if ($request->filled('amount_max')) {
                $query->where('amount', '<=', $request->amount_max);
            }

            $transactions = $query->orderBy('transaction_date', 'desc')
                ->paginate($request->get('per_page', 15));

            $this->logApiActivity('bank-reconciliation.transactions', $request);

            return response()->json([
                'data' => $transactions->items(),
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving bank transactions');
        }
    }

    public function autoMatch(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('bank-reconciliation.create')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'confidence_threshold' => 'nullable|numeric|between:0,1',
            'date_range_days' => 'nullable|integer|between:1,90',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $bankAccount = BankAccount::findOrFail($request->bank_account_id);
            
            if (!$this->verifyTenantAccess($bankAccount)) {
                return response()->json(['error' => 'Resource not found'], 404);
            }

            $result = $this->matcher->matchTransactions(
                $request->bank_account_id,
                $request->get('confidence_threshold', 0.8),
                $request->get('date_range_days', 30)
            );

            $this->logApiActivity('bank-reconciliation.auto-match', $request, $bankAccount->id);

            return response()->json([
                'message' => 'Auto-matching completed',
                'data' => [
                    'matches_created' => $result['matches'],
                    'transactions_processed' => $result['processed']
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error performing auto-match');
        }
    }

    public function createMatch(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('bank-reconciliation.create')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'bank_transaction_id' => 'required|exists:bank_transactions,id',
            'matchable_type' => 'required|in:payment,tax_document',
            'matchable_id' => 'required|integer',
            'confidence_score' => 'nullable|numeric|between:0,1',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transaction = BankTransaction::findOrFail($request->bank_transaction_id);
            
            if (!$this->verifyTenantAccess($transaction->bankAccount)) {
                return response()->json(['error' => 'Resource not found'], 404);
            }

            $matchableClass = $request->matchable_type === 'payment' ? 
                \App\Models\Payment::class : \App\Models\TaxDocument::class;
            
            $matchable = $matchableClass::findOrFail($request->matchable_id);

            if (!$this->verifyTenantAccess($matchable)) {
                return response()->json(['error' => 'Resource not found'], 404);
            }

            $match = BankTransactionMatch::create([
                'bank_transaction_id' => $request->bank_transaction_id,
                'matchable_type' => $matchableClass,
                'matchable_id' => $request->matchable_id,
                'confidence_score' => $request->get('confidence_score', 1.0),
                'match_type' => 'manual',
                'notes' => $request->notes,
            ]);

            $transaction->update(['status' => 'matched']);

            $this->logApiActivity('bank-reconciliation.create-match', $request, $match->id);

            return response()->json([
                'message' => 'Match created successfully',
                'data' => $match->load(['bankTransaction', 'matchable'])
            ], 201);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error creating match');
        }
    }

    public function reconcile(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('bank-reconciliation.create')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'reconciliation_date' => 'required|date',
            'statement_balance' => 'required|numeric',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $bankAccount = BankAccount::findOrFail($request->bank_account_id);
            
            if (!$this->verifyTenantAccess($bankAccount)) {
                return response()->json(['error' => 'Resource not found'], 404);
            }

            $reconciliation = BankReconciliation::create([
                'bank_account_id' => $request->bank_account_id,
                'reconciliation_date' => $request->reconciliation_date,
                'statement_balance' => $request->statement_balance,
                'book_balance' => $bankAccount->current_balance,
                'difference' => $request->statement_balance - $bankAccount->current_balance,
                'status' => 'completed',
                'notes' => $request->notes,
            ]);

            BankTransaction::where('bank_account_id', $request->bank_account_id)
                ->where('status', 'matched')
                ->where('transaction_date', '<=', $request->reconciliation_date)
                ->update(['status' => 'reconciled']);

            $this->logApiActivity('bank-reconciliation.reconcile', $request, $reconciliation->id);

            return response()->json([
                'message' => 'Bank reconciliation completed successfully',
                'data' => $reconciliation
            ], 201);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error completing reconciliation');
        }
    }

    public function stats(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('bank-reconciliation.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $bankAccountId = $request->get('bank_account_id');
            
            if ($bankAccountId) {
                $bankAccount = BankAccount::findOrFail($bankAccountId);
                if (!$this->verifyTenantAccess($bankAccount)) {
                    return response()->json(['error' => 'Resource not found'], 404);
                }
            }

            $query = BankTransaction::query();
            
            if ($bankAccountId) {
                $query->where('bank_account_id', $bankAccountId);
            }

            $stats = [
                'total_transactions' => $query->count(),
                'pending_transactions' => $query->where('status', 'pending')->count(),
                'matched_transactions' => $query->where('status', 'matched')->count(),
                'reconciled_transactions' => $query->where('status', 'reconciled')->count(),
                'total_amount' => $query->sum('amount'),
                'pending_amount' => $query->where('status', 'pending')->sum('amount'),
            ];

            if ($bankAccountId) {
                $stats['bank_account'] = $bankAccount;
                $stats['last_reconciliation'] = $bankAccount->latestReconciliation;
            }

            $this->logApiActivity('bank-reconciliation.stats', $request);

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving reconciliation statistics');
        }
    }
}