<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    use ChecksPermissions;

    public function index()
    {
        $this->checkPermission('roles.view');

        $roles = Role::with(['permissions', 'users'])->get();

        $rolesWithStats = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
                'users_count' => $role->users->count(),
                'permissions_count' => $role->permissions->count(),
                'permissions' => $role->permissions,
            ];
        });

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $rolesWithStats,
        ]);
    }

    public function create()
    {
        $this->checkPermission('roles.create');

        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        return Inertia::render('Admin/Roles/Create', [
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $this->checkPermission('roles.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create(['name' => $validated['name']]);
        
        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return redirect()->route('roles.index')
            ->with('success', 'Rol creado exitosamente.');
    }

    public function show(Role $role)
    {
        $this->checkPermission('roles.view');

        $role->load(['permissions', 'users']);

        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return Inertia::render('Admin/Roles/Show', [
            'role' => $role,
            'permissions' => $permissions,
            'rolePermissions' => $rolePermissions,
            'users' => $role->users,
        ]);
    }

    public function edit(Role $role)
    {
        $this->checkPermission('roles.edit');

        // Prevent editing the admin role
        if ($role->name === 'admin') {
            return back()->with('error', 'El rol de administrador no puede ser editado.');
        }

        $permissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return Inertia::render('Admin/Roles/Edit', [
            'role' => $role,
            'permissions' => $permissions,
            'rolePermissions' => $rolePermissions,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $this->checkPermission('roles.edit');

        // Prevent editing the admin role
        if ($role->name === 'admin') {
            return back()->with('error', 'El rol de administrador no puede ser editado.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles.show', $role)
            ->with('success', 'Rol actualizado exitosamente.');
    }

    public function destroy(Role $role)
    {
        $this->checkPermission('roles.delete');

        // Prevent deleting system roles
        $systemRoles = ['admin', 'gerente', 'contador', 'vendedor', 'usuario'];
        if (in_array($role->name, $systemRoles)) {
            return back()->with('error', 'No se pueden eliminar los roles del sistema.');
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
            return back()->with('error', 'No se puede eliminar un rol que tiene usuarios asignados.');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Rol eliminado exitosamente.');
    }

    public function duplicate(Role $role)
    {
        $this->checkPermission('roles.create');

        $newRole = Role::create([
            'name' => $role->name . '_copy',
        ]);

        $newRole->syncPermissions($role->permissions);

        return redirect()->route('roles.edit', $newRole)
            ->with('success', 'Rol duplicado exitosamente.');
    }
}