<?php

namespace App\Modules\Banking\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ChecksPermissions;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Payment;
use App\Models\Expense;
use App\Services\BankReconciliationService;
use App\Services\BankStatementParser;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankTransactionController extends Controller
{
    use ChecksPermissions;
    
    protected BankReconciliationService $reconciliationService;
    protected BankStatementParser $statementParser;

    public function __construct(
        BankReconciliationService $reconciliationService,
        BankStatementParser $statementParser
    ) {
        $this->middleware(['auth', 'verified', 'check.module:banking']);
        $this->reconciliationService = $reconciliationService;
        $this->statementParser = $statementParser;
    }

    /**
     * Display all transactions
     */
    public function index(Request $request): Response
    {
        $this->checkPermission('bank_transactions.view');
        
        $query = BankTransaction::where('tenant_id', auth()->user()->tenant_id)
            ->with(['bankAccount', 'matches.matchable']);

        // Filters
        if ($request->filled('account_id')) {
            $query->where('bank_account_id', $request->account_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            if ($request->status === 'matched') {
                $query->whereHas('matches');
            } elseif ($request->status === 'unmatched') {
                $query->whereDoesntHave('matches');
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(50)
            ->withQueryString();

        $accounts = BankAccount::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name', 'bank_name']);

        return Inertia::render('Banking/Transactions/Index', [
            'transactions' => $transactions,
            'accounts' => $accounts,
            'filters' => $request->only(['account_id', 'type', 'status', 'date_from', 'date_to', 'search'])
        ]);
    }

    /**
     * Display transactions by account
     */
    public function byAccount(BankAccount $bankAccount): Response
    {
        $this->checkPermission('bank_transactions.view');
        $this->checkTenantAccess($bankAccount);

        $transactions = $bankAccount->transactions()
            ->with('matches.matchable')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(50);

        return Inertia::render('Banking/Transactions/ByAccount', [
            'bankAccount' => $bankAccount,
            'transactions' => $transactions
        ]);
    }

    /**
     * Import bank statement
     */
    public function import(Request $request, BankAccount $bankAccount)
    {
        $this->checkPermission('bank_transactions.import');
        $this->checkTenantAccess($bankAccount);

        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'format' => 'required|in:csv,ofx,excel'
        ]);

        try {
            $result = $this->reconciliationService->importBankStatement(
                $bankAccount,
                $request->file('file'),
                $request->format
            );

            return back()->with('success', sprintf(
                'Importación completada: %d transacciones importadas, %d duplicadas, %d errores.',
                $result['imported'],
                $result['duplicates'],
                count($result['errors'])
            ));
        } catch (\Exception $e) {
            return back()->with('error', 'Error al importar archivo: ' . $e->getMessage());
        }
    }

    /**
     * Import preview
     */
    public function importPreview(Request $request)
    {
        $this->checkPermission('bank_transactions.import');
        
        $request->validate([
            'file' => 'required|file|max:10240',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'format' => 'required|in:csv,ofx,excel'
        ]);

        $bankAccount = BankAccount::findOrFail($request->bank_account_id);
        $this->checkTenantAccess($bankAccount);

        try {
            $preview = $this->statementParser->preview(
                $request->file('file'),
                $request->format,
                $bankAccount->bank_name
            );

            return response()->json([
                'success' => true,
                'preview' => $preview
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Confirm import
     */
    public function importConfirm(Request $request)
    {
        $this->checkPermission('bank_transactions.import');
        
        $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'transactions' => 'required|array',
            'transactions.*.date' => 'required|date',
            'transactions.*.description' => 'required|string',
            'transactions.*.amount' => 'required|numeric',
            'transactions.*.type' => 'required|in:credit,debit',
            'transactions.*.reference' => 'nullable|string'
        ]);

        $bankAccount = BankAccount::findOrFail($request->bank_account_id);
        $this->checkTenantAccess($bankAccount);

        try {
            $result = $this->reconciliationService->importTransactions(
                $bankAccount,
                $request->transactions
            );

            return response()->json([
                'success' => true,
                'imported' => $result['imported'],
                'duplicates' => $result['duplicates']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Show transaction details
     */
    public function show(BankTransaction $transaction): Response
    {
        $this->checkPermission('bank_transactions.view');
        $this->checkTenantAccess($transaction->bankAccount);

        $transaction->load(['bankAccount', 'matches.matchable']);

        return Inertia::render('Banking/Transactions/Show', [
            'transaction' => $transaction
        ]);
    }

    /**
     * Update transaction
     */
    public function update(Request $request, BankTransaction $transaction)
    {
        $this->checkPermission('bank_transactions.edit');
        $this->checkTenantAccess($transaction->bankAccount);

        $validated = $request->validate([
            'description' => 'required|string|max:500',
            'category' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000'
        ]);

        $transaction->update($validated);

        return back()->with('success', 'Transacción actualizada exitosamente.');
    }

    /**
     * Delete transaction
     */
    public function destroy(BankTransaction $transaction)
    {
        $this->checkPermission('bank_transactions.delete');
        $this->checkTenantAccess($transaction->bankAccount);

        if ($transaction->matches()->exists()) {
            return back()->with('error', 'No se puede eliminar una transacción conciliada.');
        }

        $transaction->delete();

        return redirect()->route('banking.transactions.index')
            ->with('success', 'Transacción eliminada exitosamente.');
    }

    /**
     * Categorize transaction
     */
    public function categorize(Request $request, BankTransaction $transaction)
    {
        $this->checkPermission('bank_transactions.edit');
        $this->checkTenantAccess($transaction->bankAccount);

        $request->validate([
            'category' => 'required|string|max:100'
        ]);

        $transaction->update(['category' => $request->category]);

        return back()->with('success', 'Categoría actualizada.');
    }

    /**
     * Get suggested matches
     */
    public function getSuggestions(BankTransaction $transaction)
    {
        $this->checkPermission('bank_reconciliation.view');
        $this->checkTenantAccess($transaction->bankAccount);

        $suggestions = $this->reconciliationService->getSuggestedMatches($transaction);

        return response()->json([
            'suggestions' => $suggestions
        ]);
    }

    /**
     * Match transaction
     */
    public function match(Request $request, BankTransaction $transaction)
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        $this->checkTenantAccess($transaction->bankAccount);

        $request->validate([
            'matchable_type' => 'required|string',
            'matchable_id' => 'required|integer',
            'match_type' => 'required|string'
        ]);

        try {
            $matchable = match($request->matchable_type) {
                'payment' => Payment::findOrFail($request->matchable_id),
                'expense' => Expense::findOrFail($request->matchable_id),
                default => throw new \Exception('Tipo de coincidencia no válido')
            };

            $this->reconciliationService->matchTransaction(
                $transaction,
                $matchable,
                $request->match_type,
                $transaction->bankAccount->reconciliations()
                    ->where('status', 'draft')
                    ->first()
            );

            return back()->with('success', 'Transacción conciliada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al conciliar: ' . $e->getMessage());
        }
    }

    /**
     * Unmatch transaction
     */
    public function unmatch(BankTransaction $transaction)
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        $this->checkTenantAccess($transaction->bankAccount);

        try {
            $this->reconciliationService->unmatchTransaction($transaction);
            return back()->with('success', 'Conciliación eliminada.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar conciliación: ' . $e->getMessage());
        }
    }

    /**
     * Ignore transaction
     */
    public function ignore(BankTransaction $transaction)
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        $this->checkTenantAccess($transaction->bankAccount);

        $transaction->markAsIgnored();

        if ($reconciliation = $transaction->bankAccount->reconciliations()
            ->where('status', 'draft')->first()) {
            $reconciliation->calculate();
        }

        return back()->with('success', 'Transacción marcada como ignorada.');
    }

    /**
     * Check tenant access
     */
    private function checkTenantAccess($model)
    {
        if ($model->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'No tienes acceso a este recurso.');
        }
    }
}