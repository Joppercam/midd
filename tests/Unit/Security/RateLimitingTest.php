<?php

namespace Tests\Unit\Security;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_limits_login_attempts()
    {
        $attempts = 0;
        
        // Make 5 attempts (the limit)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password'
            ]);
            
            if ($response->status() !== 429) {
                $attempts++;
            }
        }
        
        $this->assertEquals(5, $attempts);
        
        // 6th attempt should be blocked
        $response = $this->postJson('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password'
        ]);
        
        $response->assertStatus(429);
        $response->assertHeader('X-RateLimit-Limit', '5');
        $response->assertHeader('X-RateLimit-Remaining', '0');
    }

    /** @test */
    public function it_limits_api_requests()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Make 60 requests (the limit)
        for ($i = 0; $i < 60; $i++) {
            $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson('/api/v1/customers');
        }
        
        // 61st request should be blocked
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/customers');
        
        $response->assertStatus(429);
    }

    /** @test */
    public function it_limits_password_reset_attempts()
    {
        $attempts = 0;
        
        // Make 3 attempts (the limit)
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/forgot-password', [
                'email' => 'test@example.com'
            ]);
            
            if ($response->status() !== 429) {
                $attempts++;
            }
        }
        
        $this->assertEquals(3, $attempts);
        
        // 4th attempt should be blocked
        $response = $this->postJson('/forgot-password', [
            'email' => 'test@example.com'
        ]);
        
        $response->assertStatus(429);
    }

    /** @test */
    public function it_uses_different_limits_for_different_endpoints()
    {
        // Auth endpoint has 5 attempts
        $authKey = 'auth:' . request()->ip();
        RateLimiter::hit($authKey, 60);
        $this->assertEquals(4, RateLimiter::remaining($authKey, 5));
        
        // API endpoint has 60 attempts
        $apiKey = 'api:' . request()->ip();
        RateLimiter::hit($apiKey, 60);
        $this->assertEquals(59, RateLimiter::remaining($apiKey, 60));
        
        // Password reset has 3 attempts
        $resetKey = 'password-reset:' . request()->ip();
        RateLimiter::hit($resetKey, 900); // 15 minutes
        $this->assertEquals(2, RateLimiter::remaining($resetKey, 3));
    }

    /** @test */
    public function it_tracks_attempts_per_user_when_authenticated()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        // User 1 makes requests
        $this->actingAs($user1);
        $key1 = 'api:authenticated:' . $user1->id;
        RateLimiter::hit($key1, 60);
        
        // User 2 should have full limit available
        $this->actingAs($user2);
        $key2 = 'api:authenticated:' . $user2->id;
        $this->assertEquals(60, RateLimiter::remaining($key2, 60));
    }

    /** @test */
    public function it_includes_retry_after_header_when_rate_limited()
    {
        // Exhaust the limit
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password'
            ]);
        }
        
        $response->assertStatus(429);
        $response->assertJsonStructure(['message', 'retry_after']);
        
        $retryAfter = $response->json('retry_after');
        $this->assertIsInt($retryAfter);
        $this->assertGreaterThan(0, $retryAfter);
    }
}