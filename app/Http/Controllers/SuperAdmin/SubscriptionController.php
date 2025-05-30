<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;
use App\Services\SuperAdmin\SubscriptionManagementService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    protected $subscriptionService;

    public function __construct(SubscriptionManagementService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function plans()
    {
        $plans = SubscriptionPlan::withCount('subscriptions')->get();

        return Inertia::render('SuperAdmin/Subscriptions/Plans', [
            'plans' => $plans,
        ]);
    }

    public function createPlan()
    {
        return Inertia::render('SuperAdmin/Subscriptions/CreatePlan');
    }

    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:subscription_plans',
            'description' => 'required|string',
            'monthly_price' => 'required|numeric|min:0',
            'annual_price' => 'required|numeric|min:0',
            'trial_days' => 'required|integer|min:0',
            'features' => 'required|array',
        ]);

        // Add required fields that aren't in the form
        $validated['included_modules'] = [];
        $validated['limits'] = [
            'max_users' => 10,
            'max_documents_per_month' => 1000,
            'max_products' => 500,
            'max_customers' => 500,
        ];

        $plan = SubscriptionPlan::create($validated);

        auth()->guard('super_admin')->user()->logActivity(
            'subscription_plan_created',
            "Created subscription plan: {$plan->name}",
            ['plan_id' => $plan->id]
        );

        return redirect()->route('super-admin.subscriptions.index')
            ->with('success', 'Plan de suscripción creado exitosamente');
    }

    public function editPlan(SubscriptionPlan $plan)
    {
        return Inertia::render('SuperAdmin/Subscriptions/EditPlan', [
            'plan' => $plan,
        ]);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:subscription_plans,code,' . $plan->id,
            'description' => 'required|string',
            'monthly_price' => 'required|numeric|min:0',
            'annual_price' => 'required|numeric|min:0',
            'trial_days' => 'required|integer|min:0',
            'features' => 'required|array',
            'included_modules' => 'nullable|array',
            'limits' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        // Ensure required fields have defaults if not provided
        if (!isset($validated['included_modules'])) {
            $validated['included_modules'] = [];
        }
        if (!isset($validated['limits'])) {
            $validated['limits'] = [
                'max_users' => 10,
                'max_documents_per_month' => 1000,
                'max_products' => 500,
                'max_customers' => 500,
            ];
        }

        $plan->update($validated);

        auth()->guard('super_admin')->user()->logActivity(
            'subscription_plan_updated',
            "Updated subscription plan: {$plan->name}",
            ['plan_id' => $plan->id, 'changes' => $validated]
        );

        return redirect()->route('super-admin.subscriptions.index')
            ->with('success', 'Plan de suscripción actualizado exitosamente');
    }

    public function subscriptions(Request $request)
    {
        $query = TenantSubscription::with(['tenant', 'plan']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plan')) {
            $query->whereHas('plan', function ($q) use ($request) {
                $q->where('slug', $request->plan);
            });
        }

        $subscriptions = $query->latest()->paginate(15);

        $stats = [
            'total' => TenantSubscription::count(),
            'active' => TenantSubscription::where('status', 'active')->count(),
            'trial' => TenantSubscription::where('status', 'trial')->count(),
            'cancelled' => TenantSubscription::where('status', 'cancelled')->count(),
            'mrr' => $this->subscriptionService->calculateMRR(),
            'arr' => $this->subscriptionService->calculateARR(),
        ];

        return Inertia::render('SuperAdmin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'stats' => $stats,
            'filters' => $request->only(['status', 'plan']),
        ]);
    }

    public function upgradeTenant(Request $request, TenantSubscription $subscription)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'immediate' => 'boolean',
        ]);

        $newPlan = SubscriptionPlan::find($validated['plan_id']);
        
        $result = $this->subscriptionService->upgradePlan(
            $subscription,
            $newPlan,
            $validated['immediate'] ?? false
        );

        auth()->guard('super_admin')->user()->logActivity(
            'subscription_upgraded',
            "Upgraded subscription for tenant: {$subscription->tenant->name}",
            [
                'tenant_id' => $subscription->tenant_id,
                'old_plan' => $subscription->plan->slug,
                'new_plan' => $newPlan->slug,
            ]
        );

        return back()->with('success', 'Subscription upgraded successfully');
    }

    public function downgradeTenant(Request $request, TenantSubscription $subscription)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $newPlan = SubscriptionPlan::find($validated['plan_id']);
        
        $result = $this->subscriptionService->downgradePlan($subscription, $newPlan);

        auth()->guard('super_admin')->user()->logActivity(
            'subscription_downgraded',
            "Downgraded subscription for tenant: {$subscription->tenant->name}",
            [
                'tenant_id' => $subscription->tenant_id,
                'old_plan' => $subscription->plan->slug,
                'new_plan' => $newPlan->slug,
            ]
        );

        return back()->with('success', 'Subscription downgraded successfully');
    }

    public function cancelSubscription(Request $request, TenantSubscription $subscription)
    {
        $validated = $request->validate([
            'reason' => 'required|string',
            'immediate' => 'boolean',
        ]);

        $this->subscriptionService->cancelSubscription(
            $subscription,
            $validated['reason'],
            $validated['immediate'] ?? false
        );

        auth()->guard('super_admin')->user()->logActivity(
            'subscription_cancelled',
            "Cancelled subscription for tenant: {$subscription->tenant->name}",
            [
                'tenant_id' => $subscription->tenant_id,
                'reason' => $validated['reason'],
            ]
        );

        return back()->with('success', 'Subscription cancelled successfully');
    }

    public function revenue()
    {
        $revenueData = $this->subscriptionService->getRevenueAnalytics();

        return Inertia::render('SuperAdmin/Subscriptions/Revenue', [
            'revenueData' => $revenueData,
        ]);
    }

    public function togglePlanStatus(SubscriptionPlan $plan)
    {
        $plan->update(['is_active' => !$plan->is_active]);

        auth()->guard('super_admin')->user()->logActivity(
            'subscription_plan_toggled',
            "Toggled status for subscription plan: {$plan->name}",
            ['plan_id' => $plan->id, 'new_status' => $plan->is_active]
        );

        return back()->with('success', $plan->is_active ? 'Plan activado exitosamente' : 'Plan desactivado exitosamente');
    }

    public function destroyPlan(SubscriptionPlan $plan)
    {
        // Check if plan has any subscriptions
        if ($plan->subscriptions()->count() > 0) {
            return back()->with('error', 'No se puede eliminar un plan que tiene suscripciones activas');
        }

        $planName = $plan->name;
        $plan->delete();

        auth()->guard('super_admin')->user()->logActivity(
            'subscription_plan_deleted',
            "Deleted subscription plan: {$planName}",
            ['plan_name' => $planName]
        );

        return back()->with('success', 'Plan eliminado exitosamente');
    }
}