<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SystemModule;
use App\Models\TenantModule;
use App\Models\Tenant;
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
     * Display module management dashboard
     */
    public function index()
    {
        $modules = SystemModule::withCount(['tenantModules as active_installations' => function ($query) {
            $query->where('is_active', true);
        }])->get();

        $stats = [
            'total_modules' => SystemModule::count(),
            'active_modules' => SystemModule::where('is_active', true)->count(),
            'total_installations' => TenantModule::where('is_active', true)->count(),
            'revenue_from_modules' => $this->calculateModuleRevenue(),
        ];

        $topModules = $this->getTopModulesByUsage();
        $recentInstallations = $this->getRecentInstallations();

        return Inertia::render('SuperAdmin/Modules/Index', [
            'modules' => $modules,
            'stats' => $stats,
            'topModules' => $topModules,
            'recentInstallations' => $recentInstallations,
        ]);
    }

    /**
     * Show detailed module information
     */
    public function show(SystemModule $module)
    {
        $module->load(['tenantModules.tenant']);
        
        $analytics = [
            'total_installations' => $module->tenantModules()->where('is_active', true)->count(),
            'revenue' => $this->calculateModuleRevenue($module),
            'adoption_rate' => $this->calculateAdoptionRate($module),
            'usage_trend' => $this->getModuleUsageTrend($module),
        ];

        $tenants = $module->tenantModules()
            ->with('tenant')
            ->where('is_active', true)
            ->orderBy('activated_at', 'desc')
            ->paginate(20);

        return Inertia::render('SuperAdmin/Modules/Show', [
            'module' => $module,
            'analytics' => $analytics,
            'tenants' => $tenants,
        ]);
    }

    /**
     * Create new system module
     */
    public function create()
    {
        return Inertia::render('SuperAdmin/Modules/Create');
    }

    /**
     * Store new system module
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:system_modules',
            'description' => 'required|string',
            'version' => 'required|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'is_core' => 'boolean',
            'dependencies' => 'array',
            'permissions' => 'array',
            'features' => 'array',
        ]);

        $module = SystemModule::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'version' => $request->version,
            'price' => $request->price,
            'is_active' => $request->boolean('is_active', true),
            'is_core' => $request->boolean('is_core', false),
            'dependencies' => $request->dependencies ?? [],
            'permissions' => $request->permissions ?? [],
            'features' => $request->features ?? [],
            'settings' => [],
        ]);

        return redirect()->route('super-admin.modules.show', $module)
            ->with('success', 'Module created successfully.');
    }

    /**
     * Edit system module
     */
    public function edit(SystemModule $module)
    {
        return Inertia::render('SuperAdmin/Modules/Edit', [
            'module' => $module,
        ]);
    }

    /**
     * Update system module
     */
    public function update(Request $request, SystemModule $module)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'version' => 'required|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'dependencies' => 'array',
            'permissions' => 'array',
            'features' => 'array',
        ]);

        $module->update([
            'name' => $request->name,
            'description' => $request->description,
            'version' => $request->version,
            'price' => $request->price,
            'is_active' => $request->boolean('is_active'),
            'dependencies' => $request->dependencies ?? [],
            'permissions' => $request->permissions ?? [],
            'features' => $request->features ?? [],
        ]);

        return redirect()->route('super-admin.modules.show', $module)
            ->with('success', 'Module updated successfully.');
    }

    /**
     * Toggle module status
     */
    public function toggleStatus(SystemModule $module)
    {
        $module->update(['is_active' => !$module->is_active]);

        $status = $module->is_active ? 'activated' : 'deactivated';
        
        return redirect()->back()
            ->with('success', "Module {$status} successfully.");
    }

    /**
     * Force install module for tenant
     */
    public function forceInstall(Request $request, SystemModule $module)
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        $tenant = Tenant::findOrFail($request->tenant_id);
        
        try {
            $this->moduleManager->enableModule($tenant, $module->code);
            
            return redirect()->back()
                ->with('success', "Module installed for {$tenant->name}.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => "Failed to install module: {$e->getMessage()}"]);
        }
    }

    /**
     * Force uninstall module for tenant
     */
    public function forceUninstall(Request $request, SystemModule $module)
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        $tenant = Tenant::findOrFail($request->tenant_id);
        
        try {
            $this->moduleManager->disableModule($tenant, $module->code);
            
            return redirect()->back()
                ->with('success', "Module uninstalled for {$tenant->name}.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => "Failed to uninstall module: {$e->getMessage()}"]);
        }
    }

    /**
     * Bulk module operations
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:activate,deactivate,delete',
            'module_ids' => 'required|array',
            'module_ids.*' => 'exists:system_modules,id',
        ]);

        $modules = SystemModule::whereIn('id', $request->module_ids)->get();

        switch ($request->action) {
            case 'activate':
                $modules->each(fn($module) => $module->update(['is_active' => true]));
                $message = 'Modules activated successfully.';
                break;
            case 'deactivate':
                $modules->each(fn($module) => $module->update(['is_active' => false]));
                $message = 'Modules deactivated successfully.';
                break;
            case 'delete':
                // Only allow deletion if no active installations
                $modulesWithInstallations = $modules->filter(function ($module) {
                    return $module->tenantModules()->where('is_active', true)->exists();
                });
                
                if ($modulesWithInstallations->count() > 0) {
                    return redirect()->back()
                        ->withErrors(['error' => 'Cannot delete modules with active installations.']);
                }
                
                $modules->each(fn($module) => $module->delete());
                $message = 'Modules deleted successfully.';
                break;
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Get module usage analytics
     */
    public function analytics()
    {
        $usage = DB::table('tenant_modules')
            ->join('system_modules', 'tenant_modules.system_module_id', '=', 'system_modules.id')
            ->where('tenant_modules.is_active', true)
            ->select('system_modules.name', 'system_modules.code', DB::raw('count(*) as installations'))
            ->groupBy('system_modules.id', 'system_modules.name', 'system_modules.code')
            ->orderByDesc('installations')
            ->get();

        $revenue = DB::table('tenant_modules')
            ->join('system_modules', 'tenant_modules.system_module_id', '=', 'system_modules.id')
            ->where('tenant_modules.is_active', true)
            ->select('system_modules.name', DB::raw('sum(system_modules.price) as revenue'))
            ->groupBy('system_modules.id', 'system_modules.name')
            ->orderByDesc('revenue')
            ->get();

        return Inertia::render('SuperAdmin/Modules/Analytics', [
            'usage' => $usage,
            'revenue' => $revenue,
        ]);
    }

    // Helper methods

    protected function calculateModuleRevenue(SystemModule $module = null): float
    {
        $query = DB::table('tenant_modules')
            ->join('system_modules', 'tenant_modules.system_module_id', '=', 'system_modules.id')
            ->where('tenant_modules.is_active', true);

        if ($module) {
            $query->where('system_modules.id', $module->id);
        }

        return $query->sum('system_modules.price');
    }

    protected function getTopModulesByUsage(int $limit = 10): array
    {
        return DB::table('tenant_modules')
            ->join('system_modules', 'tenant_modules.system_module_id', '=', 'system_modules.id')
            ->where('tenant_modules.is_active', true)
            ->select('system_modules.name', 'system_modules.code', DB::raw('count(*) as installations'))
            ->groupBy('system_modules.id', 'system_modules.name', 'system_modules.code')
            ->orderByDesc('installations')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    protected function getRecentInstallations(int $limit = 10): array
    {
        return TenantModule::with(['tenant:id,name', 'systemModule:id,name'])
            ->where('is_active', true)
            ->orderBy('activated_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    protected function calculateAdoptionRate(SystemModule $module): float
    {
        $totalTenants = Tenant::where('is_active', true)->count();
        $tenantsWithModule = $module->tenantModules()->where('is_active', true)->count();

        return $totalTenants > 0 ? round(($tenantsWithModule / $totalTenants) * 100, 2) : 0;
    }

    protected function getModuleUsageTrend(SystemModule $module): array
    {
        $data = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $installations = $module->tenantModules()
                ->where('is_active', true)
                ->whereYear('activated_at', $date->year)
                ->whereMonth('activated_at', $date->month)
                ->count();
            
            $data[] = [
                'month' => $date->format('M Y'),
                'installations' => $installations,
            ];
        }

        return $data;
    }
}