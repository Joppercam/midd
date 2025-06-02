<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenant = null;
        $user = $request->user();
        $superAdmin = $request->user('super_admin');
        $userRoles = [];
        $userPermissions = [];
        
        // Manejar SuperAdmin separadamente
        if ($superAdmin) {
            return [
                ...parent::share($request),
                'auth' => [
                    'superAdmin' => [
                        'id' => $superAdmin->id,
                        'name' => $superAdmin->name,
                        'email' => $superAdmin->email,
                        'roles' => ['super_admin'],
                        'permissions' => ['*'], // Acceso total
                    ],
                ],
                'flash' => [
                    'success' => fn () => $request->session()->get('success'),
                    'error' => fn () => $request->session()->get('error'),
                    'warning' => fn () => $request->session()->get('warning'),
                    'info' => fn () => $request->session()->get('info'),
                ],
                'app' => [
                    'name' => config('app.name'),
                    'url' => config('app.url'),
                ],
            ];
        }
        
        if ($user) {
            // Cargar relaciones necesarias solo para usuarios regulares
            $user->load(['tenant', 'roles', 'permissions']);
            
            if ($user->tenant) {
                $tenant = $user->tenant;
                $tenant->updateLastActivity();
            }
            
            // Obtener roles y permisos del usuario
            $userRoles = [$user->role ?? 'user'];
            // Use custom_permissions attribute instead of permissions relationship
            $userPermissions = $user->custom_permissions ?? [];
            
            if ($user->isAdmin()) {
                // Dar todos los permisos básicos al admin
                $userPermissions = array_merge($userPermissions, [
                    'dashboard.view',
                    'customers.view', 'customers.create', 'customers.update', 'customers.delete',
                    'products.view', 'products.create', 'products.update', 'products.delete',
                    'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
                    'quotes.view', 'quotes.create', 'quotes.update', 'quotes.delete',
                    'reports.view', 'payments.view', 'expenses.view',
                    // Agregar más según sea necesario
                    '*' // Acceso completo para admin
                ]);
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'roles' => $userRoles,
                    'permissions' => $userPermissions,
                    'tenant' => $tenant ? [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'legal_name' => $tenant->legal_name,
                        'logo_url' => $tenant->logo_url,
                        'primary_color' => $tenant->primary_color,
                        'secondary_color' => $tenant->secondary_color,
                        'subscription_plan' => $tenant->subscription_plan,
                        'subscription_status' => $tenant->subscription_status,
                        'plan' => $tenant->plan,
                        'features' => $tenant->features ?? [],
                    ] : null,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'app' => [
                'name' => config('app.name'),
                'url' => config('app.url'),
            ],
        ];
    }
}
