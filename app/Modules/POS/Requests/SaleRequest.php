<?php

namespace App\Modules\POS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:255',
            
            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|in:cash,credit_card,debit_card,check,transfer',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.reference' => 'required_if:payments.*.method,credit_card,debit_card,check,transfer|nullable|string|max:100',
            'payments.*.authorization' => 'nullable|string|max:100',
            
            'discounts' => 'nullable|array',
            'discounts.*.type' => 'required_with:discounts|in:percentage,fixed,loyalty',
            'discounts.*.value' => 'required_with:discounts|numeric|min:0',
            'discounts.*.reason' => 'required_with:discounts|string|max:255',
            'discounts.*.manager_pin' => 'nullable|string',
            
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:500',
            'loyalty_points_used' => 'nullable|integer|min:0',
            'training_mode' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Debe agregar al menos un producto',
            'items.min' => 'Debe agregar al menos un producto',
            'items.*.product_id.required' => 'El producto es obligatorio',
            'items.*.product_id.exists' => 'El producto seleccionado no existe',
            'items.*.quantity.required' => 'La cantidad es obligatoria',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a 0',
            'items.*.unit_price.required' => 'El precio unitario es obligatorio',
            'items.*.unit_price.min' => 'El precio debe ser mayor o igual a 0',
            
            'payments.required' => 'Debe agregar al menos un método de pago',
            'payments.min' => 'Debe agregar al menos un método de pago',
            'payments.*.method.required' => 'El método de pago es obligatorio',
            'payments.*.method.in' => 'Método de pago no válido',
            'payments.*.amount.required' => 'El monto del pago es obligatorio',
            'payments.*.amount.min' => 'El monto del pago debe ser mayor a 0',
            'payments.*.reference.required_if' => 'La referencia es obligatoria para este método de pago',
            
            'discounts.*.type.required_with' => 'El tipo de descuento es obligatorio',
            'discounts.*.type.in' => 'Tipo de descuento no válido',
            'discounts.*.value.required_with' => 'El valor del descuento es obligatorio',
            'discounts.*.value.min' => 'El valor del descuento debe ser mayor a 0',
            'discounts.*.reason.required_with' => 'La razón del descuento es obligatoria',
            
            'customer_id.exists' => 'El cliente seleccionado no existe',
            'loyalty_points_used.min' => 'Los puntos de lealtad deben ser mayor o igual a 0',
        ];
    }

    public function prepareForValidation(): void
    {
        // Establecer training_mode como false por defecto
        if (!$this->has('training_mode')) {
            $this->merge(['training_mode' => false]);
        }

        // Calcular tax_rate por defecto si no se proporciona
        if (!$this->has('tax_rate')) {
            $this->merge(['tax_rate' => config('pos.sales.tax_rate', 19)]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que el total de pagos cubra el total de la venta
            $itemsTotal = 0;
            $discountTotal = 0;
            $paymentsTotal = 0;

            // Calcular total de items
            foreach ($this->get('items', []) as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $lineDiscount = $item['discount_amount'] ?? 0;
                $itemsTotal += $lineTotal - $lineDiscount;
            }

            // Calcular descuentos globales
            foreach ($this->get('discounts', []) as $discount) {
                if ($discount['type'] === 'percentage') {
                    $discountTotal += $itemsTotal * ($discount['value'] / 100);
                } else {
                    $discountTotal += $discount['value'];
                }
            }

            // Calcular total de pagos
            foreach ($this->get('payments', []) as $payment) {
                $paymentsTotal += $payment['amount'];
            }

            // Aplicar impuestos
            $taxRate = $this->get('tax_rate', 19) / 100;
            $subtotal = $itemsTotal - $discountTotal;
            $taxAmount = $subtotal * $taxRate;
            $total = $subtotal + $taxAmount;

            // Validar que los pagos cubran el total
            if ($paymentsTotal < $total) {
                $validator->errors()->add('payments', 'El total de pagos no cubre el monto de la venta');
            }

            // Validar descuentos que requieren aprobación
            foreach ($this->get('discounts', []) as $index => $discount) {
                if ($discount['type'] === 'percentage' && $discount['value'] > config('pos.discounts.max_discount_without_approval', 10)) {
                    if (empty($discount['manager_pin'])) {
                        $validator->errors()->add("discounts.{$index}.manager_pin", 'Se requiere PIN gerencial para este descuento');
                    }
                }
            }

            // Validar uso de puntos de lealtad
            if ($this->get('loyalty_points_used', 0) > 0) {
                $customerId = $this->get('customer_id');
                if (!$customerId) {
                    $validator->errors()->add('customer_id', 'Debe seleccionar un cliente para usar puntos de lealtad');
                }
            }
        });
    }
}