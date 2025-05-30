<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\BelongsToTenant;

class BankTransactionMatch extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'bank_transaction_id',
        'tenant_id',
        'matchable_type',
        'matchable_id',
        'match_type',
        'matched_amount',
        'confidence_score',
        'match_method',
        'match_details',
        'matched_by',
        'bank_reconciliation_id'
    ];

    protected $casts = [
        'matched_amount' => 'decimal:2',
        'confidence_score' => 'float',
        'match_details' => 'array'
    ];

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function matchable(): MorphTo
    {
        return $this->morphTo();
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function bankReconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class);
    }

    public static function createMatch(
        BankTransaction $transaction,
        Model $matchable,
        string $matchType,
        array $options = []
    ): self {
        // Remove any existing matches for this transaction
        $transaction->matches()->delete();
        
        $match = self::create([
            'bank_transaction_id' => $transaction->id,
            'tenant_id' => $transaction->tenant_id,
            'matchable_type' => get_class($matchable),
            'matchable_id' => $matchable->id,
            'match_type' => $matchType,
            'matched_amount' => $options['amount'] ?? abs($transaction->amount),
            'confidence_score' => $options['confidence_score'] ?? 100,
            'match_method' => $options['match_method'] ?? 'manual',
            'match_details' => $options['match_details'] ?? null,
            'matched_by' => $options['matched_by'] ?? auth()->id(),
            'bank_reconciliation_id' => $options['bank_reconciliation_id'] ?? null
        ]);
        
        // Update transaction status
        $transaction->markAsMatched();
        
        // Update related model if it has a payment status
        if (method_exists($matchable, 'markAsPaid')) {
            $matchable->markAsPaid();
        }
        
        return $match;
    }

    public function unmatch(): void
    {
        $transaction = $this->bankTransaction;
        $matchable = $this->matchable;
        
        // Delete the match
        $this->delete();
        
        // Update transaction status
        $transaction->unmatch();
        
        // Update related model if needed
        if (method_exists($matchable, 'markAsUnpaid')) {
            $matchable->markAsUnpaid();
        }
    }

    public function getMatchTypeLabel(): string
    {
        return match($this->match_type) {
            'payment_received' => 'Pago Recibido',
            'payment_made' => 'Pago Realizado',
            'invoice' => 'Factura',
            'expense' => 'Gasto',
            'expense_payment' => 'Pago de Gasto',
            'transfer' => 'Transferencia',
            'adjustment' => 'Ajuste',
            default => ucfirst(str_replace('_', ' ', $this->match_type))
        };
    }

    public function getMatchMethodLabel(): string
    {
        return match($this->match_method) {
            'manual' => 'Manual',
            'auto_reference' => 'Auto (Referencia)',
            'auto_amount' => 'Auto (Monto)',
            'auto_date' => 'Auto (Fecha)',
            'auto_combined' => 'Auto (Combinado)',
            default => ucfirst($this->match_method)
        };
    }

    public function getConfidenceLabel(): string
    {
        if ($this->confidence_score >= 90) {
            return 'Alta';
        } elseif ($this->confidence_score >= 70) {
            return 'Media';
        } else {
            return 'Baja';
        }
    }

    public function getConfidenceColor(): string
    {
        if ($this->confidence_score >= 90) {
            return 'green';
        } elseif ($this->confidence_score >= 70) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    public function scopeByType($query, $type)
    {
        return $query->where('match_type', $type);
    }

    public function scopeManual($query)
    {
        return $query->where('match_method', 'manual');
    }

    public function scopeAutomatic($query)
    {
        return $query->where('match_method', '!=', 'manual');
    }

    public function scopeHighConfidence($query, $threshold = 90)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }
}