<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxDocumentItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'tax_document_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_percentage',
        'discount_amount',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function taxDocument(): BelongsTo
    {
        return $this->belongsTo(TaxDocument::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::creating(function ($item) {
            $item->subtotal = $item->quantity * $item->unit_price;
            $item->tax_rate = $item->tax_rate ?? 19; // IVA 19% default
            $item->tax_amount = $item->subtotal * ($item->tax_rate / 100);
            $item->total = $item->subtotal + $item->tax_amount - ($item->discount_amount ?? 0);
        });

        static::updating(function ($item) {
            $item->subtotal = $item->quantity * $item->unit_price;
            $item->tax_rate = $item->tax_rate ?? 19; // IVA 19% default
            $item->tax_amount = $item->subtotal * ($item->tax_rate / 100);
            $item->total = $item->subtotal + $item->tax_amount - ($item->discount_amount ?? 0);
        });
    }
}