<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SuperAdminAuthController extends Controller
{
    public function showLoginForm()
    {
        return Inertia::render('SuperAdmin/Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $superAdmin = SuperAdmin::where('email', $credentials['email'])->first();

        if (!$superAdmin || !Hash::check($credentials['password'], $superAdmin->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$superAdmin->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // Create a custom guard for super admins
        Auth::guard('super_admin')->login($superAdmin, $request->boolean('remember'));

        $superAdmin->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $superAdmin->logActivity('login', 'Super admin logged in');

        $request->session()->regenerate();

        return redirect()->intended(route('super-admin.dashboard'));
    }

    public function logout(Request $request)
    {
        $superAdmin = Auth::guard('super_admin')->user();
        
        if ($superAdmin) {
            $superAdmin->logActivity('logout', 'Super admin logged out');
        }

        Auth::guard('super_admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('super-admin.login');
    }
}