<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;
use Illuminate\Support\Facades\Cache;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorAuthService
{
    protected Google2FA $google2fa;
    protected string $issuer;
    protected int $window;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
        $this->issuer = config('security.two_factor.issuer', 'CrecePyme');
        $this->window = config('security.two_factor.window', 1);
    }

    /**
     * Generate secret key for user
     */
    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Generate QR code for 2FA setup
     */
    public function generateQRCode(User $user): string
    {
        $secret = $user->two_factor_secret;
        
        if (!$secret) {
            throw new \Exception('User does not have a 2FA secret');
        }

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            $this->issuer,
            $user->email,
            $secret
        );

        // Generate QR code image
        $renderer = new ImageRenderer(
            new RendererStyle(400),
            new ImagickImageBackEnd()
        );
        
        $writer = new Writer($renderer);
        
        return base64_encode($writer->writeString($qrCodeUrl));
    }

    /**
     * Enable 2FA for user
     */
    public function enable(User $user, string $token): bool
    {
        if (!$this->verifyToken($user, $token)) {
            return false;
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_enabled_at' => now(),
        ]);

        // Generate recovery codes
        $this->generateRecoveryCodes($user);

        // Log security event
        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->log('Two-factor authentication enabled');

        return true;
    }

    /**
     * Disable 2FA for user
     */
    public function disable(User $user, string $password): bool
    {
        if (!password_verify($password, $user->password)) {
            return false;
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled_at' => null,
        ]);

        // Clear any pending 2FA sessions
        $this->clearPendingSession($user);

        // Log security event
        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->log('Two-factor authentication disabled');

        return true;
    }

    /**
     * Verify 2FA token
     */
    public function verifyToken(User $user, string $token): bool
    {
        if (!$user->two_factor_secret) {
            return false;
        }

        // Check if token was recently used (prevent replay attacks)
        if ($this->isTokenRecentlyUsed($user, $token)) {
            return false;
        }

        $valid = $this->google2fa->verifyKey(
            $user->two_factor_secret,
            $token,
            $this->window
        );

        if ($valid) {
            $this->markTokenAsUsed($user, $token);
        }

        return $valid;
    }

    /**
     * Verify recovery code
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $recoveryCodes = $user->two_factor_recovery_codes ?? [];

        if (!in_array($code, $recoveryCodes)) {
            return false;
        }

        // Remove used recovery code
        $recoveryCodes = array_diff($recoveryCodes, [$code]);
        
        $user->update([
            'two_factor_recovery_codes' => array_values($recoveryCodes),
        ]);

        // Log security event
        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties(['recovery_code_used' => true])
            ->log('Two-factor recovery code used');

        // Notify user if running low on recovery codes
        if (count($recoveryCodes) <= 2) {
            // TODO: Send notification to user
        }

        return true;
    }

    /**
     * Generate recovery codes
     */
    public function generateRecoveryCodes(User $user): array
    {
        $codes = [];
        $count = config('security.two_factor.recovery_codes', 8);

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4) . '-' . Str::random(4));
        }

        $user->update([
            'two_factor_recovery_codes' => $codes,
        ]);

        return $codes;
    }

    /**
     * Create pending 2FA session
     */
    public function createPendingSession(User $user): string
    {
        $token = Str::random(64);
        
        Cache::put(
            "2fa_pending:{$token}",
            [
                'user_id' => $user->id,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            now()->addMinutes(5)
        );

        return $token;
    }

    /**
     * Verify pending 2FA session
     */
    public function verifyPendingSession(string $sessionToken, string $authToken): ?User
    {
        $session = Cache::get("2fa_pending:{$sessionToken}");
        
        if (!$session) {
            return null;
        }

        // Verify IP and user agent match
        if ($session['ip'] !== request()->ip() || 
            $session['user_agent'] !== request()->userAgent()) {
            return null;
        }

        $user = User::find($session['user_id']);
        
        if (!$user || !$this->verifyToken($user, $authToken)) {
            return null;
        }

        // Clear pending session
        Cache::forget("2fa_pending:{$sessionToken}");

        return $user;
    }

    /**
     * Clear pending session
     */
    protected function clearPendingSession(User $user): void
    {
        // Clear all pending sessions for user
        // This is a simplified implementation
        // In production, you'd want to track sessions per user
    }

    /**
     * Check if token was recently used
     */
    protected function isTokenRecentlyUsed(User $user, string $token): bool
    {
        $key = "2fa_used:{$user->id}:{$token}";
        
        return Cache::has($key);
    }

    /**
     * Mark token as used
     */
    protected function markTokenAsUsed(User $user, string $token): void
    {
        $key = "2fa_used:{$user->id}:{$token}";
        
        // Tokens are valid for 30 seconds, so cache for 60 seconds
        Cache::put($key, true, 60);
    }

    /**
     * Check if user should be prompted for 2FA
     */
    public function shouldPrompt2FA(User $user): bool
    {
        if (!$user->two_factor_enabled) {
            return false;
        }

        // Check if user has a trusted device
        if ($this->isTrustedDevice($user)) {
            return false;
        }

        return true;
    }

    /**
     * Check if current device is trusted
     */
    public function isTrustedDevice(User $user): bool
    {
        $deviceId = $this->getDeviceId();
        $key = "trusted_device:{$user->id}:{$deviceId}";
        
        return Cache::has($key);
    }

    /**
     * Trust current device
     */
    public function trustDevice(User $user, int $days = 30): void
    {
        $deviceId = $this->getDeviceId();
        $key = "trusted_device:{$user->id}:{$deviceId}";
        
        Cache::put($key, [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'trusted_at' => now(),
        ], now()->addDays($days));

        // Log security event
        activity()
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties(['device_id' => $deviceId, 'days' => $days])
            ->log('Device trusted for 2FA');
    }

    /**
     * Get device ID based on user agent and IP
     */
    protected function getDeviceId(): string
    {
        return hash('sha256', request()->userAgent() . request()->ip());
    }
}