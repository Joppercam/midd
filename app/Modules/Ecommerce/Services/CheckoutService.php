<?php

namespace App\Modules\Ecommerce\Services;

use App\Modules\Ecommerce\Models\Cart;
use App\Modules\Ecommerce\Models\Customer;
use App\Modules\Ecommerce\Models\Order;
use App\Modules\Ecommerce\Models\PaymentMethod;
use App\Modules\Ecommerce\Models\ShippingMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutService
{
    private PaymentGatewayFactory $paymentGatewayFactory;

    public function __construct(PaymentGatewayFactory $paymentGatewayFactory)
    {
        $this->paymentGatewayFactory = $paymentGatewayFactory;
    }

    /**
     * Procesar checkout completo
     */
    public function processCheckout(
        Cart $cart,
        array $customerData,
        array $billingAddress,
        array $shippingAddress,
        int $shippingMethodId,
        int $paymentMethodId,
        array $paymentData = []
    ): Order {
        // Validar carrito
        $errors = $cart->canCheckout();
        if (!empty($errors)) {
            throw new \Exception('Carrito no válido: ' . implode(', ', $errors));
        }

        DB::beginTransaction();
        try {
            // Crear o actualizar cliente
            $customer = $this->processCustomer($cart, $customerData);

            // Obtener métodos de envío y pago
            $shippingMethod = ShippingMethod::findOrFail($shippingMethodId);
            $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);

            // Crear orden
            $order = $this->createOrder(
                $cart,
                $customer,
                $billingAddress,
                $shippingAddress,
                $shippingMethod,
                $paymentMethod
            );

            // Procesar pago
            if ($paymentMethod->code !== 'bank_transfer') {
                $this->processPayment($order, $paymentMethod, $paymentData);
            }

            // Marcar carrito como convertido
            $cart->markAsConverted($order);

            // Enviar confirmación
            $order->sendConfirmationEmail();

            DB::commit();

            return $order;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en checkout: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Procesar datos del cliente
     */
    private function processCustomer(Cart $cart, array $customerData): Customer
    {
        // Si el carrito ya tiene cliente, actualizar datos
        if ($cart->customer_id) {
            $customer = $cart->customer;
            $customer->update([
                'first_name' => $customerData['first_name'],
                'last_name' => $customerData['last_name'],
                'phone' => $customerData['phone'] ?? $customer->phone,
            ]);
            return $customer;
        }

        // Buscar cliente existente por email
        $customer = Customer::where('store_id', $cart->store_id)
            ->where('email', $customerData['email'])
            ->first();

        if ($customer) {
            // Actualizar datos
            $customer->update([
                'first_name' => $customerData['first_name'],
                'last_name' => $customerData['last_name'],
                'phone' => $customerData['phone'] ?? $customer->phone,
            ]);
        } else {
            // Crear nuevo cliente
            $customer = Customer::create([
                'store_id' => $cart->store_id,
                'email' => $customerData['email'],
                'first_name' => $customerData['first_name'],
                'last_name' => $customerData['last_name'],
                'phone' => $customerData['phone'] ?? null,
                'type' => $customerData['type'] ?? 'b2c',
                'company_name' => $customerData['company_name'] ?? null,
                'tax_id' => $customerData['tax_id'] ?? null,
                'accepts_marketing' => $customerData['accepts_marketing'] ?? false,
            ]);
        }

        // Asignar cliente al carrito
        $cart->update(['customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * Crear orden desde carrito
     */
    private function createOrder(
        Cart $cart,
        Customer $customer,
        array $billingAddress,
        array $shippingAddress,
        ShippingMethod $shippingMethod,
        PaymentMethod $paymentMethod
    ): Order {
        // Generar número de orden
        $orderNumber = $cart->store->generateOrderNumber();

        // Crear orden
        $order = Order::create([
            'store_id' => $cart->store_id,
            'customer_id' => $customer->id,
            'order_number' => $orderNumber,
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'subtotal' => $cart->subtotal,
            'tax_amount' => $cart->tax_amount,
            'shipping_amount' => $cart->shipping_amount,
            'discount_amount' => $cart->discount_amount,
            'total' => $cart->total,
            'currency' => $cart->currency,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'billing_address' => $billingAddress,
            'shipping_address' => $shippingAddress,
            'payment_method' => $paymentMethod->name,
            'shipping_method' => $shippingMethod->name,
            'metadata' => [
                'payment_method_id' => $paymentMethod->id,
                'shipping_method_id' => $shippingMethod->id,
                'coupon_code' => $cart->metadata['coupon_code'] ?? null,
            ],
            'source' => 'web'
        ]);

        // Crear items de la orden
        foreach ($cart->items as $cartItem) {
            $order->items()->create([
                'product_id' => $cartItem->product_id,
                'variant_id' => $cartItem->variant_id,
                'name' => $cartItem->product->name,
                'sku' => $cartItem->variant?->sku ?? $cartItem->product->inventoryProduct->sku,
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->price,
                'total' => $cartItem->total,
                'product_options' => $cartItem->custom_options
            ]);
        }

        // Actualizar estadísticas del cliente
        $customer->increment('orders_count');
        $customer->increment('total_spent', $order->total);
        $customer->update(['last_order_at' => now()]);

        // Aplicar uso del cupón si existe
        if ($cart->metadata['coupon_id'] ?? null) {
            $coupon = $cart->store->coupons()->find($cart->metadata['coupon_id']);
            if ($coupon) {
                $coupon->increment('usage_count');
            }
        }

        return $order;
    }

    /**
     * Procesar pago
     */
    private function processPayment(Order $order, PaymentMethod $paymentMethod, array $paymentData): void
    {
        $gateway = $this->paymentGatewayFactory->create($paymentMethod);
        
        $result = $gateway->processPayment($order, $paymentData);

        if ($result['success']) {
            $order->markAsPaid($result['transaction_data'] ?? []);
        } else {
            throw new \Exception('Error al procesar el pago: ' . ($result['message'] ?? 'Error desconocido'));
        }
    }

    /**
     * Calcular costos de envío para métodos disponibles
     */
    public function calculateShippingRates(Cart $cart, array $shippingAddress): array
    {
        $methods = $cart->store->shippingMethods()
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        $rates = [];

        foreach ($methods as $method) {
            $rate = $method->calculateRate($cart, $shippingAddress);
            
            if ($rate !== null) {
                $rates[] = [
                    'id' => $method->id,
                    'name' => $method->name,
                    'description' => $method->description,
                    'rate' => $rate,
                    'formatted_rate' => '$' . number_format($rate, 0, ',', '.'),
                    'estimated_days' => $method->getEstimatedDays($shippingAddress),
                ];
            }
        }

        return $rates;
    }

    /**
     * Validar dirección
     */
    public function validateAddress(array $address): array
    {
        $required = ['first_name', 'last_name', 'address_line_1', 'city', 'state', 'postal_code'];
        $errors = [];

        foreach ($required as $field) {
            if (empty($address[$field])) {
                $errors[$field] = 'Este campo es requerido';
            }
        }

        // Validar código postal
        if (!empty($address['postal_code']) && !preg_match('/^\d{7}$/', $address['postal_code'])) {
            $errors['postal_code'] = 'Código postal inválido';
        }

        // Validar teléfono si existe
        if (!empty($address['phone']) && !preg_match('/^(\+?56)?[2-9]\d{8}$/', $address['phone'])) {
            $errors['phone'] = 'Teléfono inválido';
        }

        return $errors;
    }

    /**
     * Obtener métodos de pago disponibles
     */
    public function getAvailablePaymentMethods(Cart $cart): array
    {
        return $cart->store->paymentMethods()
            ->where('is_active', true)
            ->orderBy('position')
            ->get()
            ->map(function ($method) use ($cart) {
                return [
                    'id' => $method->id,
                    'name' => $method->name,
                    'code' => $method->code,
                    'description' => $method->description,
                    'fee' => $method->calculateFee($cart->total),
                    'formatted_fee' => $method->calculateFee($cart->total) > 0 
                        ? '$' . number_format($method->calculateFee($cart->total), 0, ',', '.')
                        : 'Sin costo adicional',
                ];
            })
            ->toArray();
    }

    /**
     * Aplicar impuestos según dirección
     */
    public function calculateTaxes(Cart $cart, array $billingAddress): float
    {
        // TODO: Implementar cálculo de impuestos según región
        // Por ahora, aplicar IVA estándar
        $taxRate = 0.19; // 19% IVA
        
        return $cart->subtotal * $taxRate;
    }

    /**
     * Verificar disponibilidad final antes de confirmar
     */
    public function verifyAvailability(Cart $cart): array
    {
        $unavailableItems = [];

        foreach ($cart->items as $item) {
            if (!$item->product->canAddToCart($item->quantity)) {
                $unavailableItems[] = [
                    'product' => $item->product->name,
                    'requested' => $item->quantity,
                    'available' => $item->product->available_quantity
                ];
            }
        }

        return $unavailableItems;
    }
}