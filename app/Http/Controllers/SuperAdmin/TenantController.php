<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SuperAdmin\TenantManagementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    protected $tenantService;

    public function __construct(TenantManagementService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    public function index(Request $request)
    {
        $query = Tenant::with(['subscription', 'activeModules'])
            ->withCount(['users', 'customers', 'products', 'taxDocuments']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':
                    $query->where('is_active', true)->where('subscription_status', 'active');
                    break;
                case 'trial':
                    $query->where('subscription_status', 'trial');
                    break;
                case 'suspended':
                    $query->where('is_active', false);
                    break;
            }
        }

        $tenants = $query->latest()->paginate(15);

        $stats = [
            'total' => Tenant::count(),
            'active' => Tenant::where('is_active', true)->where('subscription_status', 'active')->count(),
            'trial' => Tenant::where('subscription_status', 'trial')->count(),
            'suspended' => Tenant::where('is_active', false)->count(),
        ];

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'tenants' => $tenants,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Tenant $tenant)
    {
        $tenant->load(['users', 'subscription', 'activeModules.systemModule', 'usageStatistics' => function ($query) {
            $query->where('date', '>=', now()->subDays(30));
        }]);

        $usage = $this->tenantService->getTenantUsageDetails($tenant);
        $activities = $this->tenantService->getTenantActivities($tenant);
        $revenue = $this->tenantService->getTenantRevenue($tenant);

        return Inertia::render('SuperAdmin/Tenants/Show', [
            'tenant' => $tenant,
            'usage' => $usage,
            'activities' => $activities,
            'revenue' => $revenue,
        ]);
    }

    public function create()
    {
        $plans = \App\Models\SubscriptionPlan::where('is_active', true)->get();

        return Inertia::render('SuperAdmin/Tenants/Create', [
            'plans' => $plans,
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('Iniciando creación de tenant', ['request_data' => $request->all()]);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'required|string|max:255',
            'rut' => 'required|string|unique:tenants',
            'email' => 'required|email|unique:tenants',
            'phone' => 'nullable|string',
            'domain' => 'nullable|string|unique:tenants',
            'plan' => 'required|exists:subscription_plans,code',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ]);

        \Log::info('Validación exitosa', ['validated_data' => $validated]);

        DB::beginTransaction();
        try {
            \Log::info('Iniciando creación en servicio');
            $tenant = $this->tenantService->createTenant($validated);
            \Log::info('Tenant creado exitosamente', ['tenant_id' => $tenant->id]);
            
            // Create admin user for the tenant
            $adminUser = User::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
                'email_verified_at' => now(),
            ]);

            // Assign admin role
            $adminUser->assignRole('admin');

            auth()->guard('super_admin')->user()->logActivity(
                'tenant_created',
                "Created new tenant: {$tenant->name}",
                ['tenant_id' => $tenant->id]
            );

            DB::commit();

            return redirect()->route('super-admin.tenants.show', $tenant)
                ->with('success', 'Tenant created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create tenant: ' . $e->getMessage()]);
        }
    }

    public function edit(Tenant $tenant)
    {
        $plans = \App\Models\SubscriptionPlan::where('is_active', true)->get();

        return Inertia::render('SuperAdmin/Tenants/Edit', [
            'tenant' => $tenant->load('subscription'),
            'plans' => $plans,
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email,' . $tenant->id,
            'phone' => 'nullable|string',
            'domain' => 'nullable|string|unique:tenants,domain,' . $tenant->id,
            'max_users' => 'required|integer|min:1',
            'max_documents_per_month' => 'required|integer|min:1',
            'max_products' => 'required|integer|min:1',
            'max_customers' => 'required|integer|min:1',
            'api_access' => 'boolean',
            'multi_branch' => 'boolean',
        ]);

        $tenant->update($validated);

        auth()->guard('super_admin')->user()->logActivity(
            'tenant_updated',
            "Updated tenant: {$tenant->name}",
            ['tenant_id' => $tenant->id, 'changes' => $validated]
        );

        return redirect()->route('super-admin.tenants.show', $tenant)
            ->with('success', 'Tenant updated successfully');
    }

    public function suspend(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $tenant->suspend($validated['reason']);

        auth()->guard('super_admin')->user()->logActivity(
            'tenant_suspended',
            "Suspended tenant: {$tenant->name}",
            ['tenant_id' => $tenant->id, 'reason' => $validated['reason']]
        );

        return back()->with('success', 'Tenant suspended successfully');
    }

    public function reactivate(Tenant $tenant)
    {
        $tenant->reactivate();

        auth()->guard('super_admin')->user()->logActivity(
            'tenant_reactivated',
            "Reactivated tenant: {$tenant->name}",
            ['tenant_id' => $tenant->id]
        );

        return back()->with('success', 'Tenant reactivated successfully');
    }

    public function destroy(Tenant $tenant)
    {
        if ($tenant->users()->count() > 0) {
            return back()->withErrors(['error' => 'Cannot delete tenant with active users']);
        }

        $tenantName = $tenant->name;
        $tenant->delete();

        auth()->guard('super_admin')->user()->logActivity(
            'tenant_deleted',
            "Deleted tenant: {$tenantName}"
        );

        return redirect()->route('super-admin.tenants.index')
            ->with('success', 'Tenant deleted successfully');
    }

    public function impersonate(Tenant $tenant)
    {
        $adminUser = $tenant->users()->whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        if (!$adminUser) {
            return back()->withErrors(['error' => 'No admin user found for this tenant']);
        }

        session(['super_admin_id' => auth()->guard('super_admin')->id()]);
        session(['impersonating_tenant' => $tenant->id]);

        auth()->guard('super_admin')->user()->logActivity(
            'tenant_impersonated',
            "Started impersonating tenant: {$tenant->name}",
            ['tenant_id' => $tenant->id, 'user_id' => $adminUser->id]
        );

        auth()->logout();
        auth()->login($adminUser);

        return redirect()->route('dashboard')
            ->with('info', "You are now impersonating {$tenant->name}");
    }

    public function stopImpersonation()
    {
        $superAdminId = session('super_admin_id');
        $tenantId = session('impersonating_tenant');

        if (!$superAdminId) {
            return redirect()->route('login');
        }

        $superAdmin = \App\Models\SuperAdmin::find($superAdminId);
        
        if ($superAdmin && $tenantId) {
            $tenant = Tenant::find($tenantId);
            $superAdmin->logActivity(
                'tenant_impersonation_stopped',
                "Stopped impersonating tenant: {$tenant->name}",
                ['tenant_id' => $tenantId]
            );
        }

        session()->forget(['super_admin_id', 'impersonating_tenant']);

        auth()->logout();
        auth()->guard('super_admin')->login($superAdmin);

        return redirect()->route('super-admin.dashboard')
            ->with('info', 'Impersonation ended');
    }
}