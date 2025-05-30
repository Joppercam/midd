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

        // Set the team ID for Spatie permissions (using tenant_id)
        setPermissionsTeamId($user->tenant_id);

        // Check if user has the required permission
        if (!$user->hasPermissionTo($permission)) {
            abort(403, 'No tienes permiso para realizar esta acción');
        }

        return $next($request);
    }
}