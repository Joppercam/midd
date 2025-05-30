<?php

namespace App\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        switch ($this->route()->getName()) {
            case 'hrm.payroll.store':
                $rules = [
                    'month' => 'required|integer|between:1,12',
                    'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
                    'payment_date' => 'required|date|after:today',
                    'payment_method' => 'required|in:transfer,check,cash',
                    'employees' => 'nullable|array',
                    'employees.*' => 'exists:employees,id',
                ];
                break;

            case 'hrm.payroll.update':
                $rules = [
                    'payment_date' => 'nullable|date',
                    'payment_method' => 'nullable|in:transfer,check,cash',
                    'notes' => 'nullable|string|max:1000',
                ];
                break;

            case 'hrm.payroll.calculate':
                $rules = [
                    'employees' => 'required|array|min:1',
                    'employees.*.id' => 'required|exists:employees,id',
                    'employees.*.overtime_hours' => 'nullable|numeric|min:0',
                    'employees.*.bonuses' => 'nullable|numeric|min:0',
                    'employees.*.deductions' => 'nullable|numeric|min:0',
                ];
                break;

            case 'hrm.payroll.approve':
                $rules = [
                    'comments' => 'nullable|string|max:500',
                ];
                break;

            case 'hrm.payroll.add-deduction':
                $rules = [
                    'employee_id' => 'required|exists:employees,id',
                    'type' => 'required|in:loan,advance,other',
                    'amount' => 'required|numeric|min:0',
                    'description' => 'required|string|max:255',
                    'installments' => 'nullable|integer|min:1|max:36',
                ];
                break;

            case 'hrm.payroll.add-bonus':
                $rules = [
                    'employee_id' => 'required|exists:employees,id',
                    'type' => 'required|in:performance,sales,special,other',
                    'amount' => 'required|numeric|min:0',
                    'description' => 'required|string|max:255',
                    'is_taxable' => 'required|boolean',
                ];
                break;

            case 'hrm.payroll.bank-file':
                $rules = [
                    'bank' => 'required|in:santander,chile,bci,scotiabank,estado',
                    'account_type' => 'required|in:checking,savings,vista',
                ];
                break;

            case 'hrm.payroll.report':
            case 'hrm.payroll.tax-report':
            case 'hrm.payroll.social-security':
                $rules = [
                    'month' => 'required|integer|between:1,12',
                    'year' => 'required|integer|min:2020|max:' . date('Y'),
                    'format' => 'nullable|in:pdf,excel,csv',
                ];
                break;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'month.required' => 'El mes es obligatorio',
            'month.between' => 'El mes debe estar entre 1 y 12',
            'year.required' => 'El año es obligatorio',
            'year.min' => 'El año debe ser 2020 o posterior',
            'year.max' => 'El año no puede ser mayor a ' . (date('Y') + 1),
            'payment_date.required' => 'La fecha de pago es obligatoria',
            'payment_date.after' => 'La fecha de pago debe ser futura',
            'payment_method.required' => 'El método de pago es obligatorio',
            'payment_method.in' => 'El método de pago seleccionado no es válido',
            'employees.required' => 'Debe seleccionar al menos un empleado',
            'employees.min' => 'Debe seleccionar al menos un empleado',
            'employee_id.required' => 'El empleado es obligatorio',
            'employee_id.exists' => 'El empleado seleccionado no existe',
            'type.required' => 'El tipo es obligatorio',
            'amount.required' => 'El monto es obligatorio',
            'amount.min' => 'El monto debe ser mayor o igual a 0',
            'description.required' => 'La descripción es obligatoria',
            'bank.required' => 'El banco es obligatorio',
            'account_type.required' => 'El tipo de cuenta es obligatorio',
        ];
    }

    public function prepareForValidation(): void
    {
        // Convertir checkboxes a boolean
        if ($this->has('is_taxable')) {
            $this->merge([
                'is_taxable' => filter_var($this->is_taxable, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}