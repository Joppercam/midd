<?php

namespace App\Modules\Invoicing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'customer_id' => 'required|exists:customers,id',
            'date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01|max:999999999.99',
            'payment_method' => 'required|string|in:' . implode(',', array_keys(config('invoicing.payment_methods', []))),
            'reference' => 'nullable|string|max:255',
            'bank' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:pending,confirmed,cancelled',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'allocations' => 'nullable|array',
            'allocations.*.tax_document_id' => 'required|exists:tax_documents,id',
            'allocations.*.amount' => 'required|numeric|min:0.01',
            'allocations.*.notes' => 'nullable|string|max:500',
        ];

        // For bank transfers, require bank account
        if ($this->payment_method === 'bank_transfer') {
            $rules['bank_account_id'] = 'required|exists:bank_accounts,id';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'El cliente es obligatorio.',
            'customer_id.exists' => 'El cliente seleccionado no es válido.',
            'date.required' => 'La fecha de pago es obligatoria.',
            'date.before_or_equal' => 'La fecha de pago no puede ser futura.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'amount.max' => 'El monto excede el límite permitido.',
            'payment_method.required' => 'El método de pago es obligatorio.',
            'payment_method.in' => 'El método de pago seleccionado no es válido.',
            'bank_account_id.required' => 'La cuenta bancaria es obligatoria para transferencias.',
            'bank_account_id.exists' => 'La cuenta bancaria seleccionada no es válida.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado seleccionado no es válido.',
            'allocations.*.tax_document_id.required' => 'El documento es obligatorio en las asignaciones.',
            'allocations.*.tax_document_id.exists' => 'El documento seleccionado no es válido.',
            'allocations.*.amount.required' => 'El monto es obligatorio en las asignaciones.',
            'allocations.*.amount.min' => 'El monto de asignación debe ser mayor a 0.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->errors()->any()) {
                // Validate allocations sum doesn't exceed payment amount
                if ($this->allocations) {
                    $totalAllocations = collect($this->allocations)->sum('amount');
                    if ($totalAllocations > $this->amount) {
                        $validator->errors()->add('allocations', 'La suma de asignaciones no puede exceder el monto del pago.');
                    }
                }

                // Validate customer belongs to current tenant
                $customer = \App\Models\Customer::find($this->customer_id);
                if ($customer && $customer->tenant_id !== auth()->user()->tenant_id) {
                    $validator->errors()->add('customer_id', 'El cliente no pertenece a su empresa.');
                }

                // Validate bank account belongs to current tenant (if provided)
                if ($this->bank_account_id) {
                    $bankAccount = \App\Models\BankAccount::find($this->bank_account_id);
                    if ($bankAccount && $bankAccount->tenant_id !== auth()->user()->tenant_id) {
                        $validator->errors()->add('bank_account_id', 'La cuenta bancaria no pertenece a su empresa.');
                    }
                }

                // Validate tax documents belong to the same customer and tenant
                if ($this->allocations) {
                    foreach ($this->allocations as $index => $allocation) {
                        $document = \App\Models\TaxDocument::find($allocation['tax_document_id']);
                        if ($document) {
                            if ($document->tenant_id !== auth()->user()->tenant_id) {
                                $validator->errors()->add("allocations.{$index}.tax_document_id", 'El documento no pertenece a su empresa.');
                            }
                            if ($document->customer_id != $this->customer_id) {
                                $validator->errors()->add("allocations.{$index}.tax_document_id", 'El documento no pertenece al cliente seleccionado.');
                            }
                            if ($document->balance <= 0) {
                                $validator->errors()->add("allocations.{$index}.tax_document_id", 'El documento no tiene saldo pendiente.');
                            }
                            if ($allocation['amount'] > $document->balance) {
                                $validator->errors()->add("allocations.{$index}.amount", 'El monto excede el saldo del documento.');
                            }
                        }
                    }
                }
            }
        });
    }
}