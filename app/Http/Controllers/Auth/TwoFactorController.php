<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TwoFactorController extends Controller
{
    protected TwoFactorAuthService $twoFactorService;

    public function __construct(TwoFactorAuthService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    /**
     * Show the 2FA setup page
     */
    public function setup()
    {
        $user = auth()->user();
        
        if ($user->two_factor_enabled) {
            return redirect()->route('profile.edit')
                ->with('message', '2FA ya está habilitado en tu cuenta.');
        }

        // Generate secret and QR code
        $secret = $this->twoFactorService->generateSecret($user);
        $qrCode = $this->twoFactorService->generateQRCode($user);

        return Inertia::render('Auth/TwoFactorSetup', [
            'secret' => $secret,
            'qrCode' => $qrCode,
        ]);
    }

    /**
     * Enable 2FA for the user
     */
    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = auth()->user();
        
        if ($user->two_factor_enabled) {
            return back()->withErrors(['code' => '2FA ya está habilitado.']);
        }

        // Verify the code
        if (!$this->twoFactorService->verifyCode($user, $request->code)) {
            return back()->withErrors(['code' => 'El código es inválido.']);
        }

        // Enable 2FA
        $user->update(['two_factor_enabled' => true]);
        
        Log::info('2FA enabled for user', ['user_id' => $user->id]);

        return redirect()->route('profile.edit')
            ->with('success', '2FA ha sido habilitado exitosamente.');
    }

    /**
     * Show the 2FA verification page
     */
    public function verify()
    {
        if (!auth()->user()->two_factor_enabled) {
            return redirect()->route('dashboard');
        }

        if (session('2fa_verified')) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/TwoFactorVerify');
    }

    /**
     * Verify the 2FA code
     */
    public function check(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = auth()->user();

        if (!$this->twoFactorService->verifyCode($user, $request->code)) {
            return back()->withErrors(['code' => 'El código es inválido.']);
        }

        // Mark as verified in session
        session(['2fa_verified' => true]);
        
        Log::info('2FA verification successful', ['user_id' => $user->id]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = auth()->user();
        
        if (!$user->two_factor_enabled) {
            return back()->withErrors(['password' => '2FA no está habilitado.']);
        }

        // Disable 2FA
        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ]);
        
        // Clear 2FA verification from session
        session()->forget('2fa_verified');
        
        Log::info('2FA disabled for user', ['user_id' => $user->id]);

        return redirect()->route('profile.edit')
            ->with('success', '2FA ha sido deshabilitado.');
    }

    /**
     * Generate recovery codes
     */
    public function recoveryCodes()
    {
        $user = auth()->user();
        
        if (!$user->two_factor_enabled) {
            return redirect()->route('profile.edit')
                ->with('error', 'Debes habilitar 2FA primero.');
        }

        $codes = $this->twoFactorService->generateRecoveryCodes($user);

        return Inertia::render('Auth/TwoFactorRecoveryCodes', [
            'codes' => $codes,
        ]);
    }
}