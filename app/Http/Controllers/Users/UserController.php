<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request)
    {
        $this->checkPermission('users.view');

        $query = User::where('tenant_id', auth()->user()->tenant_id)
            ->with(['roles']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();

        // Transform users to include role names
        $users->getCollection()->transform(function ($user) {
            $user->role_names = $user->getRoleNames();
            return $user;
        });

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => Role::pluck('name'),
            'filters' => $request->only(['search', 'role', 'is_active']),
        ]);
    }

    public function create()
    {
        $this->checkPermission('users.create');

        return Inertia::render('Users/Create', [
            'roles' => Role::all()->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $this->getRoleDisplayName($role->name),
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $this->checkPermission('users.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|exists:roles,name',
            'is_active' => 'boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'tenant_id' => auth()->user()->tenant_id,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Set tenant context and assign role
        setPermissionsTeamId($user->tenant_id);
        $user->assignRole($validated['role']);

        return redirect()->route('users.index')
            ->with('success', 'Usuario creado exitosamente.');
    }

    public function edit(User $user)
    {
        $this->checkPermission('users.edit');

        // Verify tenant
        if ($user->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        // Set tenant context
        setPermissionsTeamId($user->tenant_id);

        return Inertia::render('Users/Edit', [
            'user' => $user->load('roles'),
            'userRole' => $user->getRoleNames()->first(),
            'roles' => Role::all()->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $this->getRoleDisplayName($role->name),
                ];
            }),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->checkPermission('users.edit');

        // Verify tenant
        if ($user->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|exists:roles,name',
            'is_active' => 'boolean',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        // Update role
        setPermissionsTeamId($user->tenant_id);
        $user->syncRoles([$validated['role']]);

        return redirect()->route('users.index')
            ->with('success', 'Usuario actualizado exitosamente.');
    }

    public function destroy(User $user)
    {
        $this->checkPermission('users.delete');

        // Verify tenant
        if ($user->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario eliminado exitosamente.');
    }

    /**
     * Get display name for role
     */
    private function getRoleDisplayName($roleName)
    {
        $names = [
            'super-admin' => 'Super Administrador',
            'admin' => 'Administrador',
            'accountant' => 'Contador',
            'sales' => 'Ventas',
            'viewer' => 'Solo Lectura',
        ];

        return $names[$roleName] ?? ucfirst($roleName);
    }
}