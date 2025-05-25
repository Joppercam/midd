<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!auth()->user()->tenant_id) {
            auth()->logout();
            return redirect()->route('login')
                ->with('error', 'Tu cuenta no está asociada a ninguna empresa.');
        }

        if (!auth()->user()->tenant->isActive()) {
            auth()->logout();
            return redirect()->route('login')
                ->with('error', 'Tu suscripción ha expirado. Contacta con soporte.');
        }

        return $next($request);
    }
}