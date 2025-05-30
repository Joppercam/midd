<?php

namespace App\Modules\Accounting\Models;

use App\Models\TenantAwareModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends TenantAwareModel
{
    protected $fillable = [
        'entry_number',
        'entry_date',
        'reference',
        'description',
        'total_debit',
        'total_credit',
        'status',
        'type',
        'source',
        'source_id',
        'created_by',
        'approved_by',
        'reversed_by',
        'reversal_entry_id',
        'posted_at',
        'reversed_at',
        'reversal_reason',
        'metadata'
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
        'metadata' => 'array'
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function reversalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_entry_id');
    }

    public function originalEntry(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'reversal_entry_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('id');
    }

    public function generalLedgerEntries(): HasMany
    {
        return $this->hasMany(GeneralLedger::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function scopeReversed($query)
    {
        return $query->where('status', 'reversed');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeForPeriod($query, \DateTime $startDate, \DateTime $endDate)
    {
        return $query->whereBetween('entry_date', [$startDate, $endDate]);
    }

    public function getIsPostedAttribute(): bool
    {
        return $this->status === 'posted';
    }

    public function getIsDraftAttribute(): bool
    {
        return $this->status === 'draft';
    }

    public function getIsReversedAttribute(): bool
    {
        return $this->status === 'reversed';
    }

    public function getIsBalancedAttribute(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }

    public function getCanEditAttribute(): bool
    {
        return $this->is_draft;
    }

    public function getCanPostAttribute(): bool
    {
        return $this->is_draft && $this->is_balanced && $this->lines->count() >= 2;
    }

    public function getCanReverseAttribute(): bool
    {
        return $this->is_posted && !$this->reversed_at;
    }

    public function post(?User $user = null): void
    {
        if (!$this->can_post) {
            throw new \Exception('El asiento no puede ser contabilizado');
        }

        $this->update([
            'status' => 'posted',
            'approved_by' => $user?->id ?? auth()->id(),
            'posted_at' => now()
        ]);

        // Crear entradas en el libro mayor
        $this->createGeneralLedgerEntries();

        // Actualizar balances de las cuentas
        $this->updateAccountBalances();
    }

    public function reverse(string $reason, ?User $user = null): JournalEntry
    {
        if (!$this->can_reverse) {
            throw new \Exception('El asiento no puede ser reversado');
        }

        \DB::beginTransaction();
        try {
            // Crear asiento de reversión
            $reversalEntry = static::create([
                'tenant_id' => $this->tenant_id,
                'entry_number' => $this->generateEntryNumber(),
                'entry_date' => now()->toDateString(),
                'reference' => $this->reference,
                'description' => "REVERSIÓN: {$this->description}",
                'total_debit' => $this->total_credit, // Intercambiar débitos y créditos
                'total_credit' => $this->total_debit,
                'status' => 'posted',
                'type' => 'reversal',
                'source' => $this->source,
                'source_id' => $this->source_id,
                'created_by' => $user?->id ?? auth()->id(),
                'approved_by' => $user?->id ?? auth()->id(),
                'posted_at' => now(),
                'metadata' => array_merge($this->metadata ?? [], [
                    'original_entry_id' => $this->id,
                    'reversal_reason' => $reason
                ])
            ]);

            // Crear líneas de reversión (intercambiando débitos y créditos)
            foreach ($this->lines as $line) {
                $reversalEntry->lines()->create([
                    'account_id' => $line->account_id,
                    'description' => "REVERSIÓN: {$line->description}",
                    'debit_amount' => $line->credit_amount, // Intercambiar
                    'credit_amount' => $line->debit_amount,
                    'reference' => $line->reference,
                    'metadata' => array_merge($line->metadata ?? [], [
                        'original_line_id' => $line->id
                    ])
                ]);
            }

            // Marcar el asiento original como reversado
            $this->update([
                'status' => 'reversed',
                'reversed_by' => $user?->id ?? auth()->id(),
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'reversal_entry_id' => $reversalEntry->id
            ]);

            // Crear entradas en el libro mayor para la reversión
            $reversalEntry->createGeneralLedgerEntries();

            // Actualizar balances de las cuentas
            $reversalEntry->updateAccountBalances();

            \DB::commit();

            return $reversalEntry;
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    public function calculateTotals(): void
    {
        $totalDebit = $this->lines()->sum('debit_amount');
        $totalCredit = $this->lines()->sum('credit_amount');

        $this->update([
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit
        ]);
    }

    private function createGeneralLedgerEntries(): void
    {
        foreach ($this->lines as $line) {
            // Calcular el balance actual de la cuenta
            $lastEntry = GeneralLedger::where('account_id', $line->account_id)
                ->where('transaction_date', '<=', $this->entry_date)
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $previousBalance = $lastEntry?->running_balance ?? 0;
            
            // Calcular nuevo balance según la naturaleza de la cuenta
            $account = $line->account;
            if ($account->is_debit_normal) {
                $newBalance = $previousBalance + $line->debit_amount - $line->credit_amount;
            } else {
                $newBalance = $previousBalance + $line->credit_amount - $line->debit_amount;
            }

            GeneralLedger::create([
                'tenant_id' => $this->tenant_id,
                'account_id' => $line->account_id,
                'journal_entry_id' => $this->id,
                'journal_entry_line_id' => $line->id,
                'transaction_date' => $this->entry_date,
                'reference' => $this->reference,
                'description' => $line->description ?: $this->description,
                'debit_amount' => $line->debit_amount,
                'credit_amount' => $line->credit_amount,
                'running_balance' => $newBalance
            ]);
        }
    }

    private function updateAccountBalances(): void
    {
        $accountIds = $this->lines()->pluck('account_id')->unique();
        
        foreach ($accountIds as $accountId) {
            $account = ChartOfAccount::find($accountId);
            $account->updateBalance();
        }
    }

    private function generateEntryNumber(): string
    {
        $prefix = 'JE';
        $year = now()->year;
        $month = now()->month;
        
        $lastEntry = static::where('tenant_id', $this->tenant_id)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('entry_number', 'desc')
            ->first();
        
        if ($lastEntry && preg_match('/' . $prefix . '-' . $year . $month . '-(\d+)/', $lastEntry->entry_number, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }
        
        return $prefix . '-' . $year . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public static function createFromTemplate(array $data, array $lines): self
    {
        \DB::beginTransaction();
        try {
            $entry = static::create($data);
            
            foreach ($lines as $line) {
                $entry->lines()->create($line);
            }
            
            $entry->calculateTotals();
            
            \DB::commit();
            
            return $entry;
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }
}