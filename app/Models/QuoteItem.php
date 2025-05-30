<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    protected $fillable = [
        'quote_id',
        'product_id',
        'description',
        'product_code',
        'quantity',
        'unit',
        'unit_price',
        'discount',
        'subtotal',
        'position',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            // Calcular subtotal automáticamente
            if (empty($item->subtotal)) {
                $discountAmount = ($item->unit_price * $item->quantity) * ($item->discount / 100);
                $item->subtotal = ($item->unit_price * $item->quantity) - $discountAmount;
            }
            
            // Asignar posición automáticamente
            if (empty($item->position)) {
                $maxPosition = self::where('quote_id', $item->quote_id)->max('position');
                $item->position = $maxPosition ? $maxPosition + 1 : 1;
            }
        });

        static::updating(function ($item) {
            // Recalcular subtotal al actualizar
            $discountAmount = ($item->unit_price * $item->quantity) * ($item->discount / 100);
            $item->subtotal = ($item->unit_price * $item->quantity) - $discountAmount;
        });
    }

    // Relaciones
    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Helpers
    public function calculateSubtotal()
    {
        $discountAmount = ($this->unit_price * $this->quantity) * ($this->discount / 100);
        return ($this->unit_price * $this->quantity) - $discountAmount;
    }
}
