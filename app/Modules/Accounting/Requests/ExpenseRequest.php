<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $documentTypes = array_keys(config('accounting.expense_document_types', []));
        $categories = array_keys(config('accounting.expense_categories', []));
        
        return [
            'supplier_id' => 'nullable|exists:suppliers,id',
            'document_type' => 'required|in:' . implode(',', $documentTypes),
            'supplier_document_number' => 'nullable|string|max:255',
            'date' => 'required|date|before_or_equal:today',
            'due_date' => 'nullable|date|after_or_equal:date',
            'net_amount' => 'required|numeric|min:0.01|max:' . config('accounting.validation.max_expense_amount'),
            'tax_amount' => 'nullable|numeric|min:0',
            'other_taxes' => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,bank_transfer,check,credit_card,debit_card,electronic,credit_account,other',
            'status' => 'required|in:draft,pending,paid,cancelled',
            'category' => 'nullable|in:' . implode(',', $categories),
            'subcategory' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:' . config('accounting.validation.max_description_length'),
            'reference' => 'nullable|string|max:255',
            'account_code' => 'nullable|string|max:20',
            'project_id' => 'nullable|exists:projects,id',
            'cost_center' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.exists' => 'El proveedor seleccionado no es válido.',
            'document_type.required' => 'El tipo de documento es obligatorio.',
            'document_type.in' => 'El tipo de documento seleccionado no es válido.',
            'date.required' => 'La fecha es obligatoria.',
            'date.before_or_equal' => 'La fecha no puede ser futura.',
            'due_date.after_or_equal' => 'La fecha de vencimiento debe ser posterior o igual a la fecha del documento.',
            'net_amount.required' => 'El monto neto es obligatorio.',
            'net_amount.min' => 'El monto neto debe ser mayor a 0.',
            'net_amount.max' => 'El monto neto excede el límite permitido.',
            'tax_amount.min' => 'El monto del impuesto no puede ser negativo.',
            'other_taxes.min' => 'Otros impuestos no pueden ser negativos.',
            'total_amount.min' => 'El monto total debe ser mayor a 0.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado seleccionado no es válido.',
            'category.in' => 'La categoría seleccionada no es válida.',
            'description.max' => 'La descripción es demasiado larga.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->errors()->any()) {
                // Validate supplier belongs to current tenant
                if ($this->supplier_id) {
                    $supplier = \App\Models\Supplier::find($this->supplier_id);
                    if ($supplier && $supplier->tenant_id !== auth()->user()->tenant_id) {
                        $validator->errors()->add('supplier_id', 'El proveedor no pertenece a su empresa.');
                    }
                }

                // Validate total amount calculation
                $netAmount = $this->net_amount ?? 0;
                $taxAmount = $this->tax_amount ?? 0;
                $otherTaxes = $this->other_taxes ?? 0;
                $calculatedTotal = $netAmount + $taxAmount + $otherTaxes;
                
                if ($this->total_amount && abs($this->total_amount - $calculatedTotal) > 0.01) {
                    $validator->errors()->add('total_amount', 'El monto total no coincide con la suma de los componentes.');
                }

                // Validate tax amount for document types that require it
                $documentTypeConfig = config('accounting.expense_document_types.' . $this->document_type);
                if ($documentTypeConfig && $documentTypeConfig['affects_tax'] && !$this->tax_amount) {
                    // Only validate if net amount suggests tax should be present
                    if ($netAmount > 0 && $taxAmount == 0) {
                        // This is a warning, not an error - user might have a reason
                    }
                }

                // Validate folio requirement
                if ($documentTypeConfig && $documentTypeConfig['requires_folio'] && !$this->supplier_document_number) {
                    $validator->errors()->add('supplier_document_number', 'Este tipo de documento requiere número de folio.');
                }

                // Validate project belongs to tenant if specified
                if ($this->project_id) {
                    $project = \App\Models\Project::find($this->project_id);
                    if ($project && $project->tenant_id !== auth()->user()->tenant_id) {
                        $validator->errors()->add('project_id', 'El proyecto no pertenece a su empresa.');
                    }
                }

                // Validate account code exists in chart of accounts
                if ($this->account_code) {
                    $account = \App\Models\ChartOfAccount::where('tenant_id', auth()->user()->tenant_id)
                        ->where('code', $this->account_code)
                        ->where('is_active', true)
                        ->first();
                    
                    if (!$account) {
                        $validator->errors()->add('account_code', 'El código de cuenta no existe o no está activo.');
                    } elseif ($account->type !== 'expense') {
                        $validator->errors()->add('account_code', 'El código de cuenta debe ser de tipo gasto.');
                    }
                }

                // Business rule validations
                if ($this->status === 'paid' && (!$this->due_date || $this->due_date > now())) {
                    // Paid expenses should have a due date in the past or today
                    $validator->errors()->add('status', 'Un gasto marcado como pagado debería tener fecha de vencimiento.');
                }
            }
        });
    }

    protected function prepareForValidation()
    {
        // Auto-calculate total if not provided
        if (!$this->total_amount) {
            $netAmount = $this->net_amount ?? 0;
            $taxAmount = $this->tax_amount ?? 0;
            $otherTaxes = $this->other_taxes ?? 0;
            
            $this->merge([
                'total_amount' => $netAmount + $taxAmount + $otherTaxes
            ]);
        }

        // Auto-calculate tax if IVA should be applied and not provided
        if (!$this->tax_amount && $this->net_amount) {
            $documentTypeConfig = config('accounting.expense_document_types.' . $this->document_type);
            if ($documentTypeConfig && $documentTypeConfig['affects_tax']) {
                $ivaRate = config('accounting.taxes.iva_rate', 19) / 100;
                $this->merge([
                    'tax_amount' => round($this->net_amount * $ivaRate, 2)
                ]);
            }
        }
    }
}