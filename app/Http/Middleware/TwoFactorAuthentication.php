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
        try {
            // Force explicit type checking to prevent accidental returns
            $response = $this->processRequest($request, $next);
            
            // Ensure we only return Response objects
            if (!$response instanceof Response) {
                \Log::error('TwoFactorAuthentication middleware: Invalid return type detected', [
                    'type' => gettype($response),
                    'class' => is_object($response) ? get_class($response) : 'not_object'
                ]);
                return $next($request);
            }
            
            return $response;
            
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('TwoFactorAuthentication middleware error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'url' => $request->url()
            ]);
            
            // Always return a valid Response
            return $next($request);
        }
    }

    private function processRequest(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Si el usuario no existe, continuar
        if (!$user) {
            return $next($request);
        }

        // Verificar si la propiedad two_factor_enabled existe y está habilitada
        $twoFactorEnabled = false;
        try {
            // Explicitly check for property existence to avoid relationship loading
            if (property_exists($user, 'two_factor_enabled') && isset($user->two_factor_enabled)) {
                $twoFactorEnabled = (bool)$user->two_factor_enabled;
            }
        } catch (\Exception $e) {
            // Si hay error accediendo a la propiedad, asumir que no está habilitado
            $twoFactorEnabled = false;
            \Log::debug('2FA property access error: ' . $e->getMessage());
        }

        // Si el usuario no tiene 2FA habilitado, continuar normalmente
        if (!$twoFactorEnabled) {
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

        // Verificar que la ruta de verificación existe antes de redirigir
        if (!\Route::has('two-factor.verify')) {
            \Log::warning('Two-factor verification route not found, skipping 2FA check');
            return $next($request);
        }

        // Redirigir a la página de verificación 2FA
        return redirect()->route('two-factor.verify');
    }
}