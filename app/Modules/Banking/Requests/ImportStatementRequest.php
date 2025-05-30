<?php

namespace App\Modules\Banking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'file' => 'required|file|mimes:csv,txt,xls,xlsx|max:10240', // 10MB max
            'date_format' => 'nullable|string|in:d/m/Y,d-m-Y,Y-m-d,m/d/Y',
            'skip_duplicates' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'bank_account_id.required' => 'Debe seleccionar una cuenta bancaria.',
            'bank_account_id.exists' => 'La cuenta bancaria seleccionada no es válida.',
            'file.required' => 'Debe seleccionar un archivo para importar.',
            'file.mimes' => 'El archivo debe ser de tipo: CSV, TXT, XLS o XLSX.',
            'file.max' => 'El archivo no puede ser mayor a 10MB.',
            'date_format.in' => 'El formato de fecha no es válido.',
        ];
    }
}