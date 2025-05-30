<?php

namespace App\Modules\CRM\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\ValidRut;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('customer')?->id;
        
        return [
            'rut' => [
                'required',
                'string',
                'max:20',
                new ValidRut(),
                Rule::unique('customers', 'rut')
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->ignore($customerId)
            ],
            'name' => 'required|string|max:255',
            'type' => 'required|in:person,company',
            'category' => 'nullable|in:premium,standard,basic,prospect',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'commune' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'business_activity' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_position' => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
            'credit_limit' => 'nullable|numeric|min:0|max:999999999',
            'payment_term_days' => 'nullable|integer|min:0|max:365',
            'discount_rate' => 'nullable|numeric|min:0|max:100',
            'tax_id' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'language' => 'nullable|string|size:2',
            'timezone' => 'nullable|string|max:50',
            'communication_preference' => 'nullable|in:email,phone,whatsapp,in_person',
            'birth_date' => 'nullable|date|before:today',
            'anniversary_date' => 'nullable|date',
            'preferred_contact_time' => 'nullable|string|max:50',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'custom_fields' => 'nullable|array',
            'is_active' => 'boolean',
            'is_prospect' => 'boolean',
            'is_supplier' => 'boolean',
            'lead_source' => 'nullable|string|max:100',
            'lead_score' => 'nullable|integer|min:0|max:100',
            'assigned_user_id' => 'nullable|exists:users,id',
            'priority' => 'nullable|in:low,medium,high',
            'sales_rep_id' => 'nullable|exists:users,id',
            'account_manager_id' => 'nullable|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'rut.required' => 'El RUT es obligatorio.',
            'rut.unique' => 'El RUT ya existe en tu empresa.',
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no debe exceder 255 caracteres.',
            'type.required' => 'El tipo de cliente es obligatorio.',
            'type.in' => 'El tipo debe ser persona o empresa.',
            'category.in' => 'La categoría seleccionada no es válida.',
            'email.email' => 'El formato del email no es válido.',
            'email.max' => 'El email no debe exceder 255 caracteres.',
            'phone.max' => 'El teléfono no debe exceder 50 caracteres.',
            'mobile.max' => 'El móvil no debe exceder 50 caracteres.',
            'whatsapp.max' => 'El WhatsApp no debe exceder 50 caracteres.',
            'address.max' => 'La dirección no debe exceder 500 caracteres.',
            'commune.max' => 'La comuna no debe exceder 100 caracteres.',
            'city.max' => 'La ciudad no debe exceder 100 caracteres.',
            'region.max' => 'La región no debe exceder 100 caracteres.',
            'postal_code.max' => 'El código postal no debe exceder 20 caracteres.',
            'country.max' => 'El país no debe exceder 100 caracteres.',
            'business_activity.max' => 'La actividad empresarial no debe exceder 255 caracteres.',
            'industry.max' => 'La industria no debe exceder 100 caracteres.',
            'website.url' => 'El sitio web debe ser una URL válida.',
            'website.max' => 'El sitio web no debe exceder 255 caracteres.',
            'contact_name.max' => 'El nombre del contacto no debe exceder 255 caracteres.',
            'contact_position.max' => 'El cargo del contacto no debe exceder 100 caracteres.',
            'contact_email.email' => 'El formato del email del contacto no es válido.',
            'contact_email.max' => 'El email del contacto no debe exceder 255 caracteres.',
            'contact_phone.max' => 'El teléfono del contacto no debe exceder 50 caracteres.',
            'notes.max' => 'Las notas no deben exceder 2000 caracteres.',
            'credit_limit.numeric' => 'El límite de crédito debe ser un número.',
            'credit_limit.min' => 'El límite de crédito no puede ser negativo.',
            'credit_limit.max' => 'El límite de crédito es demasiado alto.',
            'payment_term_days.integer' => 'Los días de pago deben ser un número entero.',
            'payment_term_days.min' => 'Los días de pago no pueden ser negativos.',
            'payment_term_days.max' => 'Los días de pago no pueden exceder 365.',
            'discount_rate.numeric' => 'La tasa de descuento debe ser un número.',
            'discount_rate.min' => 'La tasa de descuento no puede ser negativa.',
            'discount_rate.max' => 'La tasa de descuento no puede exceder 100%.',
            'tax_id.max' => 'El ID fiscal no debe exceder 50 caracteres.',
            'currency.size' => 'La moneda debe tener exactamente 3 caracteres.',
            'language.size' => 'El idioma debe tener exactamente 2 caracteres.',
            'timezone.max' => 'La zona horaria no debe exceder 50 caracteres.',
            'communication_preference.in' => 'La preferencia de comunicación seleccionada no es válida.',
            'birth_date.date' => 'La fecha de nacimiento debe ser una fecha válida.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'anniversary_date.date' => 'La fecha de aniversario debe ser una fecha válida.',
            'preferred_contact_time.max' => 'El horario preferido no debe exceder 50 caracteres.',
            'tags.array' => 'Las etiquetas deben ser un array.',
            'tags.*.max' => 'Cada etiqueta no debe exceder 50 caracteres.',
            'custom_fields.array' => 'Los campos personalizados deben ser un array.',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso.',
            'is_prospect.boolean' => 'El estado de prospecto debe ser verdadero o falso.',
            'is_supplier.boolean' => 'El estado de proveedor debe ser verdadero o falso.',
            'lead_source.max' => 'La fuente del lead no debe exceder 100 caracteres.',
            'lead_score.integer' => 'La puntuación del lead debe ser un número entero.',
            'lead_score.min' => 'La puntuación del lead no puede ser negativa.',
            'lead_score.max' => 'La puntuación del lead no puede exceder 100.',
            'assigned_user_id.exists' => 'El usuario asignado no existe.',
            'priority.in' => 'La prioridad seleccionada no es válida.',
            'sales_rep_id.exists' => 'El representante de ventas no existe.',
            'account_manager_id.exists' => 'El gerente de cuenta no existe.',
        ];
    }

    public function attributes(): array
    {
        return [
            'rut' => 'RUT',
            'name' => 'nombre',
            'type' => 'tipo',
            'category' => 'categoría',
            'email' => 'email',
            'phone' => 'teléfono',
            'mobile' => 'móvil',
            'whatsapp' => 'WhatsApp',
            'address' => 'dirección',
            'commune' => 'comuna',
            'city' => 'ciudad',
            'region' => 'región',
            'postal_code' => 'código postal',
            'country' => 'país',
            'business_activity' => 'actividad empresarial',
            'industry' => 'industria',
            'website' => 'sitio web',
            'contact_name' => 'nombre del contacto',
            'contact_position' => 'cargo del contacto',
            'contact_email' => 'email del contacto',
            'contact_phone' => 'teléfono del contacto',
            'notes' => 'notas',
            'credit_limit' => 'límite de crédito',
            'payment_term_days' => 'días de pago',
            'discount_rate' => 'tasa de descuento',
            'tax_id' => 'ID fiscal',
            'currency' => 'moneda',
            'language' => 'idioma',
            'timezone' => 'zona horaria',
            'communication_preference' => 'preferencia de comunicación',
            'birth_date' => 'fecha de nacimiento',
            'anniversary_date' => 'fecha de aniversario',
            'preferred_contact_time' => 'horario preferido de contacto',
            'tags' => 'etiquetas',
            'custom_fields' => 'campos personalizados',
            'is_active' => 'activo',
            'is_prospect' => 'prospecto',
            'is_supplier' => 'proveedor',
            'lead_source' => 'fuente del lead',
            'lead_score' => 'puntuación del lead',
            'assigned_user_id' => 'usuario asignado',
            'priority' => 'prioridad',
            'sales_rep_id' => 'representante de ventas',
            'account_manager_id' => 'gerente de cuenta',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Limpiar y formatear campos antes de la validación
        if ($this->has('rut')) {
            $this->merge([
                'rut' => strtoupper(trim($this->rut))
            ]);
        }
        
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email))
            ]);
        }
        
        if ($this->has('contact_email')) {
            $this->merge([
                'contact_email' => strtolower(trim($this->contact_email))
            ]);
        }
        
        // Establecer valores por defecto
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
        
        if (!$this->has('currency')) {
            $this->merge(['currency' => 'CLP']);
        }
        
        if (!$this->has('language')) {
            $this->merge(['language' => 'es']);
        }
        
        if (!$this->has('country')) {
            $this->merge(['country' => 'Chile']);
        }
    }
}