<?php

namespace App\Modules\Ecommerce\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Modules\Ecommerce\Models\ShoppingCart;
use App\Modules\Ecommerce\Models\EcommerceProduct;
use App\Modules\Ecommerce\Models\Coupon;
use Illuminate\Support\Facades\Session;

class CartService
{
    /**
     * Get or create cart for current user/session
     */
    public function getCurrentCart(): ShoppingCart
    {
        $user = auth()->user();
        $sessionId = Session::getId();

        if ($user && $user instanceof Customer) {
            // For authenticated customers
            $cart = ShoppingCart::where('customer_id', $user->id)
                ->where('tenant_id', tenant()->id)
                ->active()
                ->first();

            if (!$cart) {
                $cart = $this->createCart($user->id, null);
            }
        } else {
            // For guest users
            $cart = ShoppingCart::where('session_id', $sessionId)
                ->where('tenant_id', tenant()->id)
                ->active()
                ->first();

            if (!$cart) {
                $cart = $this->createCart(null, $sessionId);
            }
        }

        return $cart;
    }

    /**
     * Create new cart
     */
    protected function createCart(?string $customerId = null, ?string $sessionId = null): ShoppingCart
    {
        return ShoppingCart::create([
            'tenant_id' => tenant()->id,
            'customer_id' => $customerId,
            'session_id' => $sessionId,
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Add product to cart
     */
    public function addToCart(string $productId, int $quantity = 1, ?array $variantData = null): array
    {
        $product = Product::findOrFail($productId);
        $ecommerceProduct = EcommerceProduct::where('product_id', $productId)
            ->where('is_published', true)
            ->firstOrFail();

        // Check if product is in stock
        if (!$ecommerceProduct->isInStock() || $ecommerceProduct->available_quantity < $quantity) {
            return [
                'success' => false,
                'message' => 'Insufficient stock available.',
                'available_quantity' => $ecommerceProduct->available_quantity,
            ];
        }

        $cart = $this->getCurrentCart();
        $item = $cart->addItem($ecommerceProduct, $quantity, $variantData);

        return [
            'success' => true,
            'message' => 'Product added to cart successfully.',
            'cart' => $cart->load('items.product'),
            'item' => $item,
        ];
    }

    /**
     * Get cart summary
     */
    public function getCartSummary(): array
    {
        $cart = $this->getCurrentCart();
        $cart->load('items.product');

        return [
            'cart' => $cart,
            'item_count' => $cart->total_items,
            'subtotal' => $cart->subtotal,
            'tax_amount' => $cart->tax_amount,
            'shipping_amount' => $cart->shipping_amount,
            'discount_amount' => $cart->discount_amount,
            'total' => $cart->total,
            'is_empty' => $cart->isEmpty(),
        ];
    }
}