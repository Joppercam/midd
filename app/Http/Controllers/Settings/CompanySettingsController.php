<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CompanySettingsController extends Controller
{
    public function index()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('dashboard')
                ->with('error', 'No se encontró la información de la empresa.');
        }

        // Solo usuarios con rol admin pueden acceder
        if (!auth()->user()->hasRole(['super-admin', 'admin'])) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return Inertia::render('Settings/CompanySettings', [
            'tenant' => $tenant,
            'industries' => $this->getIndustries(),
            'taxRegimes' => $this->getTaxRegimes(),
            'economicActivities' => $this->getEconomicActivities(),
            'timezones' => $this->getTimezones(),
            'plans' => $this->getAvailablePlans(),
            'usage' => $tenant->usage_stats,
            'limits' => $tenant->plan_limits,
        ]);
    }

    public function updateBasicInfo(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!auth()->user()->hasRole(['super-admin', 'admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'tax_id' => 'required|string|max:20',
            'industry' => 'nullable|string|max:100',
            'website' => 'nullable|url|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Información básica actualizada correctamente.');
    }

    public function updateAddress(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!auth()->user()->hasRole(['super-admin', 'admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:100',
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Dirección actualizada correctamente.');
    }

    public function updateFiscalInfo(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!auth()->user()->hasRole(['super-admin', 'admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'tax_regime' => 'required|string|max:100',
            'economic_activity' => 'required|string|max:255',
            'economic_activity_code' => 'required|string|max:20',
            'is_holding' => 'boolean',
            'uses_branch_offices' => 'boolean',
            'branch_code' => 'nullable|string|max:20',
            'fiscal_year_start_month' => 'required|integer|min:1|max:12',
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Información fiscal actualizada correctamente.');
    }

    public function updateLogo(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!auth()->user()->hasRole(['super-admin', 'admin'])) {
            abort(403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);

        // Eliminar logo anterior si existe
        if ($tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
        }

        // Guardar nuevo logo
        $path = $request->file('logo')->store('logos/' . $tenant->id, 'public');
        
        $tenant->update(['logo_path' => $path]);

        return back()->with('success', 'Logo actualizado correctamente.');
    }

    public function removeLogo()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!auth()->user()->hasRole(['super-admin', 'admin'])) {
            abort(403);
        }

        if ($tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
            $tenant->update(['logo_path' => null]);
        }

        return back()->with('success', 'Logo eliminado correctamente.');
    }

    public function updateBranding(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!auth()->user()->hasRole(['super-admin', 'admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Colores actualizados correctamente.');
    }

    public function updateInvoiceSettings(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!auth()->user()->hasRole(['super-admin', 'admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'invoice_settings' => 'required|array',
            'invoice_settings.show_logo' => 'boolean',
            'invoice_settings.show_payment_instructions' => 'boolean',
            'invoice_settings.payment_instructions' => 'nullable|string|max:1000',
            'invoice_settings.footer_text' => 'nullable|string|max:500',
            'invoice_settings.terms_and_conditions' => 'nullable|string|max:2000',
            'invoice_settings.default_due_days' => 'required|integer|min:0|max:365',
            'invoice_settings.invoice_prefix' => 'nullable|string|max:10',
            'invoice_settings.credit_note_prefix' => 'nullable|string|max:10',
            'invoice_settings.debit_note_prefix' => 'nullable|string|max:10',
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Configuración de facturas actualizada correctamente.');
    }

    public function updateEmailSettings(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!auth()->user()->hasRole(['super-admin', 'admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'email_settings' => 'required|array',
            'email_settings.from_name' => 'required|string|max:100',
            'email_settings.from_email' => 'required|email|max:100',
            'email_settings.reply_to_email' => 'nullable|email|max:100',
            'email_settings.send_copy_to' => 'nullable|email|max:100',
            'email_settings.invoice_subject' => 'required|string|max:200',
            'email_settings.invoice_message' => 'required|string|max:1000',
            'email_settings.reminder_subject' => 'required|string|max:200',
            'email_settings.reminder_message' => 'required|string|max:1000',
            'email_settings.auto_send_invoice' => 'boolean',
            'email_settings.auto_send_reminder' => 'boolean',
            'email_settings.reminder_days' => 'nullable|array',
            'email_settings.reminder_days.*' => 'integer|min:1|max:90',
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Configuración de emails actualizada correctamente.');
    }

    public function updateRegionalSettings(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!auth()->user()->hasRole(['super-admin', 'admin'])) {
            abort(403);
        }

        $validated = $request->validate([
            'currency' => 'required|string|size:3',
            'timezone' => 'required|string|timezone',
            'date_format' => 'required|string|in:d/m/Y,d-m-Y,Y-m-d,m/d/Y',
            'time_format' => 'required|string|in:H:i,h:i A',
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Configuración regional actualizada correctamente.');
    }

    private function getIndustries()
    {
        return [
            'retail' => 'Comercio minorista',
            'wholesale' => 'Comercio mayorista',
            'manufacturing' => 'Manufactura',
            'services' => 'Servicios',
            'construction' => 'Construcción',
            'technology' => 'Tecnología',
            'healthcare' => 'Salud',
            'education' => 'Educación',
            'hospitality' => 'Hotelería y turismo',
            'transportation' => 'Transporte',
            'agriculture' => 'Agricultura',
            'mining' => 'Minería',
            'real_estate' => 'Inmobiliaria',
            'finance' => 'Finanzas',
            'other' => 'Otro',
        ];
    }

    private function getTaxRegimes()
    {
        return [
            'primera_categoria' => 'Primera Categoría',
            'pyme' => 'Régimen Pro Pyme',
            'renta_presunta' => 'Renta Presunta',
            'transparente' => 'Régimen Transparente',
            'semi_integrado' => 'Régimen Semi Integrado',
        ];
    }

    private function getEconomicActivities()
    {
        // Lista simplificada, en producción vendría de una tabla
        return [
            '011100' => 'Cultivo de cereales',
            '461000' => 'Venta al por mayor a cambio de una retribución o por contrata',
            '471100' => 'Venta al por menor en comercios no especializados con predominio en productos alimenticios',
            '620100' => 'Actividades de programación informática',
            '681010' => 'Compra, venta y alquiler de bienes inmuebles propios o arrendados',
            '702000' => 'Actividades de consultoría de gestión',
            // ... más actividades
        ];
    }

    private function getTimezones()
    {
        return [
            'America/Santiago' => 'Santiago de Chile (CLT/CLST)',
            'Pacific/Easter' => 'Isla de Pascua (EAST/EASST)',
            'America/Punta_Arenas' => 'Magallanes (CLT)',
        ];
    }

    private function getAvailablePlans()
    {
        return [
            'basic' => [
                'name' => 'Plan Básico',
                'max_users' => 5,
                'max_documents_per_month' => 100,
                'max_products' => 500,
                'max_customers' => 500,
                'features' => ['invoicing', 'inventory', 'reports'],
            ],
            'professional' => [
                'name' => 'Plan Profesional',
                'max_users' => 15,
                'max_documents_per_month' => 500,
                'max_products' => 2000,
                'max_customers' => 2000,
                'features' => ['invoicing', 'inventory', 'reports', 'api_access', 'bank_reconciliation'],
            ],
            'enterprise' => [
                'name' => 'Plan Empresa',
                'max_users' => -1, // Ilimitado
                'max_documents_per_month' => -1,
                'max_products' => -1,
                'max_customers' => -1,
                'features' => ['all'],
            ],
        ];
    }
}