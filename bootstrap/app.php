<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Global middleware (now safe for development with internal checks)
        $middleware->append([
            \App\Http\Middleware\IPBlacklist::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\WAFProtection::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\HandleApiErrors::class,
            \App\Http\Middleware\WAFProtection::class,
        ]);

        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckTenantPermission::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission.spatie' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'api.auth' => \App\Http\Middleware\ApiAuthentication::class,
            'handle.errors' => \App\Http\Middleware\HandleApiErrors::class,
            'module' => \App\Http\Middleware\CheckModuleAccess::class,
            'check.module' => \App\Http\Middleware\CheckModuleMiddleware::class,
            'check.subscription' => \App\Http\Middleware\CheckSubscription::class,
            'super_admin' => \App\Http\Middleware\SuperAdminAuthentication::class,
            'super_admin_guest' => \App\Http\Middleware\SuperAdminGuest::class,
            'prevent_impersonation_conflict' => \App\Http\Middleware\PreventSuperAdminImpersonationConflict::class,
            'rate_limit' => \App\Http\Middleware\RateLimiting::class,
            'waf' => \App\Http\Middleware\WAFProtection::class,
            'security_headers' => \App\Http\Middleware\SecurityHeaders::class,
            'ip_blacklist' => \App\Http\Middleware\IPBlacklist::class,
            '2fa' => \App\Http\Middleware\TwoFactorAuthentication::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
