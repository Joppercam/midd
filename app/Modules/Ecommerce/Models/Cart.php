<?php

namespace App\Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $table = 'ecommerce_carts';

    protected $fillable = [
        'store_id',
        'customer_id',
        'session_id',
        'status',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'total',
        'currency',
        'metadata',
        'abandoned_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'metadata' => 'array',
        'abandoned_at' => 'datetime'
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAbandoned($query)
    {
        return $query->where('status', 'abandoned');
    }

    public function scopeConverted($query)
    {
        return $query->where('status', 'converted');
    }

    public function getIsEmptyAttribute(): bool
    {
        return $this->items->count() === 0;
    }

    public function getItemCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return '$' . number_format($this->subtotal, 0, ',', '.');
    }

    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format($this->total, 0, ',', '.');
    }

    public function addItem(Product $product, int $quantity = 1, ?ProductVariant $variant = null, array $customOptions = []): CartItem
    {
        // Verificar si el producto ya está en el carrito
        $existingItem = $this->items()
            ->where('product_id', $product->id)
            ->where('variant_id', $variant?->id)
            ->first();

        if ($existingItem) {
            // Actualizar cantidad
            $existingItem->increment('quantity', $quantity);
            $existingItem->updateTotal();
            return $existingItem;
        }

        // Crear nuevo item
        $price = $variant?->price ?? $product->price;
        
        $item = $this->items()->create([
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'quantity' => $quantity,
            'price' => $price,
            'total' => $price * $quantity,
            'custom_options' => $customOptions
        ]);

        $this->calculateTotals();

        return $item;
    }

    public function updateItem(CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $item->delete();
        } else {
            $item->update(['quantity' => $quantity]);
            $item->updateTotal();
        }

        $this->calculateTotals();
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
        $this->calculateTotals();
    }

    public function clear(): void
    {
        $this->items()->delete();
        $this->calculateTotals();
    }

    public function calculateTotals(): void
    {
        $this->load('items.product');
        
        $subtotal = $this->items->sum('total');
        
        // TODO: Calcular impuestos según configuración
        $taxRate = 0.19; // 19% IVA
        $taxAmount = $subtotal * $taxRate;
        
        // TODO: Calcular envío según método seleccionado
        $shippingAmount = $this->shipping_amount ?? 0;
        
        // TODO: Aplicar cupones de descuento
        $discountAmount = $this->discount_amount ?? 0;
        
        $total = $subtotal + $taxAmount + $shippingAmount - $discountAmount;
        
        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total
        ]);
    }

    public function applyCoupon(Coupon $coupon): void
    {
        if (!$coupon->isValidFor($this)) {
            throw new \Exception('Cupón no válido');
        }

        $discountAmount = $coupon->calculateDiscount($this);
        
        $this->update([
            'discount_amount' => $discountAmount,
            'metadata' => array_merge($this->metadata ?? [], [
                'coupon_code' => $coupon->code,
                'coupon_id' => $coupon->id
            ])
        ]);
        
        $this->calculateTotals();
    }

    public function removeCoupon(): void
    {
        $metadata = $this->metadata ?? [];
        unset($metadata['coupon_code'], $metadata['coupon_id']);
        
        $this->update([
            'discount_amount' => 0,
            'metadata' => $metadata
        ]);
        
        $this->calculateTotals();
    }

    public function setShippingMethod(ShippingMethod $method): void
    {
        $shippingAmount = $method->calculateRate($this);
        
        $this->update([
            'shipping_amount' => $shippingAmount,
            'metadata' => array_merge($this->metadata ?? [], [
                'shipping_method_id' => $method->id,
                'shipping_method_name' => $method->name
            ])
        ]);
        
        $this->calculateTotals();
    }

    public function markAsAbandoned(): void
    {
        if ($this->status === 'active' && !$this->is_empty) {
            $this->update([
                'status' => 'abandoned',
                'abandoned_at' => now()
            ]);
        }
    }

    public function markAsConverted(Order $order): void
    {
        $this->update([
            'status' => 'converted',
            'metadata' => array_merge($this->metadata ?? [], [
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ])
        ]);
    }

    public function canCheckout(): array
    {
        $errors = [];
        
        if ($this->is_empty) {
            $errors[] = 'El carrito está vacío';
        }
        
        // Verificar stock de cada item
        foreach ($this->items as $item) {
            if (!$item->product->canAddToCart($item->quantity)) {
                $errors[] = "No hay suficiente stock de {$item->product->name}";
            }
        }
        
        return $errors;
    }

    public function merge(Cart $otherCart): void
    {
        foreach ($otherCart->items as $item) {
            $this->addItem(
                $item->product,
                $item->quantity,
                $item->variant,
                $item->custom_options ?? []
            );
        }
        
        // Mantener cupón del carrito más reciente
        if ($otherCart->metadata['coupon_code'] ?? null) {
            $this->update([
                'metadata' => array_merge($this->metadata ?? [], [
                    'coupon_code' => $otherCart->metadata['coupon_code'],
                    'coupon_id' => $otherCart->metadata['coupon_id']
                ])
            ]);
        }
        
        $otherCart->clear();
        $otherCart->delete();
    }
}