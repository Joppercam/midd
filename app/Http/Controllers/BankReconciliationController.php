<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BankReconciliation;
use App\Models\Payment;
use App\Models\Expense;
use App\Services\BankReconciliationService;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class BankReconciliationController extends Controller
{
    use ChecksPermissions;
    protected BankReconciliationService $reconciliationService;

    public function __construct(BankReconciliationService $reconciliationService)
    {
        $this->reconciliationService = $reconciliationService;
    }

    public function index(): Response
    {
        $this->checkPermission('bank_reconciliation.view');
        $bankAccounts = BankAccount::where('tenant_id', auth()->user()->tenant_id)
            ->with(['reconciliations' => function ($query) {
                $query->latest('reconciliation_date')->limit(5);
            }])
            ->get();

        $activeReconciliations = BankReconciliation::where('tenant_id', auth()->user()->tenant_id)
            ->where('status', 'draft')
            ->with('bankAccount')
            ->get();

        return Inertia::render('Banking/Index', [
            'bankAccounts' => $bankAccounts,
            'activeReconciliations' => $activeReconciliations
        ]);
    }

    public function accounts(): Response
    {
        $this->checkPermission('bank_reconciliation.view');
        $accounts = BankAccount::where('tenant_id', auth()->user()->tenant_id)
            ->withCount('transactions')
            ->get();

        return Inertia::render('Banking/Accounts', [
            'accounts' => $accounts
        ]);
    }

    public function createAccount(): Response
    {
        $this->checkPermission('bank_reconciliation.manage');
        return Inertia::render('Banking/CreateAccount');
    }

    public function storeAccount(Request $request)
    {
        $this->checkPermission('bank_reconciliation.manage');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_type' => 'required|in:checking,savings,credit_card',
            'currency' => 'required|string|size:3',
            'current_balance' => 'nullable|numeric',
            'notes' => 'nullable|string|max:1000'
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;
        $validated['reconciled_balance'] = $validated['current_balance'] ?? 0;

        BankAccount::create($validated);

        return redirect()->route('banking.accounts')
            ->with('success', 'Cuenta bancaria creada exitosamente.');
    }

    public function transactions(BankAccount $bankAccount): Response
    {
        $this->checkPermission('bank_reconciliation.view');
        
        if ($bankAccount->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $transactions = $bankAccount->transactions()
            ->with('matches.matchable')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(50);

        return Inertia::render('Banking/Transactions', [
            'bankAccount' => $bankAccount,
            'transactions' => $transactions
        ]);
    }

    public function importStatement(Request $request, BankAccount $bankAccount)
    {
        $this->checkPermission('bank_reconciliation.import');
        
        if ($bankAccount->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

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

    public function startReconciliation(Request $request, BankAccount $bankAccount): Response
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        
        if ($bankAccount->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'statement_balance' => 'required|numeric'
        ]);

        $reconciliation = $this->reconciliationService->createReconciliation(
            $bankAccount,
            Carbon::parse($request->start_date),
            Carbon::parse($request->end_date),
            $request->statement_balance
        );

        return redirect()->route('banking.reconcile', $reconciliation)
            ->with('success', 'Conciliación iniciada.');
    }

    public function reconcile(BankReconciliation $reconciliation): Response
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        
        if ($reconciliation->bank_account->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $transactions = $reconciliation->getTransactions()
            ->load('matches.matchable');

        $summary = $this->reconciliationService->getReconciliationSummary($reconciliation);

        return Inertia::render('Banking/Reconcile', [
            'reconciliation' => $reconciliation->load('bankAccount'),
            'transactions' => $transactions,
            'summary' => $summary
        ]);
    }

    public function autoMatch(BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        
        if ($reconciliation->bank_account->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        try {
            $result = $this->reconciliationService->autoMatchTransactions($reconciliation);

            return back()->with('success', sprintf(
                'Conciliación automática completada: %d de %d transacciones conciliadas.',
                $result['matched'],
                $result['total']
            ));
        } catch (\Exception $e) {
            return back()->with('error', 'Error en conciliación automática: ' . $e->getMessage());
        }
    }

    public function getSuggestedMatches(BankTransaction $transaction)
    {
        $this->checkPermission('bank_reconciliation.view');
        
        if ($transaction->bank_account->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $suggestions = $this->reconciliationService->getSuggestedMatches($transaction);

        return response()->json([
            'suggestions' => $suggestions
        ]);
    }

    public function matchTransaction(Request $request, BankTransaction $transaction)
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        
        if ($transaction->bank_account->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

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

    public function unmatchTransaction(BankTransaction $transaction)
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        
        if ($transaction->bank_account->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        try {
            $this->reconciliationService->unmatchTransaction($transaction);

            return back()->with('success', 'Conciliación eliminada.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar conciliación: ' . $e->getMessage());
        }
    }

    public function completeReconciliation(BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliation.approve');
        
        if ($reconciliation->bank_account->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        try {
            $this->reconciliationService->completeReconciliation($reconciliation);

            return redirect()->route('banking.index')
                ->with('success', 'Conciliación completada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function addAdjustment(Request $request, BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        
        if ($reconciliation->bank_account->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|not_in:0'
        ]);

        $reconciliation->addAdjustment(
            $request->description,
            $request->amount
        );

        return back()->with('success', 'Ajuste agregado.');
    }

    public function removeAdjustment(BankReconciliation $reconciliation, int $index)
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        
        if ($reconciliation->bank_account->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $reconciliation->removeAdjustment($index);

        return back()->with('success', 'Ajuste eliminado.');
    }

    public function ignoreTransaction(BankTransaction $transaction)
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        
        if ($transaction->bank_account->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $transaction->markAsIgnored();

        if ($reconciliation = $transaction->bankAccount->reconciliations()
            ->where('status', 'draft')->first()) {
            $reconciliation->calculate();
        }

        return back()->with('success', 'Transacción marcada como ignorada.');
    }

    public function reconciliationReport(BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliation.view');
        
        if ($reconciliation->bank_account->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $report = $this->reconciliationService->generateReconciliationReport($reconciliation);
        
        return Inertia::render('Banking/ReconciliationReport', [
            'reconciliation' => $reconciliation->load('bankAccount'),
            'report' => $report
        ]);
    }

    public function exportReconciliationPdf(BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliation.export');
        
        if ($reconciliation->bank_account->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $report = $this->reconciliationService->generateReconciliationReport($reconciliation);
        $tenant = auth()->user()->tenant;
        
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('reports.bank-reconciliation-pdf', compact('reconciliation', 'report', 'tenant'));
        
        return $pdf->download("conciliacion-bancaria-{$reconciliation->id}.pdf");
    }

    public function exportReconciliationExcel(BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliation.export');
        
        if ($reconciliation->bank_account->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        return (new \App\Exports\BankReconciliationExport($reconciliation))
            ->download("conciliacion-bancaria-{$reconciliation->id}.xlsx");
    }

    public function monthlyReport(Request $request)
    {
        $this->checkPermission('bank_reconciliation.view');
        
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020'
        ]);

        $tenant_id = auth()->user()->tenant_id;
        
        $reconciliations = BankReconciliation::where('tenant_id', $tenant_id)
            ->whereYear('reconciliation_date', $request->year)
            ->whereMonth('reconciliation_date', $request->month)
            ->with('bankAccount')
            ->get();

        $summary = $this->reconciliationService->generateMonthlySummary(
            $tenant_id,
            $request->year,
            $request->month
        );

        return Inertia::render('Banking/MonthlyReport', [
            'reconciliations' => $reconciliations,
            'summary' => $summary,
            'period' => [
                'month' => $request->month,
                'year' => $request->year
            ]
        ]);
    }
}