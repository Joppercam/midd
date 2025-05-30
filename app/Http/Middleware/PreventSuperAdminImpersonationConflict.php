<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PreventSuperAdminImpersonationConflict
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If a super admin is impersonating a tenant user,
        // prevent access to super admin routes
        if (session()->has('impersonating_tenant') && $request->routeIs('super-admin.*')) {
            return redirect()->route('dashboard')
                ->with('error', 'Cannot access super admin panel while impersonating a tenant.');
        }

        return $next($request);
    }
}