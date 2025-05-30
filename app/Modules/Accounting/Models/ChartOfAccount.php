<?php

namespace App\Modules\Accounting\Models;

use App\Models\TenantAwareModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends TenantAwareModel
{
    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'subtype',
        'parent_id',
        'level',
        'is_parent',
        'accepts_entries',
        'opening_balance',
        'current_balance',
        'normal_balance',
        'is_active',
        'settings'
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_parent' => 'boolean',
        'accepts_entries' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array'
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('code');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function generalLedgerEntries(): HasMany
    {
        return $this->hasMany(GeneralLedger::class, 'account_id');
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class, 'account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeAcceptsEntries($query)
    {
        return $query->where('accepts_entries', true);
    }

    public function scopeParents($query)
    {
        return $query->where('is_parent', true);
    }

    public function scopeChildren($query)
    {
        return $query->where('is_parent', false);
    }

    public function getFullCodeAttribute(): string
    {
        $codes = [$this->code];
        $parent = $this->parent;
        
        while ($parent) {
            $codes[] = $parent->code;
            $parent = $parent->parent;
        }
        
        return implode('.', array_reverse($codes));
    }

    public function getFullNameAttribute(): string
    {
        $names = [$this->name];
        $parent = $this->parent;
        
        while ($parent) {
            $names[] = $parent->name;
            $parent = $parent->parent;
        }
        
        return implode(' > ', array_reverse($names));
    }

    public function getIsAssetAttribute(): bool
    {
        return $this->type === 'asset';
    }

    public function getIsLiabilityAttribute(): bool
    {
        return $this->type === 'liability';
    }

    public function getIsEquityAttribute(): bool
    {
        return $this->type === 'equity';
    }

    public function getIsRevenueAttribute(): bool
    {
        return $this->type === 'revenue';
    }

    public function getIsExpenseAttribute(): bool
    {
        return $this->type === 'expense';
    }

    public function getIsDebitNormalAttribute(): bool
    {
        return $this->normal_balance === 'debit';
    }

    public function getIsCreditNormalAttribute(): bool
    {
        return $this->normal_balance === 'credit';
    }

    public function calculateBalance(?\DateTime $asOfDate = null): float
    {
        $query = $this->generalLedgerEntries();
        
        if ($asOfDate) {
            $query->where('transaction_date', '<=', $asOfDate);
        }
        
        $debits = $query->sum('debit_amount');
        $credits = $query->sum('credit_amount');
        
        if ($this->is_debit_normal) {
            return $debits - $credits;
        } else {
            return $credits - $debits;
        }
    }

    public function getBalanceForPeriod(\DateTime $startDate, \DateTime $endDate): array
    {
        $entries = $this->generalLedgerEntries()
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->get();

        $openingBalance = $this->calculateBalance($startDate->modify('-1 day'));
        $debits = $entries->sum('debit_amount');
        $credits = $entries->sum('credit_amount');
        
        if ($this->is_debit_normal) {
            $closingBalance = $openingBalance + $debits - $credits;
        } else {
            $closingBalance = $openingBalance + $credits - $debits;
        }

        return [
            'opening_balance' => $openingBalance,
            'debits' => $debits,
            'credits' => $credits,
            'net_change' => $this->is_debit_normal ? $debits - $credits : $credits - $debits,
            'closing_balance' => $closingBalance,
        ];
    }

    public function updateBalance(): void
    {
        $newBalance = $this->calculateBalance();
        $this->update(['current_balance' => $newBalance]);
    }

    public function canDelete(): bool
    {
        // No se puede eliminar si tiene movimientos
        if ($this->journalEntryLines()->exists()) {
            return false;
        }
        
        // No se puede eliminar si tiene cuentas hijas
        if ($this->children()->exists()) {
            return false;
        }
        
        return true;
    }

    public function getHierarchyPath(): array
    {
        $path = [];
        $current = $this;
        
        while ($current) {
            $path[] = [
                'id' => $current->id,
                'code' => $current->code,
                'name' => $current->name,
                'level' => $current->level
            ];
            $current = $current->parent;
        }
        
        return array_reverse($path);
    }

    public static function generateCode(?string $parentCode = null, ?string $type = null): string
    {
        if (!$parentCode) {
            // Códigos principales por tipo
            $typeCodes = [
                'asset' => '1',
                'liability' => '2',
                'equity' => '3',
                'revenue' => '4',
                'expense' => '5'
            ];
            
            return $typeCodes[$type] ?? '9';
        }
        
        // Buscar el siguiente código disponible para este padre
        $siblings = static::where('parent_id', function ($query) use ($parentCode) {
            $query->select('id')
                ->from('chart_of_accounts')
                ->where('code', $parentCode)
                ->limit(1);
        })->orderBy('code', 'desc')->first();
        
        if (!$siblings) {
            return $parentCode . '.01';
        }
        
        // Extraer el último número y incrementar
        $lastCode = explode('.', $siblings->code);
        $lastNumber = intval(end($lastCode));
        $nextNumber = str_pad($lastNumber + 1, 2, '0', STR_PAD_LEFT);
        
        return $parentCode . '.' . $nextNumber;
    }
}