<?php

namespace App\Modules\HRM\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\ValidRut;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:employees,email',
            'phone' => 'nullable|string|max:20',
            'position' => 'required|string|max:100',
            'department_id' => 'nullable|exists:departments,id',
            'manager_id' => 'nullable|exists:employees,id',
            'hire_date' => 'required|date',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'nationality' => 'nullable|string|max:100',
            'identification_type' => 'nullable|in:rut,passport,other',
            'identification_number' => 'nullable|string|max:50',
            'base_salary' => 'required|numeric|min:0',
            'profile_photo' => 'nullable|image|max:2048',
            'emergency_contacts' => 'nullable|array',
            'emergency_contacts.*.name' => 'required_with:emergency_contacts|string|max:100',
            'emergency_contacts.*.relationship' => 'required_with:emergency_contacts|string|max:50',
            'emergency_contacts.*.phone' => 'required_with:emergency_contacts|string|max:20',
            'contract.type' => 'nullable|in:indefinite,fixed_term,per_project',
            'contract.start_date' => 'nullable|date',
            'contract.end_date' => 'nullable|date|after:contract.start_date',
            'contract.probation_end_date' => 'nullable|date|after:contract.start_date',
        ];

        // Validación RUT solo para Chile
        if ($this->input('identification_type') === 'rut') {
            $rules['rut'] = ['required', 'string', new ValidRut()];
        } else {
            $rules['rut'] = 'nullable|string';
        }

        // Para actualización, hacer el email único excepto para el empleado actual
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['email'] = 'required|email|unique:employees,email,' . $this->route('employee');
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es obligatorio',
            'last_name.required' => 'El apellido es obligatorio',
            'email.required' => 'El correo electrónico es obligatorio',
            'email.unique' => 'Este correo electrónico ya está registrado',
            'position.required' => 'El cargo es obligatorio',
            'hire_date.required' => 'La fecha de contratación es obligatoria',
            'base_salary.required' => 'El salario base es obligatorio',
            'base_salary.min' => 'El salario base debe ser mayor o igual a 0',
            'rut.required' => 'El RUT es obligatorio cuando el tipo de identificación es RUT',
            'profile_photo.image' => 'El archivo debe ser una imagen',
            'profile_photo.max' => 'La imagen no debe superar los 2MB',
        ];
    }
}