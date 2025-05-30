<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Traits\ChecksPermissions;
use App\Modules\Accounting\Services\ChartOfAccountsService;
use App\Modules\Accounting\Requests\ChartOfAccountRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChartOfAccountsController extends Controller
{
    use ChecksPermissions;

    protected $chartService;

    public function __construct(ChartOfAccountsService $chartService)
    {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:accounting']);
        $this->chartService = $chartService;
    }

    public function index(Request $request)
    {
        $this->checkPermission('chart_accounts.view');
        
        $filters = $request->only(['search', 'type', 'level', 'is_active']);
        $accounts = $this->chartService->getFilteredAccounts($filters);
        
        $accountTypes = [
            'asset' => 'Activo',
            'liability' => 'Pasivo',
            'equity' => 'Patrimonio',
            'income' => 'Ingreso',
            'expense' => 'Gasto',
        ];

        return Inertia::render('Accounting/ChartOfAccounts/Index', [
            'accounts' => $accounts,
            'accountTypes' => $accountTypes,
            'filters' => $filters,
            'hasDefaultChart' => $this->chartService->hasDefaultChart(auth()->user()->tenant_id),
        ]);
    }

    public function create()
    {
        $this->checkPermission('chart_accounts.create');
        
        $parentAccounts = $this->chartService->getParentAccounts(auth()->user()->tenant_id);
        $accountTypes = [
            'asset' => 'Activo',
            'liability' => 'Pasivo',
            'equity' => 'Patrimonio',
            'income' => 'Ingreso',
            'expense' => 'Gasto',
        ];

        return Inertia::render('Accounting/ChartOfAccounts/Create', [
            'parentAccounts' => $parentAccounts,
            'accountTypes' => $accountTypes,
            'configuration' => config('accounting.chart_of_accounts'),
        ]);
    }

    public function store(ChartOfAccountRequest $request)
    {
        $this->checkPermission('chart_accounts.create');
        
        try {
            $account = $this->chartService->createAccount(
                $request->validated(),
                auth()->user()->tenant_id
            );

            return redirect()->route('accounting.chart-of-accounts.index')
                ->with('success', 'Cuenta creada exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(ChartOfAccount $account)
    {
        $this->checkPermission('chart_accounts.view');
        $this->authorize('view', $account);
        
        $account->load(['parent', 'children']);
        $balance = $this->chartService->getAccountBalance($account);
        $transactions = $this->chartService->getRecentTransactions($account, 20);

        return Inertia::render('Accounting/ChartOfAccounts/Show', [
            'account' => $account,
            'balance' => $balance,
            'transactions' => $transactions,
        ]);
    }

    public function edit(ChartOfAccount $account)
    {
        $this->checkPermission('chart_accounts.edit');
        $this->authorize('update', $account);
        
        if ($account->hasTransactions()) {
            return back()->withErrors(['error' => 'No se puede editar una cuenta que tiene transacciones.']);
        }
        
        $parentAccounts = $this->chartService->getParentAccounts(
            auth()->user()->tenant_id,
            $account->id
        );
        
        $accountTypes = [
            'asset' => 'Activo',
            'liability' => 'Pasivo',
            'equity' => 'Patrimonio',
            'income' => 'Ingreso',
            'expense' => 'Gasto',
        ];

        return Inertia::render('Accounting/ChartOfAccounts/Edit', [
            'account' => $account,
            'parentAccounts' => $parentAccounts,
            'accountTypes' => $accountTypes,
        ]);
    }

    public function update(ChartOfAccountRequest $request, ChartOfAccount $account)
    {
        $this->checkPermission('chart_accounts.edit');
        $this->authorize('update', $account);
        
        if ($account->hasTransactions()) {
            return back()->withErrors(['error' => 'No se puede modificar una cuenta que tiene transacciones.']);
        }

        try {
            $this->chartService->updateAccount($account, $request->validated());

            return redirect()->route('accounting.chart-of-accounts.index')
                ->with('success', 'Cuenta actualizada exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(ChartOfAccount $account)
    {
        $this->checkPermission('chart_accounts.delete');
        $this->authorize('delete', $account);
        
        if ($account->hasTransactions()) {
            return back()->withErrors(['error' => 'No se puede eliminar una cuenta que tiene transacciones.']);
        }

        if ($account->children()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar una cuenta que tiene subcuentas.']);
        }

        try {
            $this->chartService->deleteAccount($account);

            return redirect()->route('accounting.chart-of-accounts.index')
                ->with('success', 'Cuenta eliminada exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function import(Request $request)
    {
        $this->checkPermission('chart_accounts.import');
        
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx|max:2048',
            'format' => 'required|in:standard,custom',
        ]);

        try {
            $result = $this->chartService->importAccounts(
                $request->file('file'),
                $request->format,
                auth()->user()->tenant_id
            );

            return redirect()->back()->with('success', 
                "Importación completada. {$result['imported']} cuentas importadas, {$result['errors']} errores."
            );

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function export(Request $request)
    {
        $this->checkPermission('chart_accounts.export');
        
        $request->validate([
            'format' => 'required|in:excel,csv,pdf',
            'include_balances' => 'boolean',
        ]);

        try {
            $export = $this->chartService->exportAccounts(
                auth()->user()->tenant_id,
                $request->format,
                $request->boolean('include_balances')
            );

            return $export;

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function initializeDefault()
    {
        $this->checkPermission('chart_accounts.create');
        
        if ($this->chartService->hasDefaultChart(auth()->user()->tenant_id)) {
            return back()->withErrors(['error' => 'Ya existe un plan de cuentas inicializado.']);
        }

        try {
            $result = $this->chartService->initializeDefaultChart(auth()->user()->tenant_id);

            return redirect()->back()->with('success', 
                "Plan de cuentas inicializado. {$result['created']} cuentas creadas."
            );

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reorder(Request $request)
    {
        $this->checkPermission('chart_accounts.edit');
        
        $request->validate([
            'accounts' => 'required|array',
            'accounts.*.id' => 'required|exists:chart_of_accounts,id',
            'accounts.*.sort_order' => 'required|integer|min:0',
        ]);

        try {
            $this->chartService->reorderAccounts(
                $request->accounts,
                auth()->user()->tenant_id
            );

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function getBalance(ChartOfAccount $account)
    {
        $this->checkPermission('chart_accounts.view');
        $this->authorize('view', $account);
        
        try {
            $balance = $this->chartService->getAccountBalance($account);
            
            return response()->json([
                'success' => true,
                'balance' => $balance,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function getTransactions(Request $request, ChartOfAccount $account)
    {
        $this->checkPermission('chart_accounts.view');
        $this->authorize('view', $account);
        
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        try {
            $transactions = $this->chartService->getAccountTransactions(
                $account,
                $request->date_from,
                $request->date_to,
                $request->get('per_page', 20)
            );
            
            return response()->json([
                'success' => true,
                'transactions' => $transactions,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function balanceSheet(Request $request)
    {
        $this->checkPermission('chart_accounts.view');
        
        $request->validate([
            'as_of_date' => 'nullable|date',
            'format' => 'nullable|in:json,excel,pdf',
        ]);

        try {
            $balanceSheet = $this->chartService->generateBalanceSheet(
                auth()->user()->tenant_id,
                $request->as_of_date ?? now()->format('Y-m-d')
            );

            if ($request->format === 'excel' || $request->format === 'pdf') {
                return $this->chartService->exportBalanceSheet($balanceSheet, $request->format);
            }

            return response()->json([
                'success' => true,
                'balance_sheet' => $balanceSheet,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function trialBalance(Request $request)
    {
        $this->checkPermission('chart_accounts.view');
        
        $request->validate([
            'as_of_date' => 'nullable|date',
            'include_zero_balances' => 'boolean',
            'format' => 'nullable|in:json,excel,pdf',
        ]);

        try {
            $trialBalance = $this->chartService->generateTrialBalance(
                auth()->user()->tenant_id,
                $request->as_of_date ?? now()->format('Y-m-d'),
                $request->boolean('include_zero_balances')
            );

            if ($request->format === 'excel' || $request->format === 'pdf') {
                return $this->chartService->exportTrialBalance($trialBalance, $request->format);
            }

            return response()->json([
                'success' => true,
                'trial_balance' => $trialBalance,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}