<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('super_admin')->check()) {
            return redirect()->route('super-admin.login');
        }

        $superAdmin = Auth::guard('super_admin')->user();
        
        if (!$superAdmin->is_active) {
            Auth::guard('super_admin')->logout();
            return redirect()->route('super-admin.login')
                ->withErrors(['Your account has been deactivated.']);
        }

        // Share super admin user with views
        view()->share('superAdmin', $superAdmin);

        return $next($request);
    }
}