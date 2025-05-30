<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class FolioAssignment extends TenantAwareModel
{
    use Auditable;

    protected $fillable = [
        'tenant_id',
        'folio_range_id',
        'tax_document_id',
        'document_type',
        'folio_number',
        'status',
        'assigned_at',
        'used_at',
        'cancelled_at',
        'recycled_at',
        'cancelled_reason',
        'assigned_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'used_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'recycled_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_USED = 'used';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_RECYCLED = 'recycled';

    /**
     * Get the tenant that owns this assignment
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the folio range
     */
    public function folioRange(): BelongsTo
    {
        return $this->belongsTo(FolioRange::class);
    }

    /**
     * Get the tax document
     */
    public function taxDocument(): BelongsTo
    {
        return $this->belongsTo(TaxDocument::class);
    }

    /**
     * Get the user who assigned this folio
     */
    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Mark folio as used
     */
    public function markAsUsed(): void
    {
        $this->update([
            'status' => self::STATUS_USED,
            'used_at' => now(),
        ]);
    }

    /**
     * Cancel folio assignment
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_reason' => $reason,
        ]);
    }

    /**
     * Recycle folio for reuse
     */
    public function recycle(): void
    {
        if ($this->status !== self::STATUS_CANCELLED) {
            throw new \Exception('Only cancelled folios can be recycled');
        }

        $this->update([
            'status' => self::STATUS_RECYCLED,
            'recycled_at' => now(),
            'tax_document_id' => null,
        ]);
    }

    /**
     * Check if folio can be used
     */
    public function getCanBeUsedAttribute(): bool
    {
        return $this->status === self::STATUS_ASSIGNED;
    }

    /**
     * Check if folio can be recycled
     */
    public function getCanBeRecycledAttribute(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Scope for assigned folios
     */
    public function scopeAssigned($query)
    {
        return $query->where('status', self::STATUS_ASSIGNED);
    }

    /**
     * Scope for used folios
     */
    public function scopeUsed($query)
    {
        return $query->where('status', self::STATUS_USED);
    }

    /**
     * Scope for cancelled folios
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope for recyclable folios
     */
    public function scopeRecyclable($query)
    {
        return $query->cancelled()
                    ->where('cancelled_at', '<=', now()->subHours(24));
    }
}