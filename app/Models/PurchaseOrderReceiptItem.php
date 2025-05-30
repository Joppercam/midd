<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_receipt_id',
        'purchase_order_item_id',
        'quantity_received',
        'condition',
        'notes',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:2',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderReceipt::class, 'purchase_order_receipt_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function getConditionColorAttribute(): string
    {
        return match($this->condition) {
            'good' => 'green',
            'damaged' => 'yellow',
            'rejected' => 'red',
            default => 'gray',
        };
    }

    public function getConditionLabelAttribute(): string
    {
        return match($this->condition) {
            'good' => 'Bueno',
            'damaged' => 'Dañado',
            'rejected' => 'Rechazado',
            default => 'Desconocido',
        };
    }
}