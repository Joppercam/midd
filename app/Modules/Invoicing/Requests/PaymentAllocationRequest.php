<?php

namespace App\Modules\Invoicing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allocations' => 'required|array|min:1',
            'allocations.*.tax_document_id' => 'required|exists:tax_documents,id',
            'allocations.*.amount' => 'required|numeric|min:0.01',
            'allocations.*.notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'allocations.required' => 'Debe proporcionar al menos una asignación.',
            'allocations.min' => 'Debe proporcionar al menos una asignación.',
            'allocations.*.tax_document_id.required' => 'El documento es obligatorio.',
            'allocations.*.tax_document_id.exists' => 'El documento seleccionado no es válido.',
            'allocations.*.amount.required' => 'El monto de asignación es obligatorio.',
            'allocations.*.amount.min' => 'El monto de asignación debe ser mayor a 0.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->errors()->any()) {
                $payment = $this->route('payment');
                $tenantId = auth()->user()->tenant_id;
                
                // Validate total allocations don't exceed remaining payment amount
                $totalAllocations = collect($this->allocations)->sum('amount');
                if ($totalAllocations > $payment->remaining_amount) {
                    $validator->errors()->add('allocations', 'La suma de asignaciones excede el monto disponible del pago.');
                }

                // Validate each allocation
                foreach ($this->allocations as $index => $allocation) {
                    $document = \App\Models\TaxDocument::find($allocation['tax_document_id']);
                    
                    if ($document) {
                        // Check document belongs to same tenant
                        if ($document->tenant_id !== $tenantId) {
                            $validator->errors()->add("allocations.{$index}.tax_document_id", 'El documento no pertenece a su empresa.');
                            continue;
                        }

                        // Check document belongs to same customer as payment
                        if ($document->customer_id !== $payment->customer_id) {
                            $validator->errors()->add("allocations.{$index}.tax_document_id", 'El documento no pertenece al cliente del pago.');
                            continue;
                        }

                        // Check document has balance
                        if ($document->balance <= 0) {
                            $validator->errors()->add("allocations.{$index}.tax_document_id", 'El documento no tiene saldo pendiente.');
                            continue;
                        }

                        // Check allocation amount doesn't exceed document balance
                        if ($allocation['amount'] > $document->balance) {
                            $validator->errors()->add("allocations.{$index}.amount", 'El monto excede el saldo del documento.');
                            continue;
                        }

                        // Check if allocation already exists for this document
                        $existingAllocation = \App\Models\PaymentAllocation::where('payment_id', $payment->id)
                            ->where('tax_document_id', $allocation['tax_document_id'])
                            ->exists();

                        if ($existingAllocation) {
                            $validator->errors()->add("allocations.{$index}.tax_document_id", 'Ya existe una asignación para este documento.');
                        }
                    }
                }
            }
        });
    }
}