<?php

namespace App\Modules\Banking\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class BankReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'period_start' => 'required|date|before_or_equal:period_end',
            'period_end' => 'required|date|after_or_equal:period_start',
            'bank_starting_balance' => 'required|numeric',
            'bank_ending_balance' => 'required|numeric',
        ];
    }

    public function messages(): array
    {
        return [
            'bank_account_id.required' => 'Debe seleccionar una cuenta bancaria.',
            'bank_account_id.exists' => 'La cuenta bancaria seleccionada no es válida.',
            'period_start.required' => 'La fecha de inicio es obligatoria.',
            'period_start.before_or_equal' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.',
            'period_end.required' => 'La fecha de fin es obligatoria.',
            'period_end.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'bank_starting_balance.required' => 'El saldo inicial del banco es obligatorio.',
            'bank_ending_balance.required' => 'El saldo final del banco es obligatorio.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$validator->errors()->any()) {
                // Check if period is not too long
                $start = Carbon::parse($this->period_start);
                $end = Carbon::parse($this->period_end);
                
                if ($start->diffInDays($end) > 90) {
                    $validator->errors()->add('period_end', 'El período de conciliación no puede ser mayor a 90 días.');
                }

                // Check if there's no pending reconciliation for this account
                $account = \App\Models\BankAccount::find($this->bank_account_id);
                if ($account) {
                    $pendingReconciliation = $account->reconciliations()
                        ->where('status', 'pending')
                        ->exists();
                    
                    if ($pendingReconciliation) {
                        $validator->errors()->add('bank_account_id', 'Esta cuenta tiene una conciliación pendiente. Debe completarla o cancelarla antes de iniciar una nueva.');
                    }
                }
            }
        });
    }
}