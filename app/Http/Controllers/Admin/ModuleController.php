<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemModule;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\SubscriptionPlan;
use App\Services\ModuleManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ModuleController extends Controller
{
    protected ModuleManager $moduleManager;

    public function __construct(ModuleManager $moduleManager)
    {
        $this->moduleManager = $moduleManager;
    }

    /**
     * Dashboard principal de módulos
     */
    public function index(Request $request)
    {
        $modules = SystemModule::with(['tenantModules' => function($query) {
            $query->where('is_enabled', true);
        }])
        ->withCount(['activeTenants'])
        ->orderBy('category')
        ->orderBy('sort_order')
        ->get();

        $stats = $this->getModuleStats();
        
        $tenants = [];
        $moduleRequests = [];

        if ($request->get('tab') === 'tenants') {
            $tenants = $this->getTenantsList($request->get('search'));
        }

        if ($request->get('tab') === 'requests') {
            $moduleRequests = $this->getModuleRequests();
        }

        return Inertia::render('Admin/Modules/Index', [
            'modules' => $modules,
            'stats' => $stats,
            'tenants' => $tenants,
            'moduleRequests' => $moduleRequests,
        ]);
    }

    /**
     * Obtener estadísticas generales
     */
    private function getModuleStats(): array
    {
        return [
            'active_modules' => SystemModule::where('is_active', true)->count(),
            'total_tenants' => Tenant::where('is_active', true)->count(),
            'monthly_revenue' => $this->calculateMonthlyRevenue(),
            'pending_requests' => DB::table('module_requests')
                ->where('status', 'pending')
                ->count(),
        ];
    }

    /**
     * Calcular ingresos mensuales
     */
    private function calculateMonthlyRevenue(): float
    {
        $planRevenue = DB::table('tenant_subscriptions')
            ->join('subscription_plans', 'tenant_subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('tenant_subscriptions.status', 'active')
            ->sum(DB::raw('
                CASE 
                    WHEN tenant_subscriptions.billing_cycle = "monthly" THEN subscription_plans.monthly_price
                    WHEN tenant_subscriptions.billing_cycle = "annual" THEN subscription_plans.annual_price / 12
                    ELSE 0
                END
            '));

        $moduleRevenue = DB::table('tenant_modules')
            ->join('system_modules', 'tenant_modules.module_id', '=', 'system_modules.id')
            ->where('tenant_modules.is_enabled', true)
            ->where(function($query) {
                $query->whereNull('tenant_modules.expires_at')
                    ->orWhere('tenant_modules.expires_at', '>', now());
            })
            ->sum(DB::raw('
                CASE 
                    WHEN tenant_modules.custom_price IS NOT NULL THEN tenant_modules.custom_price
                    ELSE system_modules.base_price
                END
            '));

        return $planRevenue + $moduleRevenue;
    }

    /**
     * Obtener lista de tenants
     */
    private function getTenantsList(?string $search): array
    {
        $query = Tenant::with(['subscription.plan', 'activeModules.systemModule'])
            ->withCount(['modules as active_modules_count' => function($q) {
                $q->where('is_enabled', true);
            }]);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%");
            });
        }

        return $query->limit(20)->get()->toArray();
    }

    /**
     * Obtener solicitudes de módulos
     */
    private function getModuleRequests(): array
    {
        return DB::table('module_requests')
            ->join('tenants', 'module_requests.tenant_id', '=', 'tenants.id')
            ->join('system_modules', 'module_requests.module_id', '=', 'system_modules.id')
            ->join('users', 'module_requests.requested_by', '=', 'users.id')
            ->select([
                'module_requests.*',
                'tenants.name as tenant_name',
                'system_modules.name as module_name',
                'users.name as requested_by_name'
            ])
            ->orderByDesc('module_requests.created_at')
            ->get()
            ->toArray();
    }

    /**
     * Mostrar detalles de un módulo
     */
    public function show(SystemModule $module)
    {
        $module->load(['tenantModules.tenant', 'activeTenants']);
        
        $stats = [
            'total_tenants' => $module->tenantModules()->count(),
            'active_tenants' => $module->activeTenants()->count(),
            'monthly_revenue' => $module->activeTenants()->sum(function($tenantModule) {
                return $tenantModule->custom_price ?? $tenantModule->systemModule->base_price;
            }),
            'usage_stats' => $this->getModuleUsageStats($module),
        ];

        return Inertia::render('Admin/Modules/Show', [
            'module' => $module,
            'stats' => $stats,
        ]);
    }

    /**
     * Obtener estadísticas de uso de un módulo
     */
    private function getModuleUsageStats(SystemModule $module): array
    {
        return DB::table('module_usage_logs')
            ->where('module_id', $module->id)
            ->where('logged_at', '>=', now()->subDays(30))
            ->selectRaw('
                COUNT(*) as total_actions,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT tenant_id) as active_tenants,
                action,
                DATE(logged_at) as date
            ')
            ->groupBy('action', 'date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Crear un nuevo módulo
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:system_modules',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'version' => 'required|string|max:20',
            'category' => 'required|string|in:core,finance,sales,operations,hr,analytics,integration,industry',
            'base_price' => 'required|numeric|min:0',
            'dependencies' => 'array',
            'features' => 'array',
            'permissions' => 'array',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
        ]);

        $module = SystemModule::create($request->all());

        return redirect()->route('admin.modules.index')
            ->with('success', 'Módulo creado exitosamente.');
    }

    /**
     * Actualizar un módulo
     */
    public function update(Request $request, SystemModule $module)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'version' => 'required|string|max:20',
            'category' => 'required|string|in:core,finance,sales,operations,hr,analytics,integration,industry',
            'base_price' => 'required|numeric|min:0',
            'dependencies' => 'array',
            'features' => 'array',
            'permissions' => 'array',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        $module->update($request->all());

        return redirect()->route('admin.modules.show', $module)
            ->with('success', 'Módulo actualizado exitosamente.');
    }

    /**
     * Obtener módulos de un tenant específico
     */
    public function getTenantModules(Tenant $tenant)
    {
        $availableModules = SystemModule::where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get();

        $tenantModules = $tenant->modules()->with('systemModule')->get();

        return response()->json([
            'available_modules' => $availableModules,
            'tenant_modules' => $tenantModules,
        ]);
    }

    /**
     * Actualizar módulos de un tenant
     */
    public function updateTenantModules(Request $request, Tenant $tenant)
    {
        $request->validate([
            'modules' => 'required|array',
            'modules.*.module_id' => 'required|exists:system_modules,id',
            'modules.*.is_enabled' => 'required|boolean',
            'modules.*.custom_price' => 'nullable|numeric|min:0',
            'modules.*.expires_at' => 'nullable|date',
        ]);

        DB::transaction(function() use ($request, $tenant) {
            foreach ($request->modules as $moduleData) {
                $systemModule = SystemModule::find($moduleData['module_id']);
                
                if ($moduleData['is_enabled']) {
                    $this->moduleManager->enableModule($tenant, $systemModule, [
                        'custom_price' => $moduleData['custom_price'] ?? null,
                        'expires_at' => $moduleData['expires_at'] ?? null,
                    ]);
                } else {
                    // Solo deshabilitar si no es core
                    if (!$systemModule->is_core) {
                        $this->moduleManager->disableModule($tenant, $systemModule);
                    }
                }
            }
        });

        return redirect()->back()->with('success', 'Módulos actualizados exitosamente.');
    }

    /**
     * Aprobar solicitud de módulo
     */
    public function approveModuleRequest(Request $request, $requestId)
    {
        $moduleRequest = DB::table('module_requests')->find($requestId);
        
        if (!$moduleRequest || $moduleRequest->status !== 'pending') {
            return redirect()->back()->with('error', 'Solicitud no encontrada o ya procesada.');
        }

        DB::transaction(function() use ($moduleRequest, $request) {
            // Habilitar el módulo
            $tenant = Tenant::find($moduleRequest->tenant_id);
            $module = SystemModule::find($moduleRequest->module_id);
            
            $this->moduleManager->enableModule($tenant, $module, [
                'expires_at' => now()->addDays(30), // 30 días de prueba
            ]);

            // Actualizar solicitud
            DB::table('module_requests')
                ->where('id', $moduleRequest->id)
                ->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                    'admin_notes' => $request->notes,
                    'updated_at' => now(),
                ]);
        });

        return redirect()->back()->with('success', 'Solicitud aprobada y módulo habilitado.');
    }

    /**
     * Rechazar solicitud de módulo
     */
    public function rejectModuleRequest(Request $request, $requestId)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::table('module_requests')
            ->where('id', $requestId)
            ->update([
                'status' => 'rejected',
                'admin_notes' => $request->reason,
                'updated_at' => now(),
            ]);

        return redirect()->back()->with('success', 'Solicitud rechazada.');
    }

    /**
     * Obtener recomendaciones de módulos para un tenant
     */
    public function getRecommendations(Tenant $tenant)
    {
        $recommendations = $this->moduleManager->getRecommendedModules($tenant);
        
        return response()->json([
            'recommendations' => $recommendations,
            'tenant' => $tenant->only(['id', 'name', 'business_type', 'business_size']),
        ]);
    }

    /**
     * Obtener estadísticas de uso por módulo
     */
    public function getUsageStats(Request $request)
    {
        $period = $request->get('period', 'month');
        
        $stats = DB::table('module_usage_logs')
            ->join('system_modules', 'module_usage_logs.module_id', '=', 'system_modules.id')
            ->join('tenants', 'module_usage_logs.tenant_id', '=', 'tenants.id')
            ->where('module_usage_logs.logged_at', '>=', $this->getPeriodStart($period))
            ->selectRaw('
                system_modules.name as module_name,
                system_modules.code as module_code,
                COUNT(*) as total_actions,
                COUNT(DISTINCT module_usage_logs.user_id) as unique_users,
                COUNT(DISTINCT module_usage_logs.tenant_id) as active_tenants
            ')
            ->groupBy('system_modules.id', 'system_modules.name', 'system_modules.code')
            ->orderByDesc('total_actions')
            ->get();

        return response()->json($stats);
    }

    /**
     * Obtener fecha de inicio según período
     */
    private function getPeriodStart(string $period): \DateTime
    {
        return match($period) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'quarter' => now()->subQuarter(),
            'year' => now()->subYear(),
            default => now()->subMonth(),
        };
    }
}