<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature = null): Response
    {
        try {
            // Force explicit type checking to prevent accidental returns
            $response = $this->processSubscriptionCheck($request, $next, $feature);
            
            // Ensure we only return Response objects
            if (!$response instanceof Response) {
                \Log::error('CheckSubscription middleware: Invalid return type detected', [
                    'type' => gettype($response),
                    'class' => is_object($response) ? get_class($response) : 'not_object',
                    'feature' => $feature
                ]);
                return $next($request);
            }
            
            return $response;
            
        } catch (\Exception $e) {
            // Log error and continue without subscription check
            \Log::error('CheckSubscription middleware error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'url' => $request->url(),
                'feature' => $feature
            ]);
            
            return $next($request);
        }
    }

    private function processSubscriptionCheck(Request $request, Closure $next, ?string $feature): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return $next($request);
        }

        // Safely get tenant by ID to avoid relationship loading issues
        $tenantId = $user->tenant_id ?? null;
        if (!$tenantId) {
            return $next($request);
        }

        try {
            $tenant = \App\Models\Tenant::find($tenantId);
            if (!$tenant) {
                return $next($request);
            }

            // Check if tenant is on trial
            if (method_exists($tenant, 'isOnTrial') && $tenant->isOnTrial()) {
                $daysLeft = $tenant->trial_ends_at->diffInDays(now());
                if ($daysLeft <= 3) {
                    session()->flash('warning', "Tu periodo de prueba termina en {$daysLeft} días.");
                }
            }

            // Check feature limits based on subscription plan
            if ($feature) {
                $limits = $this->getSubscriptionLimits($tenant->subscription_plan ?? 'trial');
                
                if (!$this->checkFeatureLimit($tenant, $feature, $limits)) {
                    return redirect()->route('subscription.upgrade')
                        ->with('error', 'Has alcanzado el límite de tu plan. Actualiza tu suscripción.');
                }
            }

            return $next($request);
            
        } catch (\Exception $e) {
            \Log::warning('CheckSubscription tenant access error: ' . $e->getMessage());
            return $next($request);
        }
    }

    private function getSubscriptionLimits(string $plan): array
    {
        return match($plan) {
            'starter' => [
                'users' => 3,
                'documents_per_month' => 100,
                'customers' => 50,
                'products' => 100,
            ],
            'professional' => [
                'users' => 10,
                'documents_per_month' => 500,
                'customers' => 500,
                'products' => 1000,
            ],
            'enterprise' => [
                'users' => -1, // unlimited
                'documents_per_month' => -1,
                'customers' => -1,
                'products' => -1,
            ],
            default => [ // trial
                'users' => 2,
                'documents_per_month' => 50,
                'customers' => 20,
                'products' => 50,
            ],
        };
    }

    private function checkFeatureLimit($tenant, string $feature, array $limits): bool
    {
        if ($limits[$feature] === -1) return true; // unlimited

        return match($feature) {
            'users' => $tenant->users()->count() < $limits['users'],
            'documents' => $tenant->taxDocuments()
                ->whereMonth('created_at', now()->month)
                ->count() < $limits['documents_per_month'],
            'customers' => $tenant->customers()->count() < $limits['customers'],
            'products' => $tenant->products()->count() < $limits['products'],
            default => true,
        };
    }
}