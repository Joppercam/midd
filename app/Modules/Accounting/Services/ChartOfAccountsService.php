<?php

namespace App\Modules\Accounting\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ChartOfAccountsService
{
    public function getFilteredAccounts(array $filters): LengthAwarePaginator
    {
        $tenantId = auth()->user()->tenant_id;
        
        $query = ChartOfAccount::where('tenant_id', $tenantId)
            ->with(['parent', 'children']);

        // Apply filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('code')
            ->paginate(50)
            ->withQueryString();
    }

    public function createAccount(array $data, int $tenantId): ChartOfAccount
    {
        return DB::transaction(function () use ($data, $tenantId) {
            // Generate code if not provided
            if (empty($data['code'])) {
                $data['code'] = $this->generateAccountCode($tenantId, $data['type'], $data['parent_id'] ?? null);
            }

            // Determine level
            $level = 1;
            if (!empty($data['parent_id'])) {
                $parent = ChartOfAccount::findOrFail($data['parent_id']);
                $level = $parent->level + 1;
            }

            return ChartOfAccount::create([
                'tenant_id' => $tenantId,
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $data['type'],
                'parent_id' => $data['parent_id'] ?? null,
                'level' => $level,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => auth()->id(),
            ]);
        });
    }

    public function updateAccount(ChartOfAccount $account, array $data): ChartOfAccount
    {
        return DB::transaction(function () use ($account, $data) {
            // Update level if parent changed
            $level = $account->level;
            if (isset($data['parent_id']) && $data['parent_id'] !== $account->parent_id) {
                if ($data['parent_id']) {
                    $parent = ChartOfAccount::findOrFail($data['parent_id']);
                    $level = $parent->level + 1;
                } else {
                    $level = 1;
                }
                
                // Update children levels recursively
                $this->updateChildrenLevels($account, $level - $account->level);
            }

            $account->update([
                'code' => $data['code'] ?? $account->code,
                'name' => $data['name'],
                'type' => $data['type'],
                'parent_id' => $data['parent_id'] ?? null,
                'level' => $level,
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? $account->is_active,
                'updated_by' => auth()->id(),
            ]);

            return $account;
        });
    }

    public function deleteAccount(ChartOfAccount $account): void
    {
        DB::transaction(function () use ($account) {
            // Move children to parent or make them root accounts
            $account->children()->update([
                'parent_id' => $account->parent_id,
                'level' => DB::raw('level - 1'),
            ]);

            $account->delete();
        });
    }

    public function getParentAccounts(int $tenantId, ?int $excludeId = null): Collection
    {
        $query = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('code');

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get(['id', 'code', 'name', 'type', 'level']);
    }

    public function hasDefaultChart(int $tenantId): bool
    {
        return ChartOfAccount::where('tenant_id', $tenantId)->exists();
    }

    public function initializeDefaultChart(int $tenantId): array
    {
        $created = 0;
        $defaultStructure = config('accounting.chart_of_accounts.default_structure');

        DB::transaction(function () use ($defaultStructure, $tenantId, &$created) {
            foreach ($defaultStructure as $mainAccount) {
                $account = ChartOfAccount::create([
                    'tenant_id' => $tenantId,
                    'code' => $mainAccount['code'],
                    'name' => $mainAccount['name'],
                    'type' => $mainAccount['type'],
                    'level' => $mainAccount['level'],
                    'is_active' => true,
                    'created_by' => auth()->id(),
                ]);
                $created++;

                // Create children if any
                if (isset($mainAccount['children'])) {
                    foreach ($mainAccount['children'] as $childAccount) {
                        ChartOfAccount::create([
                            'tenant_id' => $tenantId,
                            'code' => $childAccount['code'],
                            'name' => $childAccount['name'],
                            'type' => $childAccount['type'],
                            'parent_id' => $account->id,
                            'level' => $childAccount['level'],
                            'is_active' => true,
                            'created_by' => auth()->id(),
                        ]);
                        $created++;
                    }
                }
            }
        });

        return ['created' => $created];
    }

    public function importAccounts(UploadedFile $file, string $format, int $tenantId): array
    {
        $imported = 0;
        $errors = 0;
        $errorDetails = [];

        try {
            $data = Excel::toArray([], $file)[0]; // Get first sheet
            
            foreach ($data as $index => $row) {
                // Skip header row
                if ($index === 0) continue;

                try {
                    // Expected format: Code, Name, Type, Parent Code, Description
                    $code = trim($row[0] ?? '');
                    $name = trim($row[1] ?? '');
                    $type = trim($row[2] ?? '');
                    $parentCode = trim($row[3] ?? '');
                    $description = trim($row[4] ?? '');

                    if (empty($code) || empty($name) || empty($type)) {
                        throw new \Exception('Código, nombre y tipo son obligatorios');
                    }

                    // Find parent if specified
                    $parentId = null;
                    if (!empty($parentCode)) {
                        $parent = ChartOfAccount::where('tenant_id', $tenantId)
                            ->where('code', $parentCode)
                            ->first();
                        
                        if (!$parent) {
                            throw new \Exception("Cuenta padre con código '{$parentCode}' no encontrada");
                        }
                        $parentId = $parent->id;
                    }

                    // Check if account already exists
                    $existing = ChartOfAccount::where('tenant_id', $tenantId)
                        ->where('code', $code)
                        ->first();

                    if ($existing) {
                        throw new \Exception("Ya existe una cuenta con código '{$code}'");
                    }

                    $this->createAccount([
                        'code' => $code,
                        'name' => $name,
                        'type' => $type,
                        'parent_id' => $parentId,
                        'description' => $description,
                        'is_active' => true,
                    ], $tenantId);

                    $imported++;

                } catch (\Exception $e) {
                    $errors++;
                    $errorDetails[] = [
                        'row' => $index + 1,
                        'error' => $e->getMessage(),
                    ];
                }
            }

        } catch (\Exception $e) {
            throw new \Exception('Error al procesar archivo: ' . $e->getMessage());
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
            'error_details' => $errorDetails,
        ];
    }

    public function exportAccounts(int $tenantId, string $format, bool $includeBalances = false)
    {
        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->orderBy('code')
            ->get();

        if ($includeBalances) {
            $accounts->load('journalEntryLines');
        }

        switch ($format) {
            case 'excel':
                return Excel::download(
                    new \App\Exports\ChartOfAccountsExport($accounts, $includeBalances),
                    'plan-cuentas-' . now()->format('Y-m-d') . '.xlsx'
                );
                
            case 'csv':
                return Excel::download(
                    new \App\Exports\ChartOfAccountsExport($accounts, $includeBalances),
                    'plan-cuentas-' . now()->format('Y-m-d') . '.csv'
                );
                
            case 'pdf':
                $pdf = Pdf::loadView('accounting.chart-of-accounts.export-pdf', [
                    'accounts' => $accounts,
                    'include_balances' => $includeBalances,
                    'company' => auth()->user()->tenant,
                ]);
                
                return $pdf->download('plan-cuentas-' . now()->format('Y-m-d') . '.pdf');
                
            default:
                throw new \Exception('Formato de exportación no válido.');
        }
    }

    public function reorderAccounts(array $accounts, int $tenantId): void
    {
        DB::transaction(function () use ($accounts, $tenantId) {
            foreach ($accounts as $accountData) {
                ChartOfAccount::where('tenant_id', $tenantId)
                    ->where('id', $accountData['id'])
                    ->update(['sort_order' => $accountData['sort_order']]);
            }
        });
    }

    public function getAccountBalance(ChartOfAccount $account): array
    {
        // Calculate current balance based on journal entries
        $debitTotal = $account->journalEntryLines()
            ->whereHas('journalEntry', function ($query) {
                $query->where('status', 'posted');
            })
            ->sum('debit_amount');

        $creditTotal = $account->journalEntryLines()
            ->whereHas('journalEntry', function ($query) {
                $query->where('status', 'posted');
            })
            ->sum('credit_amount');

        // Calculate balance based on account type
        $balance = $this->calculateAccountBalance($account->type, $debitTotal, $creditTotal);

        return [
            'debit_total' => $debitTotal,
            'credit_total' => $creditTotal,
            'balance' => $balance,
            'balance_type' => $balance >= 0 ? 'debit' : 'credit',
        ];
    }

    public function getRecentTransactions(ChartOfAccount $account, int $limit = 20): Collection
    {
        return $account->journalEntryLines()
            ->with(['journalEntry'])
            ->whereHas('journalEntry', function ($query) {
                $query->where('status', 'posted');
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getAccountTransactions(ChartOfAccount $account, ?string $dateFrom = null, ?string $dateTo = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $account->journalEntryLines()
            ->with(['journalEntry'])
            ->whereHas('journalEntry', function ($q) use ($dateFrom, $dateTo) {
                $q->where('status', 'posted');
                
                if ($dateFrom) {
                    $q->whereDate('transaction_date', '>=', $dateFrom);
                }
                
                if ($dateTo) {
                    $q->whereDate('transaction_date', '<=', $dateTo);
                }
            })
            ->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    public function generateBalanceSheet(int $tenantId, string $asOfDate): array
    {
        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get();

        $balanceSheet = [
            'as_of_date' => $asOfDate,
            'assets' => [],
            'liabilities' => [],
            'equity' => [],
            'totals' => [
                'total_assets' => 0,
                'total_liabilities' => 0,
                'total_equity' => 0,
            ],
        ];

        foreach ($accounts as $account) {
            $balance = $this->getAccountBalanceAsOf($account, $asOfDate);
            
            if ($balance['balance'] != 0) {
                $accountData = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $balance['balance'],
                ];

                switch ($account->type) {
                    case 'asset':
                        $balanceSheet['assets'][] = $accountData;
                        $balanceSheet['totals']['total_assets'] += $balance['balance'];
                        break;
                    case 'liability':
                        $balanceSheet['liabilities'][] = $accountData;
                        $balanceSheet['totals']['total_liabilities'] += abs($balance['balance']);
                        break;
                    case 'equity':
                        $balanceSheet['equity'][] = $accountData;
                        $balanceSheet['totals']['total_equity'] += abs($balance['balance']);
                        break;
                }
            }
        }

        return $balanceSheet;
    }

    public function generateTrialBalance(int $tenantId, string $asOfDate, bool $includeZeroBalances = false): array
    {
        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $trialBalance = [
            'as_of_date' => $asOfDate,
            'accounts' => [],
            'totals' => [
                'total_debits' => 0,
                'total_credits' => 0,
            ],
        ];

        foreach ($accounts as $account) {
            $balance = $this->getAccountBalanceAsOf($account, $asOfDate);
            
            if ($includeZeroBalances || $balance['debit_total'] != 0 || $balance['credit_total'] != 0) {
                $trialBalance['accounts'][] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'debit_balance' => $balance['debit_total'],
                    'credit_balance' => $balance['credit_total'],
                ];

                $trialBalance['totals']['total_debits'] += $balance['debit_total'];
                $trialBalance['totals']['total_credits'] += $balance['credit_total'];
            }
        }

        return $trialBalance;
    }

    protected function generateAccountCode(int $tenantId, string $type, ?int $parentId = null): string
    {
        $config = config('accounting.chart_of_accounts');
        $length = $config['code_length'];

        if ($parentId) {
            $parent = ChartOfAccount::findOrFail($parentId);
            $prefix = $parent->code;
            
            // Find next available number
            $lastChild = ChartOfAccount::where('tenant_id', $tenantId)
                ->where('parent_id', $parentId)
                ->where('code', 'like', $prefix . '%')
                ->orderBy('code', 'desc')
                ->first();

            if ($lastChild) {
                $lastNumber = intval(substr($lastChild->code, strlen($prefix)));
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            return $prefix . str_pad($nextNumber, $length - strlen($prefix), '0', STR_PAD_LEFT);
        }

        // Root account - use type-based prefix
        $prefixes = [
            'asset' => '1',
            'liability' => '2',
            'equity' => '3',
            'income' => '4',
            'expense' => '5',
        ];

        $prefix = $prefixes[$type] ?? '9';
        
        $lastAccount = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('code', 'like', $prefix . '%')
            ->where('level', 1)
            ->orderBy('code', 'desc')
            ->first();

        if ($lastAccount) {
            $lastNumber = intval(substr($lastAccount->code, 1));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, $length - 1, '0', STR_PAD_LEFT);
    }

    protected function updateChildrenLevels(ChartOfAccount $account, int $levelDifference): void
    {
        $children = $account->children;
        
        foreach ($children as $child) {
            $child->update(['level' => $child->level + $levelDifference]);
            $this->updateChildrenLevels($child, $levelDifference);
        }
    }

    protected function calculateAccountBalance(string $type, float $debitTotal, float $creditTotal): float
    {
        switch ($type) {
            case 'asset':
            case 'expense':
                return $debitTotal - $creditTotal;
            case 'liability':
            case 'equity':
            case 'income':
                return $creditTotal - $debitTotal;
            default:
                return $debitTotal - $creditTotal;
        }
    }

    protected function getAccountBalanceAsOf(ChartOfAccount $account, string $asOfDate): array
    {
        $debitTotal = $account->journalEntryLines()
            ->whereHas('journalEntry', function ($query) use ($asOfDate) {
                $query->where('status', 'posted')
                      ->whereDate('transaction_date', '<=', $asOfDate);
            })
            ->sum('debit_amount');

        $creditTotal = $account->journalEntryLines()
            ->whereHas('journalEntry', function ($query) use ($asOfDate) {
                $query->where('status', 'posted')
                      ->whereDate('transaction_date', '<=', $asOfDate);
            })
            ->sum('credit_amount');

        $balance = $this->calculateAccountBalance($account->type, $debitTotal, $creditTotal);

        return [
            'debit_total' => $debitTotal,
            'credit_total' => $creditTotal,
            'balance' => $balance,
        ];
    }
}