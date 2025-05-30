<?php

namespace App\Modules\Banking\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ChecksPermissions;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BankReconciliation;
use App\Models\Payment;
use App\Models\Expense;
use App\Services\BankReconciliationService;
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
        $this->middleware(['auth', 'verified', 'check.module:banking']);
        $this->reconciliationService = $reconciliationService;
    }

    /**
     * Banking dashboard
     */
    public function index(): Response
    {
        $this->checkPermission('bank_accounts.view');
        
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

    /**
     * Reconciliation index
     */
    public function reconcileIndex(): Response
    {
        $this->checkPermission('bank_reconciliation.view');
        
        $reconciliations = BankReconciliation::where('tenant_id', auth()->user()->tenant_id)
            ->with('bankAccount')
            ->orderBy('reconciliation_date', 'desc')
            ->paginate(20);

        return Inertia::render('Banking/Reconciliations/Index', [
            'reconciliations' => $reconciliations
        ]);
    }

    /**
     * Start a new reconciliation
     */
    public function startReconciliation(Request $request, BankAccount $bankAccount)
    {
        $this->checkPermission('bank_reconciliation.create');
        $this->checkTenantAccess($bankAccount);

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

        return redirect()->route('banking.reconcile.show', $reconciliation)
            ->with('success', 'Conciliación iniciada.');
    }

    /**
     * Show reconciliation interface
     */
    public function reconcile(BankReconciliation $reconciliation): Response
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        $this->checkTenantAccess($reconciliation->bankAccount);

        $transactions = $reconciliation->getTransactions()
            ->load('matches.matchable');

        $summary = $this->reconciliationService->getReconciliationSummary($reconciliation);

        return Inertia::render('Banking/Reconciliations/Show', [
            'reconciliation' => $reconciliation->load('bankAccount'),
            'transactions' => $transactions,
            'summary' => $summary
        ]);
    }

    /**
     * Auto-match transactions
     */
    public function autoMatch(BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        $this->checkTenantAccess($reconciliation->bankAccount);

        if ($reconciliation->status !== 'draft') {
            return back()->with('error', 'Solo se pueden modificar conciliaciones en borrador.');
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

    /**
     * Complete reconciliation
     */
    public function completeReconciliation(BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliation.approve');
        $this->checkTenantAccess($reconciliation->bankAccount);

        if ($reconciliation->status !== 'draft') {
            return back()->with('error', 'Esta conciliación ya fue completada.');
        }

        try {
            $this->reconciliationService->completeReconciliation($reconciliation);

            return redirect()->route('banking.reconcile.index')
                ->with('success', 'Conciliación completada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Approve reconciliation
     */
    public function approveReconciliation(BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliation.approve');
        $this->checkTenantAccess($reconciliation->bankAccount);

        if ($reconciliation->status !== 'completed') {
            return back()->with('error', 'Solo se pueden aprobar conciliaciones completadas.');
        }

        try {
            $reconciliation->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now()
            ]);

            return back()->with('success', 'Conciliación aprobada.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al aprobar: ' . $e->getMessage());
        }
    }

    /**
     * Reopen reconciliation
     */
    public function reopenReconciliation(BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliation.approve');
        $this->checkTenantAccess($reconciliation->bankAccount);

        if ($reconciliation->status === 'approved') {
            return back()->with('error', 'No se pueden reabrir conciliaciones aprobadas.');
        }

        try {
            $reconciliation->update([
                'status' => 'draft',
                'completed_at' => null,
                'completed_by' => null
            ]);

            return back()->with('success', 'Conciliación reabierta.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al reabrir: ' . $e->getMessage());
        }
    }

    /**
     * Add adjustment
     */
    public function addAdjustment(Request $request, BankReconciliation $reconciliation)
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        $this->checkTenantAccess($reconciliation->bankAccount);

        if ($reconciliation->status !== 'draft') {
            return back()->with('error', 'Solo se pueden agregar ajustes a conciliaciones en borrador.');
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

    /**
     * Remove adjustment
     */
    public function removeAdjustment(BankReconciliation $reconciliation, int $index)
    {
        $this->checkPermission('bank_reconciliation.reconcile');
        $this->checkTenantAccess($reconciliation->bankAccount);

        if ($reconciliation->status !== 'draft') {
            return back()->with('error', 'Solo se pueden eliminar ajustes de conciliaciones en borrador.');
        }

        $reconciliation->removeAdjustment($index);

        return back()->with('success', 'Ajuste eliminado.');
    }

    /**
     * Check tenant access for bank account
     */
    private function checkTenantAccess($model)
    {
        if ($model->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'No tienes acceso a este recurso.');
        }
    }
}