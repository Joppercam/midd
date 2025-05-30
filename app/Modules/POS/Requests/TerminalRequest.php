<?php

namespace App\Modules\POS\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:100',
            'location' => 'required|string|max:100',
            'ip_address' => 'nullable|ip',
            'mac_address' => 'nullable|string|max:17|regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
            'receipt_printer' => 'nullable|string|max:100',
            'kitchen_printer' => 'nullable|string|max:100',
            'fiscal_printer' => 'nullable|string|max:100',
            'cash_drawer_port' => 'nullable|string|max:20',
            'barcode_scanner' => 'nullable|string|max:100',
            'customer_display' => 'nullable|string|max:100',
            'status' => 'required|in:active,inactive,maintenance',
            'settings' => 'nullable|array',
            'notes' => 'nullable|string|max:500',
        ];

        // Para actualización, hacer el nombre único excepto para el terminal actual
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'required|string|max:100|unique:terminals,name,' . $this->route('terminal') . ',id,tenant_id,' . auth()->user()->tenant_id;
        } else {
            $rules['name'] = 'required|string|max:100|unique:terminals,name,NULL,id,tenant_id,' . auth()->user()->tenant_id;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del terminal es obligatorio',
            'name.max' => 'El nombre no puede exceder 100 caracteres',
            'name.unique' => 'Ya existe un terminal con este nombre',
            
            'location.required' => 'La ubicación es obligatoria',
            'location.max' => 'La ubicación no puede exceder 100 caracteres',
            
            'ip_address.ip' => 'La dirección IP no es válida',
            'mac_address.regex' => 'La dirección MAC no tiene el formato correcto (XX:XX:XX:XX:XX:XX)',
            'mac_address.max' => 'La dirección MAC no puede exceder 17 caracteres',
            
            'receipt_printer.max' => 'El nombre de la impresora de recibos no puede exceder 100 caracteres',
            'kitchen_printer.max' => 'El nombre de la impresora de cocina no puede exceder 100 caracteres',
            'fiscal_printer.max' => 'El nombre de la impresora fiscal no puede exceder 100 caracteres',
            'cash_drawer_port.max' => 'El puerto del cajón no puede exceder 20 caracteres',
            'barcode_scanner.max' => 'El escáner de códigos no puede exceder 100 caracteres',
            'customer_display.max' => 'La pantalla del cliente no puede exceder 100 caracteres',
            
            'status.required' => 'El estado es obligatorio',
            'status.in' => 'El estado debe ser: activo, inactivo o mantenimiento',
            
            'settings.array' => 'Las configuraciones deben ser un objeto válido',
            'notes.max' => 'Las notas no pueden exceder 500 caracteres',
        ];
    }

    public function prepareForValidation(): void
    {
        // Normalizar la dirección MAC
        if ($this->has('mac_address')) {
            $mac = $this->get('mac_address');
            if ($mac) {
                // Convertir a formato estándar XX:XX:XX:XX:XX:XX
                $mac = strtoupper(str_replace('-', ':', $mac));
                $this->merge(['mac_address' => $mac]);
            }
        }

        // Establecer configuraciones por defecto
        if (!$this->has('settings')) {
            $this->merge(['settings' => $this->getDefaultSettings()]);
        } else {
            // Fusionar con configuraciones por defecto
            $defaultSettings = $this->getDefaultSettings();
            $userSettings = $this->get('settings', []);
            $mergedSettings = array_merge($defaultSettings, $userSettings);
            $this->merge(['settings' => $mergedSettings]);
        }

        // Establecer status por defecto si es creación
        if (!$this->has('status') && $this->isMethod('POST')) {
            $this->merge(['status' => 'active']);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar configuraciones específicas
            $this->validateSettings($validator);
            
            // Validar hardware específico
            $this->validateHardware($validator);
            
            // Validar límites del tenant
            $this->validateTenantLimits($validator);
        });
    }

    private function validateSettings($validator): void
    {
        $settings = $this->get('settings', []);

        // Validar timeout
        if (isset($settings['session_timeout'])) {
            if (!is_numeric($settings['session_timeout']) || $settings['session_timeout'] < 300 || $settings['session_timeout'] > 86400) {
                $validator->errors()->add('settings.session_timeout', 'El timeout de sesión debe estar entre 5 minutos y 24 horas');
            }
        }

        // Validar configuración de impresora de recibos
        if (isset($settings['receipt_printer'])) {
            $printerSettings = $settings['receipt_printer'];
            
            if (isset($printerSettings['paper_width']) && !in_array($printerSettings['paper_width'], [58, 80, 112])) {
                $validator->errors()->add('settings.receipt_printer.paper_width', 'El ancho de papel debe ser 58mm, 80mm o 112mm');
            }
            
            if (isset($printerSettings['copies']) && (!is_numeric($printerSettings['copies']) || $printerSettings['copies'] < 1 || $printerSettings['copies'] > 5)) {
                $validator->errors()->add('settings.receipt_printer.copies', 'El número de copias debe estar entre 1 y 5');
            }
        }

        // Validar configuración de tema
        if (isset($settings['theme']) && !in_array($settings['theme'], ['light', 'dark', 'auto'])) {
            $validator->errors()->add('settings.theme', 'El tema debe ser: light, dark o auto');
        }

        // Validar configuración de sonido
        if (isset($settings['sound_volume']) && (!is_numeric($settings['sound_volume']) || $settings['sound_volume'] < 0 || $settings['sound_volume'] > 100)) {
            $validator->errors()->add('settings.sound_volume', 'El volumen debe estar entre 0 y 100');
        }
    }

    private function validateHardware($validator): void
    {
        // Validar que las impresoras configuradas existan en el sistema
        $availablePrinters = $this->getAvailablePrinters();
        
        if ($this->get('receipt_printer') && !in_array($this->get('receipt_printer'), $availablePrinters)) {
            $validator->errors()->add('receipt_printer', 'La impresora de recibos seleccionada no está disponible');
        }
        
        if ($this->get('kitchen_printer') && !in_array($this->get('kitchen_printer'), $availablePrinters)) {
            $validator->errors()->add('kitchen_printer', 'La impresora de cocina seleccionada no está disponible');
        }
        
        if ($this->get('fiscal_printer') && !in_array($this->get('fiscal_printer'), $availablePrinters)) {
            $validator->errors()->add('fiscal_printer', 'La impresora fiscal seleccionada no está disponible');
        }

        // Validar puerto del cajón
        $cashDrawerPort = $this->get('cash_drawer_port');
        if ($cashDrawerPort) {
            $validPorts = ['COM1', 'COM2', 'COM3', 'COM4', 'USB', 'NETWORK'];
            if (!in_array(strtoupper($cashDrawerPort), $validPorts)) {
                $validator->errors()->add('cash_drawer_port', 'Puerto de cajón no válido');
            }
        }
    }

    private function validateTenantLimits($validator): void
    {
        // Validar límite de terminales para el tenant
        if ($this->isMethod('POST')) {
            $tenantId = auth()->user()->tenant_id;
            $currentTerminals = \App\Modules\POS\Models\Terminal::where('tenant_id', $tenantId)->count();
            $maxTerminals = config('pos.terminals.max_terminals', 10);
            
            if ($currentTerminals >= $maxTerminals) {
                $validator->errors()->add('name', "Has alcanzado el límite máximo de {$maxTerminals} terminales");
            }
        }

        // Validar IP única dentro del tenant
        $ipAddress = $this->get('ip_address');
        if ($ipAddress) {
            $tenantId = auth()->user()->tenant_id;
            $query = \App\Modules\POS\Models\Terminal::where('tenant_id', $tenantId)
                ->where('ip_address', $ipAddress);
            
            if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
                $query->where('id', '!=', $this->route('terminal'));
            }
            
            if ($query->exists()) {
                $validator->errors()->add('ip_address', 'Ya existe un terminal con esta dirección IP');
            }
        }
    }

    private function getDefaultSettings(): array
    {
        return [
            'session_timeout' => 3600, // 1 hora
            'auto_print_receipt' => true,
            'sound_enabled' => true,
            'sound_volume' => 50,
            'theme' => 'light',
            'language' => 'es',
            'currency' => 'CLP',
            'receipt_printer' => [
                'paper_width' => 80,
                'copies' => 1,
                'auto_cut' => true,
                'logo_enabled' => true,
            ],
            'interface' => [
                'show_product_images' => true,
                'product_grid_columns' => 4,
                'quick_access_items' => 12,
                'category_buttons' => true,
            ],
            'security' => [
                'require_pin_for_void' => true,
                'require_pin_for_discount' => true,
                'require_pin_for_refund' => true,
                'auto_logout_minutes' => 30,
            ],
        ];
    }

    private function getAvailablePrinters(): array
    {
        // TODO: Implementar detección real de impresoras
        // Por ahora retornamos una lista estática
        return [
            'EPSON TM-T20',
            'EPSON TM-T88V',
            'BIXOLON SRP-350',
            'STAR TSP143',
            'HP LaserJet',
            'Generic Thermal Printer',
        ];
    }
}