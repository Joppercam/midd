<?php

namespace App\Modules\Banking\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ChecksPermissions;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    use ChecksPermissions;

    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'check.module:banking']);
    }

    /**
     * Display a listing of bank accounts
     */
    public function index(): Response
    {
        $this->checkPermission('bank_accounts.view');
        
        $accounts = BankAccount::where('tenant_id', auth()->user()->tenant_id)
            ->withCount('transactions')
            ->with('lastReconciliation')
            ->get();

        return Inertia::render('Banking/Accounts/Index', [
            'accounts' => $accounts
        ]);
    }

    /**
     * Show the form for creating a new bank account
     */
    public function create(): Response
    {
        $this->checkPermission('bank_accounts.create');
        
        return Inertia::render('Banking/Accounts/Create', [
            'accountTypes' => $this->getAccountTypes(),
            'banks' => $this->getBanks(),
            'currencies' => $this->getCurrencies()
        ]);
    }

    /**
     * Store a newly created bank account
     */
    public function store(Request $request)
    {
        $this->checkPermission('bank_accounts.create');
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_type' => 'required|in:checking,savings,credit_card',
            'currency' => 'required|string|size:3',
            'current_balance' => 'required|numeric',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean'
        ]);

        $validated['tenant_id'] = auth()->user()->tenant_id;
        $validated['reconciled_balance'] = $validated['current_balance'];
        $validated['is_active'] = $validated['is_active'] ?? true;

        BankAccount::create($validated);

        return redirect()->route('banking.accounts.index')
            ->with('success', 'Cuenta bancaria creada exitosamente.');
    }

    /**
     * Display the specified bank account
     */
    public function show(BankAccount $bankAccount): Response
    {
        $this->checkPermission('bank_accounts.view');
        $this->checkTenantAccess($bankAccount);

        $bankAccount->load([
            'transactions' => function ($query) {
                $query->latest('transaction_date')->limit(20);
            },
            'reconciliations' => function ($query) {
                $query->latest('reconciliation_date')->limit(5);
            }
        ]);

        $statistics = $this->getAccountStatistics($bankAccount);

        return Inertia::render('Banking/Accounts/Show', [
            'account' => $bankAccount,
            'statistics' => $statistics
        ]);
    }

    /**
     * Show the form for editing the specified bank account
     */
    public function edit(BankAccount $bankAccount): Response
    {
        $this->checkPermission('bank_accounts.edit');
        $this->checkTenantAccess($bankAccount);

        return Inertia::render('Banking/Accounts/Edit', [
            'account' => $bankAccount,
            'accountTypes' => $this->getAccountTypes(),
            'banks' => $this->getBanks(),
            'currencies' => $this->getCurrencies()
        ]);
    }

    /**
     * Update the specified bank account
     */
    public function update(Request $request, BankAccount $bankAccount)
    {
        $this->checkPermission('bank_accounts.edit');
        $this->checkTenantAccess($bankAccount);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'account_type' => 'required|in:checking,savings,credit_card',
            'currency' => 'required|string|size:3',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean'
        ]);

        $bankAccount->update($validated);

        return redirect()->route('banking.accounts.show', $bankAccount)
            ->with('success', 'Cuenta bancaria actualizada exitosamente.');
    }

    /**
     * Remove the specified bank account
     */
    public function destroy(BankAccount $bankAccount)
    {
        $this->checkPermission('bank_accounts.delete');
        $this->checkTenantAccess($bankAccount);

        if ($bankAccount->transactions()->exists()) {
            return back()->with('error', 'No se puede eliminar una cuenta con transacciones.');
        }

        $bankAccount->delete();

        return redirect()->route('banking.accounts.index')
            ->with('success', 'Cuenta bancaria eliminada exitosamente.');
    }

    /**
     * Toggle account active status
     */
    public function toggle(BankAccount $bankAccount)
    {
        $this->checkPermission('bank_accounts.edit');
        $this->checkTenantAccess($bankAccount);

        $bankAccount->update([
            'is_active' => !$bankAccount->is_active
        ]);

        $status = $bankAccount->is_active ? 'activada' : 'desactivada';
        
        return back()->with('success', "Cuenta bancaria {$status}.");
    }

    /**
     * Get account types
     */
    private function getAccountTypes(): array
    {
        return [
            'checking' => 'Cuenta Corriente',
            'savings' => 'Cuenta de Ahorro',
            'credit_card' => 'Tarjeta de Crédito'
        ];
    }

    /**
     * Get list of banks
     */
    private function getBanks(): array
    {
        return [
            'Banco Estado',
            'Banco de Chile',
            'Banco Santander',
            'BCI',
            'Scotiabank',
            'Banco BICE',
            'Banco Security',
            'Banco Consorcio',
            'Banco Internacional',
            'Banco Ripley',
            'Banco Falabella',
            'Otro'
        ];
    }

    /**
     * Get available currencies
     */
    private function getCurrencies(): array
    {
        return [
            'CLP' => 'Peso Chileno (CLP)',
            'USD' => 'Dólar Estadounidense (USD)',
            'EUR' => 'Euro (EUR)',
            'UF' => 'Unidad de Fomento (UF)'
        ];
    }

    /**
     * Get account statistics
     */
    private function getAccountStatistics(BankAccount $bankAccount): array
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        return [
            'current_balance' => $bankAccount->current_balance,
            'reconciled_balance' => $bankAccount->reconciled_balance,
            'pending_transactions' => $bankAccount->transactions()
                ->whereNull('bank_transaction_match_id')
                ->count(),
            'monthly_income' => $bankAccount->transactions()
                ->where('type', 'credit')
                ->whereBetween('transaction_date', [$currentMonth, now()])
                ->sum('amount'),
            'monthly_expenses' => $bankAccount->transactions()
                ->where('type', 'debit')
                ->whereBetween('transaction_date', [$currentMonth, now()])
                ->sum('amount'),
            'last_reconciliation' => $bankAccount->reconciliations()
                ->latest('reconciliation_date')
                ->first()?->reconciliation_date,
        ];
    }

    /**
     * Check tenant access
     */
    private function checkTenantAccess(BankAccount $bankAccount)
    {
        if ($bankAccount->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'No tienes acceso a esta cuenta bancaria.');
        }
    }
}