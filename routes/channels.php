<?php

use Illuminate\Support\Facades\Broadcast;

// Canal privado para usuarios específicos
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado para usuarios específicos (alternativo)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Canal privado para tenants
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    return $user->tenant_id && (int) $user->tenant_id === (int) $tenantId;
});

// Canal de presencia para usuarios activos en un tenant
Broadcast::channel('tenant.{tenantId}.presence', function ($user, $tenantId) {
    if ($user->tenant_id && (int) $user->tenant_id === (int) $tenantId) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roles->first()->name ?? 'user'
        ];
    }
    return false;
});
