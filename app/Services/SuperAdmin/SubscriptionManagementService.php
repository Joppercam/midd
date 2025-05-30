<?php

namespace App\Services\SuperAdmin;

use App\Models\TenantSubscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionManagementService
{
    /**
     * Calculate Monthly Recurring Revenue
     */
    public function calculateMRR(): float
    {
        return TenantSubscription::where('status', 'active')
            ->where('billing_cycle', 'monthly')
            ->sum('amount');
    }

    /**
     * Calculate Annual Recurring Revenue
     */
    public function calculateARR(): float
    {
        $monthlyRevenue = $this->calculateMRR();
        $yearlyRevenue = TenantSubscription::where('status', 'active')
            ->where('billing_cycle', 'yearly')
            ->sum('amount');
        
        return ($monthlyRevenue * 12) + $yearlyRevenue;
    }

    /**
     * Upgrade a subscription plan
     */
    public function upgradePlan(TenantSubscription $subscription, SubscriptionPlan $newPlan, bool $immediate = false): TenantSubscription
    {
        DB::beginTransaction();
        
        try {
            $tenant = $subscription->tenant;
            $oldPlan = $subscription->plan;
            
            // Calculate prorated amount if immediate
            $proratedAmount = 0;
            if ($immediate && $subscription->billing_cycle === 'monthly') {
                $daysRemaining = now()->diffInDays($subscription->ends_at);
                $dailyRate = $subscription->amount / 30;
                $proratedCredit = $dailyRate * $daysRemaining;
                
                $newDailyRate = $newPlan->price_monthly / 30;
                $proratedAmount = ($newDailyRate * $daysRemaining) - $proratedCredit;
            }
            
            // Update current subscription
            if ($immediate) {
                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Upgraded to ' . $newPlan->name,
                ]);
                
                // Create new subscription
                $newSubscription = TenantSubscription::create([
                    'tenant_id' => $tenant->id,
                    'subscription_plan_id' => $newPlan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => $subscription->billing_cycle === 'monthly' 
                        ? now()->addMonth() 
                        : now()->addYear(),
                    'billing_cycle' => $subscription->billing_cycle,
                    'amount' => $subscription->billing_cycle === 'monthly' 
                        ? $newPlan->price_monthly 
                        : $newPlan->price_yearly,
                    'next_billing_date' => $subscription->billing_cycle === 'monthly' 
                        ? now()->addMonth() 
                        : now()->addYear(),
                ]);
            } else {
                // Schedule upgrade for next billing cycle
                $subscription->update([
                    'scheduled_plan_id' => $newPlan->id,
                    'scheduled_change_date' => $subscription->next_billing_date,
                ]);
                $newSubscription = $subscription;
            }
            
            // Update tenant limits based on new plan
            $tenant->update([
                'plan' => $newPlan->code,
                'max_users' => $newPlan->limits['max_users'],
                'max_documents_per_month' => $newPlan->limits['max_documents'] ?? 100,
                'max_products' => $newPlan->limits['max_products'] ?? 100,
                'max_customers' => $newPlan->limits['max_customers'] ?? 100,
                'api_access' => $newPlan->limits['api_access'] ?? false,
                'multi_branch' => $newPlan->limits['multi_branch'] ?? false,
                'features' => $newPlan->features,
            ]);
            
            DB::commit();
            
            return $newSubscription;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Downgrade a subscription plan
     */
    public function downgradePlan(TenantSubscription $subscription, SubscriptionPlan $newPlan): TenantSubscription
    {
        DB::beginTransaction();
        
        try {
            $tenant = $subscription->tenant;
            
            // Check if tenant usage allows downgrade
            $this->validateDowngrade($tenant, $newPlan);
            
            // Schedule downgrade for next billing cycle
            $subscription->update([
                'scheduled_plan_id' => $newPlan->id,
                'scheduled_change_date' => $subscription->next_billing_date,
            ]);
            
            DB::commit();
            
            return $subscription;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(TenantSubscription $subscription, string $reason, bool $immediate = false): void
    {
        if ($immediate) {
            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            
            // Suspend tenant if immediate cancellation
            $subscription->tenant->suspend($reason);
        } else {
            // Schedule cancellation for end of billing period
            $subscription->update([
                'scheduled_cancellation' => true,
                'scheduled_cancellation_date' => $subscription->ends_at,
                'cancellation_reason' => $reason,
            ]);
        }
    }

    /**
     * Get revenue analytics
     */
    public function getRevenueAnalytics(): array
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        
        // MRR Growth
        $currentMRR = $this->calculateMRR();
        $lastMonthMRR = TenantSubscription::where('status', 'active')
            ->where('billing_cycle', 'monthly')
            ->whereDate('created_at', '<', $currentMonth)
            ->sum('amount');
        
        $mrrGrowth = $lastMonthMRR > 0 
            ? (($currentMRR - $lastMonthMRR) / $lastMonthMRR) * 100 
            : 0;
        
        // Churn Rate
        $cancelledThisMonth = TenantSubscription::where('status', 'cancelled')
            ->whereMonth('cancelled_at', $currentMonth->month)
            ->count();
        
        $activeLastMonth = TenantSubscription::where('status', 'active')
            ->whereDate('created_at', '<', $currentMonth)
            ->count();
        
        $churnRate = $activeLastMonth > 0 
            ? ($cancelledThisMonth / $activeLastMonth) * 100 
            : 0;
        
        // Average Revenue Per User (ARPU)
        $activeSubscriptions = TenantSubscription::where('status', 'active')->count();
        $arpu = $activeSubscriptions > 0 ? $currentMRR / $activeSubscriptions : 0;
        
        // Customer Lifetime Value (CLV)
        $avgSubscriptionLength = TenantSubscription::where('status', 'cancelled')
            ->selectRaw('AVG(DATEDIFF(cancelled_at, starts_at)) as avg_days')
            ->value('avg_days') ?? 365;
        
        $clv = $arpu * ($avgSubscriptionLength / 30); // Convert to months
        
        // Revenue by Plan
        $revenueByPlan = TenantSubscription::where('status', 'active')
            ->join('subscription_plans', 'tenant_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->select('subscription_plans.name', DB::raw('SUM(tenant_subscriptions.amount) as total'))
            ->groupBy('subscription_plans.name')
            ->get();
        
        // Growth Trends
        $growthTrends = $this->getGrowthTrends();
        
        return [
            'mrr' => $currentMRR,
            'arr' => $this->calculateARR(),
            'mrr_growth' => $mrrGrowth,
            'churn_rate' => $churnRate,
            'arpu' => $arpu,
            'clv' => $clv,
            'revenue_by_plan' => $revenueByPlan,
            'growth_trends' => $growthTrends,
        ];
    }

    /**
     * Validate if a tenant can downgrade to a specific plan
     */
    private function validateDowngrade(Tenant $tenant, SubscriptionPlan $newPlan): void
    {
        $errors = [];
        
        if ($tenant->users()->count() > $newPlan->limits['max_users']) {
            $errors[] = "Tenant has {$tenant->users()->count()} users, but new plan allows only {$newPlan->limits['max_users']}";
        }
        
        if ($tenant->products()->count() > $newPlan->limits['max_products']) {
            $errors[] = "Tenant has {$tenant->products()->count()} products, but new plan allows only {$newPlan->limits['max_products']}";
        }
        
        if ($tenant->customers()->count() > $newPlan->limits['max_customers']) {
            $errors[] = "Tenant has {$tenant->customers()->count()} customers, but new plan allows only {$newPlan->limits['max_customers']}";
        }
        
        if (!empty($errors)) {
            throw new \Exception('Cannot downgrade: ' . implode(', ', $errors));
        }
    }

    /**
     * Get growth trends for the last 12 months
     */
    private function getGrowthTrends(): array
    {
        $trends = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            
            $monthlyRevenue = TenantSubscription::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->where('status', 'active')
                ->sum('amount');
            
            $newCustomers = Tenant::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            
            $churned = TenantSubscription::whereYear('cancelled_at', $date->year)
                ->whereMonth('cancelled_at', $date->month)
                ->count();
            
            $trends[] = [
                'month' => $date->format('M Y'),
                'revenue' => $monthlyRevenue,
                'new_customers' => $newCustomers,
                'churned' => $churned,
            ];
        }
        
        return $trends;
    }
}