<?php

namespace App\Modules\Core\Controllers;

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
    
    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'check.module:core']);
    }

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

        return Inertia::render('Core/Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only(['search', 'role', 'active']),
        ]);
    }

    public function create()
    {
        $this->checkPermission('users.create');

        $roles = Role::all();

        return Inertia::render('Core/Users/Create', [
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
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return Inertia::render('Core/Users/Show', [
            'user' => $user,
            'activities' => $activities,
        ]);
    }

    public function edit(User $user)
    {
        $this->checkPermission('users.edit');
        $this->checkTenantAccess($user);

        $user->load('roles');
        $roles = Role::all();

        return Inertia::render('Core/Users/Edit', [
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

        return redirect()->route('users.index')
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

        // Prevent deletion of last admin
        if ($user->hasRole('admin')) {
            $adminCount = User::where('tenant_id', $user->tenant_id)
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'admin');
                })
                ->count();

            if ($adminCount <= 1) {
                return back()->with('error', 'No se puede eliminar el último administrador.');
            }
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario eliminado exitosamente.');
    }

    public function impersonate(User $user)
    {
        $this->checkPermission('users.impersonate');
        $this->checkTenantAccess($user);

        // Cannot impersonate super-admin or self
        if ($user->hasRole('super-admin') || $user->id === auth()->id()) {
            return back()->with('error', 'No puedes suplantar a este usuario.');
        }

        session()->put('impersonating', auth()->id());
        auth()->login($user);

        return redirect()->route('dashboard')
            ->with('info', 'Ahora estás suplantando a ' . $user->name);
    }

    public function stopImpersonating()
    {
        if (!session()->has('impersonating')) {
            return redirect()->route('dashboard');
        }

        $originalUserId = session()->pull('impersonating');
        $originalUser = User::find($originalUserId);

        if ($originalUser) {
            auth()->login($originalUser);
        }

        return redirect()->route('dashboard')
            ->with('info', 'Has dejado de suplantar al usuario.');
    }

    public function permissions(User $user)
    {
        $this->checkPermission('users.edit');
        $this->checkTenantAccess($user);

        $user->load(['roles', 'permissions']);
        $allPermissions = Permission::orderBy('name')->get()
            ->groupBy(function ($permission) {
                return explode('.', $permission->name)[0];
            });

        return Inertia::render('Core/Users/Permissions', [
            'user' => $user,
            'allPermissions' => $allPermissions,
            'userPermissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    public function updatePermissions(Request $request, User $user)
    {
        $this->checkPermission('users.edit');
        $this->checkTenantAccess($user);

        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $user->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', 'Permisos actualizados exitosamente.');
    }

    public function activity(User $user)
    {
        $this->checkPermission('users.view');
        $this->checkTenantAccess($user);

        $activities = $user->activities()
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return Inertia::render('Core/Users/Activity', [
            'user' => $user,
            'activities' => $activities,
        ]);
    }

    private function checkTenantAccess($user)
    {
        if ($user->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'No tienes acceso a este usuario.');
        }
    }
}