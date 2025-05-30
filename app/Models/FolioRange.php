<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class FolioRange extends TenantAwareModel
{
    use Auditable;

    protected $fillable = [
        'tenant_id',
        'document_type',
        'start_folio',
        'end_folio',
        'current_folio',
        'caf_file_path',
        'caf_content',
        'authorization_date',
        'expiration_date',
        'is_active',
        'is_exhausted',
        'alert_threshold',
        'environment',
        'metadata',
    ];

    protected $casts = [
        'authorization_date' => 'date',
        'expiration_date' => 'date',
        'is_active' => 'boolean',
        'is_exhausted' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Document type mappings
     */
    const DOCUMENT_TYPES = [
        33 => 'Factura Electrónica',
        34 => 'Factura No Afecta o Exenta Electrónica',
        39 => 'Boleta Electrónica',
        52 => 'Guía de Despacho Electrónica',
        56 => 'Nota de Débito Electrónica',
        61 => 'Nota de Crédito Electrónica',
    ];

    /**
     * Get the tenant that owns this folio range
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the folio assignments for this range
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(FolioAssignment::class);
    }

    /**
     * Get the CAF file for this range
     */
    public function cafFile()
    {
        return $this->hasOne(CafFile::class);
    }

    /**
     * Get remaining folios count
     */
    public function getRemainingFoliosAttribute(): int
    {
        return max(0, $this->end_folio - $this->current_folio + 1);
    }

    /**
     * Get used folios count
     */
    public function getUsedFoliosAttribute(): int
    {
        return $this->current_folio - $this->start_folio;
    }

    /**
     * Get total folios count
     */
    public function getTotalFoliosAttribute(): int
    {
        return $this->end_folio - $this->start_folio + 1;
    }

    /**
     * Get usage percentage
     */
    public function getUsagePercentageAttribute(): float
    {
        if ($this->total_folios == 0) return 0;
        return round(($this->used_folios / $this->total_folios) * 100, 2);
    }

    /**
     * Check if alert should be triggered
     */
    public function getShouldAlertAttribute(): bool
    {
        return $this->remaining_folios <= $this->alert_threshold && !$this->is_exhausted;
    }

    /**
     * Check if range is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    /**
     * Check if range can be used
     */
    public function getCanBeUsedAttribute(): bool
    {
        return $this->is_active && 
               !$this->is_exhausted && 
               !$this->is_expired &&
               $this->remaining_folios > 0;
    }

    /**
     * Get next available folio
     */
    public function getNextFolio(): ?int
    {
        if (!$this->can_be_used) {
            return null;
        }

        $nextFolio = $this->current_folio + 1;
        
        if ($nextFolio > $this->end_folio) {
            return null;
        }

        return $nextFolio;
    }

    /**
     * Mark range as exhausted
     */
    public function markAsExhausted(): void
    {
        $this->update([
            'is_exhausted' => true,
            'is_active' => false,
        ]);
    }

    /**
     * Scope to get active ranges
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('is_exhausted', false)
                    ->where(function ($q) {
                        $q->whereNull('expiration_date')
                          ->orWhere('expiration_date', '>', now());
                    });
    }

    /**
     * Scope to get ranges by document type
     */
    public function scopeForDocumentType($query, int $documentType)
    {
        return $query->where('document_type', $documentType);
    }

    /**
     * Scope to get ranges for environment
     */
    public function scopeForEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }
}