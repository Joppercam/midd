<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuthentication
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Si el usuario no tiene 2FA habilitado, continuar normalmente
        if (!$user || !$user->two_factor_enabled) {
            return $next($request);
        }

        // Si ya verificó 2FA en esta sesión, continuar
        if ($request->session()->get('2fa_verified')) {
            return $next($request);
        }

        // Si está intentando acceder a rutas de 2FA, permitir
        if ($request->is('two-factor/*') || $request->is('logout')) {
            return $next($request);
        }

        // Redirigir a la página de verificación 2FA
        return redirect()->route('two-factor.verify');
    }
}