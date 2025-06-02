<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            abort(403, 'Unauthorized');
        }

        // Check if user is admin (bypass permission check)
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Check using our custom permission system
        if (!$user->hasPermission($permission)) {
            // Fallback to Spatie Permission system
            try {
                setPermissionsTeamId($user->tenant_id);
                if (!$user->hasPermissionTo($permission)) {
                    abort(403, 'No tienes permiso para realizar esta acción');
                }
            } catch (\Exception $e) {
                abort(403, 'No tienes permiso para realizar esta acción');
            }
        }

        return $next($request);
    }
}