<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseBook extends Model
{
    use HasFactory, BelongsToTenant, Auditable;

    protected $fillable = [
        'tenant_id',
        'year',
        'month',
        'total_documents',
        'total_exempt',
        'total_net',
        'total_tax',
        'total_amount',
        'total_withholding',
        'total_other_taxes',
        'status',
        'generated_at',
        'sent_at',
        'file_path',
        'summary',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'total_documents' => 'integer',
        'total_exempt' => 'decimal:2',
        'total_net' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'total_withholding' => 'decimal:2',
        'total_other_taxes' => 'decimal:2',
        'generated_at' => 'datetime',
        'sent_at' => 'datetime',
        'summary' => 'array',
    ];

    /**
     * Get the entries for the purchase book.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(PurchaseBookEntry::class);
    }

    /**
     * Get the period name.
     */
    public function getPeriodNameAttribute(): string
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        return $months[$this->month] . ' ' . $this->year;
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft' => 'Borrador',
            'final' => 'Final',
            'sent' => 'Enviado',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the status color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'final' => 'blue',
            'sent' => 'green',
            default => 'gray',
        };
    }

    /**
     * Check if the book can be edited.
     */
    public function canEdit(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the book can be finalized.
     */
    public function canFinalize(): bool
    {
        return $this->status === 'draft' && $this->entries()->count() > 0;
    }

    /**
     * Check if the book can be sent.
     */
    public function canSend(): bool
    {
        return $this->status === 'final';
    }

    /**
     * Calculate totals from entries.
     */
    public function calculateTotals(): void
    {
        $totals = $this->entries()
            ->selectRaw('
                COUNT(*) as total_documents,
                SUM(exempt_amount) as total_exempt,
                SUM(net_amount) as total_net,
                SUM(tax_amount) as total_tax,
                SUM(total_amount) as total_amount,
                SUM(withholding_amount) as total_withholding,
                SUM(other_taxes) as total_other_taxes
            ')
            ->first();

        $this->update([
            'total_documents' => $totals->total_documents ?? 0,
            'total_exempt' => $totals->total_exempt ?? 0,
            'total_net' => $totals->total_net ?? 0,
            'total_tax' => $totals->total_tax ?? 0,
            'total_amount' => $totals->total_amount ?? 0,
            'total_withholding' => $totals->total_withholding ?? 0,
            'total_other_taxes' => $totals->total_other_taxes ?? 0,
        ]);
    }

    /**
     * Generate summary by document type.
     */
    public function generateSummary(): array
    {
        $summary = $this->entries()
            ->groupBy('document_type')
            ->selectRaw('
                document_type,
                COUNT(*) as count,
                SUM(exempt_amount) as exempt,
                SUM(net_amount) as net,
                SUM(tax_amount) as tax,
                SUM(total_amount) as total
            ')
            ->get()
            ->toArray();

        $this->update(['summary' => $summary]);

        return $summary;
    }

    /**
     * Scope for period.
     */
    public function scopePeriod($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    /**
     * Scope for status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}