<?php

namespace Tests\Unit\Security;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Security\TwoFactorAuthService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    protected TwoFactorAuthService $twoFactorService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->twoFactorService = new TwoFactorAuthService();
    }

    /** @test */
    public function it_can_generate_secret_key()
    {
        $secret = $this->twoFactorService->generateSecretKey();
        
        $this->assertIsString($secret);
        $this->assertGreaterThanOrEqual(16, strlen($secret));
    }

    /** @test */
    public function it_can_enable_two_factor_for_user()
    {
        $user = User::factory()->create();
        $secret = $this->twoFactorService->generateSecretKey();
        $user->two_factor_secret = $secret;
        $user->save();
        
        // Generate a valid token (this would normally come from the authenticator app)
        // For testing, we'll mock the verification
        $this->mock(TwoFactorAuthService::class, function ($mock) {
            $mock->shouldReceive('verifyToken')->once()->andReturn(true);
            $mock->shouldReceive('generateRecoveryCodes')->once()->andReturn([]);
        });
        
        $service = app(TwoFactorAuthService::class);
        $result = $service->enable($user, '123456');
        
        $this->assertTrue($result);
        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
        $this->assertNotNull($user->two_factor_enabled_at);
    }

    /** @test */
    public function it_can_disable_two_factor_with_correct_password()
    {
        $password = 'password123';
        $user = User::factory()->create([
            'password' => bcrypt($password),
            'two_factor_enabled' => true,
            'two_factor_secret' => 'secret',
            'two_factor_recovery_codes' => ['CODE1', 'CODE2'],
            'two_factor_enabled_at' => now(),
        ]);
        
        $result = $this->twoFactorService->disable($user, $password);
        
        $this->assertTrue($result);
        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
    }

    /** @test */
    public function it_cannot_disable_two_factor_with_wrong_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'two_factor_enabled' => true,
            'two_factor_secret' => 'secret',
        ]);
        
        $result = $this->twoFactorService->disable($user, 'wrong-password');
        
        $this->assertFalse($result);
        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
    }

    /** @test */
    public function it_generates_recovery_codes()
    {
        $user = User::factory()->create();
        
        $codes = $this->twoFactorService->generateRecoveryCodes($user);
        
        $this->assertCount(8, $codes);
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);
        }
        
        $user->refresh();
        $this->assertEquals($codes, $user->two_factor_recovery_codes);
    }

    /** @test */
    public function it_can_verify_recovery_code()
    {
        $recoveryCodes = ['CODE1-CODE1', 'CODE2-CODE2', 'CODE3-CODE3'];
        $user = User::factory()->create([
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);
        
        $result = $this->twoFactorService->verifyRecoveryCode($user, 'CODE2-CODE2');
        
        $this->assertTrue($result);
        $user->refresh();
        $this->assertNotContains('CODE2-CODE2', $user->two_factor_recovery_codes);
        $this->assertCount(2, $user->two_factor_recovery_codes);
    }

    /** @test */
    public function it_cannot_reuse_recovery_code()
    {
        $recoveryCodes = ['CODE1-CODE1', 'CODE2-CODE2'];
        $user = User::factory()->create([
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);
        
        // Use the code once
        $this->twoFactorService->verifyRecoveryCode($user, 'CODE1-CODE1');
        
        // Try to use it again
        $result = $this->twoFactorService->verifyRecoveryCode($user, 'CODE1-CODE1');
        
        $this->assertFalse($result);
    }

    /** @test */
    public function it_creates_pending_session()
    {
        $user = User::factory()->create();
        
        $token = $this->twoFactorService->createPendingSession($user);
        
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
        
        $session = Cache::get("2fa_pending:{$token}");
        $this->assertNotNull($session);
        $this->assertEquals($user->id, $session['user_id']);
    }

    /** @test */
    public function it_prevents_token_reuse_within_time_window()
    {
        $user = User::factory()->create([
            'two_factor_secret' => 'secret',
        ]);
        
        // Mock successful verification
        $this->mock(TwoFactorAuthService::class, function ($mock) use ($user) {
            $mock->makePartial();
            $mock->shouldReceive('verifyToken')
                ->with($user, '123456')
                ->once()
                ->andReturnUsing(function () use ($mock, $user) {
                    $mock->markTokenAsUsed($user, '123456');
                    return true;
                });
            
            $mock->shouldReceive('isTokenRecentlyUsed')
                ->with($user, '123456')
                ->andReturnUsing(function () use ($user) {
                    return Cache::has("2fa_used:{$user->id}:123456");
                });
        });
        
        $service = app(TwoFactorAuthService::class);
        
        // First use should succeed
        $result1 = $service->verifyToken($user, '123456');
        $this->assertTrue($result1);
        
        // Second use should fail (token recently used)
        $result2 = $service->verifyToken($user, '123456');
        $this->assertFalse($result2);
    }

    /** @test */
    public function it_checks_if_user_should_be_prompted_for_2fa()
    {
        $userWith2FA = User::factory()->create([
            'two_factor_enabled' => true,
        ]);
        
        $userWithout2FA = User::factory()->create([
            'two_factor_enabled' => false,
        ]);
        
        $this->assertTrue($this->twoFactorService->shouldPrompt2FA($userWith2FA));
        $this->assertFalse($this->twoFactorService->shouldPrompt2FA($userWithout2FA));
    }

    /** @test */
    public function it_can_trust_device()
    {
        $user = User::factory()->create();
        
        $this->assertFalse($this->twoFactorService->isTrustedDevice($user));
        
        $this->twoFactorService->trustDevice($user, 30);
        
        $this->assertTrue($this->twoFactorService->isTrustedDevice($user));
    }

    /** @test */
    public function it_does_not_prompt_2fa_for_trusted_devices()
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
        ]);
        
        $this->twoFactorService->trustDevice($user);
        
        $this->assertFalse($this->twoFactorService->shouldPrompt2FA($user));
    }
}