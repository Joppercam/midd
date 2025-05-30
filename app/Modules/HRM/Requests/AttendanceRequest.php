<?php

namespace App\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [];

        switch ($this->route()->getName()) {
            case 'hrm.attendance.check-in':
            case 'hrm.attendance.check-out':
                $rules = [
                    'location' => 'nullable|string|max:255',
                    'latitude' => 'nullable|numeric|between:-90,90',
                    'longitude' => 'nullable|numeric|between:-180,180',
                    'notes' => 'nullable|string|max:500',
                ];
                break;

            case 'hrm.attendance.manual':
                $rules = [
                    'employee_id' => 'required|exists:employees,id',
                    'date' => 'required|date|before_or_equal:today',
                    'check_in' => 'required|date_format:H:i',
                    'check_out' => 'nullable|date_format:H:i|after:check_in',
                    'status' => 'required|in:present,late,absent,holiday,leave',
                    'notes' => 'nullable|string|max:500',
                ];
                break;

            case 'hrm.attendance.update':
                $rules = [
                    'check_in' => 'nullable|date_format:H:i',
                    'check_out' => 'nullable|date_format:H:i',
                    'status' => 'nullable|in:present,late,absent,holiday,leave',
                    'notes' => 'nullable|string|max:500',
                ];
                break;

            case 'hrm.attendance.approve':
                $rules = [
                    'approved' => 'required|boolean',
                    'comments' => 'nullable|string|max:500',
                ];
                break;

            case 'hrm.attendance.import':
                $rules = [
                    'file' => 'required|file|mimes:csv,xlsx|max:10240',
                ];
                break;

            case 'hrm.attendance.report':
            case 'hrm.attendance.team-report':
            case 'hrm.attendance.overtime':
            case 'hrm.attendance.late':
                $rules = [
                    'month' => 'nullable|integer|between:1,12',
                    'year' => 'nullable|integer|min:2020|max:' . (date('Y') + 1),
                    'department_id' => 'nullable|exists:departments,id',
                    'employee_id' => 'nullable|exists:employees,id',
                ];
                break;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'El empleado es obligatorio',
            'employee_id.exists' => 'El empleado seleccionado no existe',
            'date.required' => 'La fecha es obligatoria',
            'date.before_or_equal' => 'La fecha no puede ser futura',
            'check_in.required' => 'La hora de entrada es obligatoria',
            'check_in.date_format' => 'El formato de hora de entrada debe ser HH:MM',
            'check_out.date_format' => 'El formato de hora de salida debe ser HH:MM',
            'check_out.after' => 'La hora de salida debe ser posterior a la hora de entrada',
            'status.required' => 'El estado es obligatorio',
            'status.in' => 'El estado seleccionado no es válido',
            'file.required' => 'El archivo es obligatorio',
            'file.mimes' => 'El archivo debe ser CSV o Excel',
            'file.max' => 'El archivo no debe superar los 10MB',
            'latitude.between' => 'La latitud debe estar entre -90 y 90',
            'longitude.between' => 'La longitud debe estar entre -180 y 180',
        ];
    }

    public function prepareForValidation(): void
    {
        // Si no se proporciona mes/año, usar el actual
        if (!$this->filled('month')) {
            $this->merge(['month' => now()->month]);
        }
        
        if (!$this->filled('year')) {
            $this->merge(['year' => now()->year]);
        }
    }
}