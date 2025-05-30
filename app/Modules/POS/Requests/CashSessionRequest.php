<?php

namespace App\Modules\POS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        switch ($this->route()->getName()) {
            case 'pos.cash-sessions.store':
                $rules = [
                    'terminal_id' => 'required|exists:terminals,id',
                    'opening_amount' => 'required|numeric|min:0',
                    'counted_denominations' => 'required|array',
                    'counted_denominations.bills' => 'required|array',
                    'counted_denominations.coins' => 'required|array',
                    'notes' => 'nullable|string|max:500',
                ];
                break;

            case 'pos.cash-sessions.close':
                $rules = [
                    'final_amount' => 'required|numeric|min:0',
                    'counted_denominations' => 'required|array',
                    'counted_denominations.bills' => 'required|array',
                    'counted_denominations.coins' => 'required|array',
                    'notes' => 'nullable|string|max:500',
                ];
                break;

            case 'pos.cash-sessions.add-movement':
                $rules = [
                    'type' => 'required|in:cash_in,cash_out,deposit,withdrawal',
                    'amount' => 'required|numeric|min:0.01',
                    'reason' => 'required|string|max:255',
                    'notes' => 'nullable|string|max:500',
                    'manager_pin' => 'required|string|min:4',
                    'reference' => 'nullable|string|max:100',
                ];
                break;

            case 'pos.cash-sessions.transfer':
                $rules = [
                    'from_session_id' => 'required|exists:cash_sessions,id',
                    'to_session_id' => 'required|exists:cash_sessions,id|different:from_session_id',
                    'amount' => 'required|numeric|min:0.01',
                    'reason' => 'required|string|max:255',
                    'manager_pin' => 'required|string|min:4',
                    'notes' => 'nullable|string|max:500',
                ];
                break;

            case 'pos.cash-sessions.count':
                $rules = [
                    'counted_amount' => 'required|numeric|min:0',
                    'denominations' => 'required|array',
                    'denominations.bills' => 'required|array',
                    'denominations.coins' => 'required|array',
                    'notes' => 'nullable|string|max:500',
                ];
                break;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'terminal_id.required' => 'El terminal es obligatorio',
            'terminal_id.exists' => 'El terminal seleccionado no existe',
            'opening_amount.required' => 'El monto de apertura es obligatorio',
            'opening_amount.min' => 'El monto de apertura debe ser mayor o igual a 0',
            'final_amount.required' => 'El monto final es obligatorio',
            'final_amount.min' => 'El monto final debe ser mayor o igual a 0',
            'counted_amount.required' => 'El monto contado es obligatorio',
            'counted_amount.min' => 'El monto contado debe ser mayor o igual a 0',
            
            'counted_denominations.required' => 'El detalle de denominaciones es obligatorio',
            'counted_denominations.bills.required' => 'El detalle de billetes es obligatorio',
            'counted_denominations.coins.required' => 'El detalle de monedas es obligatorio',
            'denominations.required' => 'El detalle de denominaciones es obligatorio',
            'denominations.bills.required' => 'El detalle de billetes es obligatorio',
            'denominations.coins.required' => 'El detalle de monedas es obligatorio',
            
            'type.required' => 'El tipo de movimiento es obligatorio',
            'type.in' => 'Tipo de movimiento no válido',
            'amount.required' => 'El monto es obligatorio',
            'amount.min' => 'El monto debe ser mayor a 0',
            'reason.required' => 'La razón es obligatoria',
            'reason.max' => 'La razón no puede exceder 255 caracteres',
            'manager_pin.required' => 'El PIN gerencial es obligatorio',
            'manager_pin.min' => 'El PIN debe tener al menos 4 caracteres',
            
            'from_session_id.required' => 'La sesión origen es obligatoria',
            'from_session_id.exists' => 'La sesión origen no existe',
            'to_session_id.required' => 'La sesión destino es obligatoria',
            'to_session_id.exists' => 'La sesión destino no existe',
            'to_session_id.different' => 'Las sesiones origen y destino deben ser diferentes',
            
            'notes.max' => 'Las notas no pueden exceder 500 caracteres',
            'reference.max' => 'La referencia no puede exceder 100 caracteres',
        ];
    }

    public function prepareForValidation(): void
    {
        // Calcular el total contado basado en denominaciones
        if ($this->has('counted_denominations')) {
            $total = 0;
            $denominations = $this->get('counted_denominations');
            
            // Sumar billetes
            if (isset($denominations['bills'])) {
                foreach ($denominations['bills'] as $value => $count) {
                    $total += $value * $count;
                }
            }
            
            // Sumar monedas
            if (isset($denominations['coins'])) {
                foreach ($denominations['coins'] as $value => $count) {
                    $total += $value * $count;
                }
            }
            
            // Solo establecer el total si no se proporcionó explícitamente
            if (!$this->has('final_amount') && !$this->has('counted_amount')) {
                if ($this->route()->getName() === 'pos.cash-sessions.close') {
                    $this->merge(['final_amount' => $total]);
                } elseif ($this->route()->getName() === 'pos.cash-sessions.count') {
                    $this->merge(['counted_amount' => $total]);
                }
            }
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validaciones específicas según el tipo de operación
            switch ($this->route()->getName()) {
                case 'pos.cash-sessions.store':
                    $this->validateSessionOpening($validator);
                    break;
                    
                case 'pos.cash-sessions.close':
                    $this->validateSessionClosing($validator);
                    break;
                    
                case 'pos.cash-sessions.transfer':
                    $this->validateTransfer($validator);
                    break;
                    
                case 'pos.cash-sessions.add-movement':
                    $this->validateMovement($validator);
                    break;
            }
        });
    }

    private function validateSessionOpening($validator): void
    {
        // Validar que el usuario no tenga otra sesión abierta
        $userId = auth()->id();
        $activeSessions = \App\Modules\POS\Models\CashSession::where('user_id', $userId)
            ->where('status', 'open')
            ->count();

        if ($activeSessions > 0) {
            $validator->errors()->add('terminal_id', 'Ya tienes una sesión de caja abierta');
        }

        // Validar que el terminal esté disponible
        $terminalId = $this->get('terminal_id');
        if ($terminalId) {
            $terminalSessions = \App\Modules\POS\Models\CashSession::where('terminal_id', $terminalId)
                ->where('status', 'open')
                ->count();

            if ($terminalSessions > 0) {
                $validator->errors()->add('terminal_id', 'Este terminal ya tiene una sesión activa');
            }
        }

        // Validar monto mínimo de apertura
        $openingAmount = $this->get('opening_amount', 0);
        $minAmount = config('pos.cash_registers.default_start_amount', 0);
        
        if ($openingAmount < $minAmount) {
            $validator->errors()->add('opening_amount', "El monto de apertura debe ser al menos $" . number_format($minAmount, 0, ',', '.'));
        }
    }

    private function validateSessionClosing($validator): void
    {
        // Validar que el monto contado coincida con las denominaciones
        $finalAmount = $this->get('final_amount', 0);
        $denominations = $this->get('counted_denominations', []);
        
        $calculatedTotal = 0;
        if (isset($denominations['bills'])) {
            foreach ($denominations['bills'] as $value => $count) {
                $calculatedTotal += $value * $count;
            }
        }
        if (isset($denominations['coins'])) {
            foreach ($denominations['coins'] as $value => $count) {
                $calculatedTotal += $value * $count;
            }
        }

        $difference = abs($finalAmount - $calculatedTotal);
        if ($difference > 1) { // Tolerancia de $1
            $validator->errors()->add('final_amount', 'El monto final no coincide con el total de denominaciones contadas');
        }
    }

    private function validateTransfer($validator): void
    {
        $fromSessionId = $this->get('from_session_id');
        $toSessionId = $this->get('to_session_id');
        $amount = $this->get('amount', 0);

        // Validar que ambas sesiones estén abiertas
        if ($fromSessionId) {
            $fromSession = \App\Modules\POS\Models\CashSession::find($fromSessionId);
            if (!$fromSession || $fromSession->status !== 'open') {
                $validator->errors()->add('from_session_id', 'La sesión origen debe estar abierta');
            } elseif ($fromSession->current_balance < $amount) {
                $validator->errors()->add('amount', 'La sesión origen no tiene suficiente efectivo');
            }
        }

        if ($toSessionId) {
            $toSession = \App\Modules\POS\Models\CashSession::find($toSessionId);
            if (!$toSession || $toSession->status !== 'open') {
                $validator->errors()->add('to_session_id', 'La sesión destino debe estar abierta');
            }
        }
    }

    private function validateMovement($validator): void
    {
        $type = $this->get('type');
        $amount = $this->get('amount', 0);

        // Para retiros, validar límites
        if (in_array($type, ['cash_out', 'withdrawal'])) {
            $maxAmount = config('pos.cash_registers.max_cash_amount', 5000000);
            if ($amount > $maxAmount) {
                $validator->errors()->add('amount', 'El monto excede el límite permitido');
            }
        }

        // Validar razones predefinidas
        $reason = $this->get('reason');
        $allowedReasons = [
            'cash_in' => ['Fondo inicial', 'Depósito bancario', 'Venta de contado', 'Otros ingresos'],
            'cash_out' => ['Gastos menores', 'Cambio para clientes', 'Depósito bancario', 'Otros egresos'],
            'deposit' => ['Depósito en banco', 'Depósito en bóveda'],
            'withdrawal' => ['Retiro para gastos', 'Retiro autorizado'],
        ];

        if (isset($allowedReasons[$type]) && !in_array($reason, $allowedReasons[$type])) {
            // Permitir razones personalizadas si está configurado
            if (!config('pos.security.allow_custom_reasons', true)) {
                $validator->errors()->add('reason', 'Razón no válida para este tipo de movimiento');
            }
        }
    }
}