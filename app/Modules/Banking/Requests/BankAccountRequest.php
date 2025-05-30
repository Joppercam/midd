<?php

namespace App\Modules\Banking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'bank_name' => 'required|string|max:100',
            'account_type' => 'required|in:checking,savings,credit',
            'account_number' => 'required|string|max:50',
            'currency' => 'required|string|size:3',
            'initial_balance' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];

        // Make account number unique per tenant except for current account
        if ($this->method() === 'POST') {
            $rules['account_number'] .= '|unique:bank_accounts,account_number,NULL,id,tenant_id,' . auth()->user()->tenant_id;
        } else {
            $rules['account_number'] .= '|unique:bank_accounts,account_number,' . $this->route('account')->id . ',id,tenant_id,' . auth()->user()->tenant_id;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'bank_name.required' => 'El nombre del banco es obligatorio.',
            'account_type.required' => 'El tipo de cuenta es obligatorio.',
            'account_type.in' => 'El tipo de cuenta debe ser: corriente, ahorro o crédito.',
            'account_number.required' => 'El número de cuenta es obligatorio.',
            'account_number.unique' => 'Este número de cuenta ya está registrado.',
            'currency.required' => 'La moneda es obligatoria.',
            'currency.size' => 'La moneda debe ser un código de 3 letras (ej: CLP, USD).',
        ];
    }
}