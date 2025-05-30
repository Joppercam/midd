<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'user_id',
        'receipt_number',
        'received_at',
        'received_by',
        'notes',
        'reference_document',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($receipt) {
            if (!$receipt->receipt_number) {
                $receipt->receipt_number = static::generateReceiptNumber();
            }
        });

        static::created(function ($receipt) {
            $receipt->purchaseOrder->updateReceivedQuantities();
        });
    }

    public static function generateReceiptNumber()
    {
        $prefix = 'REC';
        $year = date('Y');
        $lastReceipt = static::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastReceipt && preg_match('/REC-\d{4}-(\d+)/', $lastReceipt->receipt_number, $matches)) {
            $sequence = intval($matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptItem::class);
    }

    public function getTotalReceivedAttribute(): float
    {
        return $this->items->sum('quantity_received');
    }
}