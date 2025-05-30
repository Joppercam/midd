<?php

namespace App\Traits;

use Illuminate\Auth\Access\AuthorizationException;

trait ChecksPermissions
{
    /**
     * Check if the authenticated user has the given permission
     *
     * @param string $permission
     * @throws AuthorizationException
     */
    protected function checkPermission(string $permission): void
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new AuthorizationException('Usuario no autenticado');
        }

        // Set the team ID for Spatie permissions (using tenant_id)
        if ($user->tenant_id) {
            setPermissionsTeamId($user->tenant_id);
        }

        // Check if user has super-admin role (bypass all permission checks)
        try {
            if ($user->hasRole('super-admin')) {
                return;
            }
        } catch (\Exception $e) {
            // If hasRole fails, continue with permission check
        }

        // For now, use the simple permission check from the User model
        // until we fix the Spatie Permission integration
        if (!$user->hasPermission($permission) && !$user->isAdmin()) {
            throw new AuthorizationException('No tienes permiso para realizar esta acción');
        }
    }

    /**
     * Check if the authenticated user has any of the given permissions
     *
     * @param array $permissions
     * @throws AuthorizationException
     */
    protected function checkAnyPermission(array $permissions): void
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new AuthorizationException('Usuario no autenticado');
        }

        // Set the team ID for Spatie permissions (using tenant_id)
        if ($user->tenant_id) {
            setPermissionsTeamId($user->tenant_id);
        }

        // Check if user has super-admin role or is admin (bypass all permission checks)
        try {
            if ($user->hasRole('super-admin')) {
                return;
            }
        } catch (\Exception $e) {
            // If hasRole fails, continue with permission check
        }

        if ($user->isAdmin()) {
            return;
        }

        // Manual check using the User model's hasPermission method
        $hasPermission = false;
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                $hasPermission = true;
                break;
            }
        }
        
        if (!$hasPermission) {
            throw new AuthorizationException('No tienes permiso para realizar esta acción');
        }
    }

    /**
     * Check if the authenticated user has all of the given permissions
     *
     * @param array $permissions
     * @throws AuthorizationException
     */
    protected function checkAllPermissions(array $permissions): void
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new AuthorizationException('Usuario no autenticado');
        }

        // Set the team ID for Spatie permissions (using tenant_id)
        if ($user->tenant_id) {
            setPermissionsTeamId($user->tenant_id);
        }

        // Check if user has super-admin role or is admin (bypass all permission checks)
        try {
            if ($user->hasRole('super-admin')) {
                return;
            }
        } catch (\Exception $e) {
            // If hasRole fails, continue with permission check
        }

        if ($user->isAdmin()) {
            return;
        }

        // Manual check using the User model's hasPermission method
        foreach ($permissions as $permission) {
            if (!$user->hasPermission($permission)) {
                throw new AuthorizationException('No tienes todos los permisos necesarios para realizar esta acción');
            }
        }
    }

    /**
     * Check if the authenticated user has the given role
     *
     * @param string $role
     * @throws AuthorizationException
     */
    protected function checkRole(string $role): void
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new AuthorizationException('Usuario no autenticado');
        }

        // Set the team ID for Spatie permissions (using tenant_id)
        if ($user->tenant_id) {
            setPermissionsTeamId($user->tenant_id);
        }

        // For now, check if user is admin directly
        // This is a temporary fix until we properly configure Spatie Permission
        if ($role === 'admin' && !$user->isAdmin()) {
            throw new AuthorizationException('No tienes el rol necesario para realizar esta acción');
        }
        
        // For other roles, we'll need to implement a proper check later
        // For now, allow access if user is admin
        if (!$user->isAdmin() && $role !== 'admin') {
            throw new AuthorizationException('No tienes el rol necesario para realizar esta acción');
        }
    }
}