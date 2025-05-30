<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request)
    {
        $this->checkPermission('users.view');

        $query = User::where('tenant_id', auth()->user()->tenant_id)
            ->with(['roles']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Active filter
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $users = $query->orderBy('name')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        $roles = Role::all();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only(['search', 'role', 'active']),
        ]);
    }

    public function create()
    {
        $this->checkPermission('users.create');

        $roles = Role::all();

        return Inertia::render('Admin/Users/Create', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $this->checkPermission('users.create');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|exists:roles,name',
            'active' => 'boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'tenant_id' => auth()->user()->tenant_id,
            'active' => $validated['active'] ?? true,
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('users.index')
            ->with('success', 'Usuario creado exitosamente.');
    }

    public function show(User $user)
    {
        $this->checkPermission('users.view');
        $this->checkTenantAccess($user);

        $user->load(['roles', 'permissions']);

        $activities = $user->activities()
            ->latest()
            ->take(20)
            ->get();

        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
            'activities' => $activities,
            'stats' => [
                'total_logins' => $user->login_count ?? 0,
                'last_login' => $user->last_login_at,
                'created_invoices' => $user->taxDocuments()->count(),
                'created_customers' => $user->customers()->count(),
            ],
        ]);
    }

    public function edit(User $user)
    {
        $this->checkPermission('users.edit');
        $this->checkTenantAccess($user);

        $roles = Role::all();
        $user->load('roles');

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->checkPermission('users.edit');
        $this->checkTenantAccess($user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => 'required|exists:roles,name',
            'active' => 'boolean',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'active' => $validated['active'] ?? true,
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->syncRoles([$validated['role']]);

        return redirect()->route('users.show', $user)
            ->with('success', 'Usuario actualizado exitosamente.');
    }

    public function destroy(User $user)
    {
        $this->checkPermission('users.delete');
        $this->checkTenantAccess($user);

        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        // Prevent deletion of the last admin
        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            return back()->with('error', 'No puedes eliminar el último administrador.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario eliminado exitosamente.');
    }

    public function permissions(User $user)
    {
        $this->checkPermission('users.permissions');
        $this->checkTenantAccess($user);

        $user->load(['roles', 'permissions']);
        
        $allPermissions = Permission::all()->groupBy(function ($permission) {
            return explode('.', $permission->name)[0];
        });

        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

        return Inertia::render('Admin/Users/Permissions', [
            'user' => $user,
            'permissions' => $allPermissions,
            'userPermissions' => $userPermissions,
        ]);
    }

    public function updatePermissions(Request $request, User $user)
    {
        $this->checkPermission('users.permissions');
        $this->checkTenantAccess($user);

        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $user->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('users.show', $user)
            ->with('success', 'Permisos actualizados exitosamente.');
    }

    public function impersonate(User $user)
    {
        $this->checkPermission('users.impersonate');
        $this->checkTenantAccess($user);

        // Store the original user ID in session
        session(['impersonator' => auth()->id()]);
        
        auth()->login($user);

        return redirect()->route('dashboard')
            ->with('info', "Ahora estás viendo el sistema como {$user->name}");
    }

    public function stopImpersonating()
    {
        $originalUserId = session('impersonator');
        
        if ($originalUserId) {
            $originalUser = User::find($originalUserId);
            if ($originalUser) {
                auth()->login($originalUser);
                session()->forget('impersonator');
                
                return redirect()->route('users.index')
                    ->with('info', 'Has vuelto a tu cuenta original.');
            }
        }

        return redirect()->route('dashboard');
    }

    protected function checkTenantAccess(User $user)
    {
        if ($user->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'No tienes acceso a este usuario.');
        }
    }
}