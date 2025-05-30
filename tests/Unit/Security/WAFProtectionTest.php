<?php

namespace Tests\Unit\Security;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class WAFProtectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_blocks_sql_injection_attempts()
    {
        $sqlInjectionPayloads = [
            "1' OR '1'='1",
            "1; DROP TABLE users--",
            "admin' UNION SELECT * FROM users--",
            "' OR 1=1#",
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            $response = $this->get('/search?q=' . urlencode($payload));
            $response->assertStatus(403);
        }
    }

    /** @test */
    public function it_blocks_xss_attempts()
    {
        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            '<iframe src="javascript:alert(\'XSS\')"></iframe>',
            '<body onload=alert("XSS")>',
        ];

        foreach ($xssPayloads as $payload) {
            $response = $this->postJson('/api/v1/customers', [
                'name' => $payload,
                'email' => 'test@example.com'
            ]);
            
            $response->assertStatus(403);
        }
    }

    /** @test */
    public function it_blocks_path_traversal_attempts()
    {
        $pathTraversalPayloads = [
            '../../etc/passwd',
            '..\\..\\windows\\system32\\config\\sam',
            '/proc/self/environ',
        ];

        foreach ($pathTraversalPayloads as $payload) {
            $response = $this->get('/files/' . $payload);
            $response->assertStatus(403);
        }
    }

    /** @test */
    public function it_blocks_command_injection_attempts()
    {
        $commandInjectionPayloads = [
            'test; ls -la',
            'test | cat /etc/passwd',
            'test`whoami`',
            'test$(id)',
        ];

        foreach ($commandInjectionPayloads as $payload) {
            $response = $this->postJson('/api/v1/reports', [
                'name' => $payload,
                'type' => 'sales'
            ]);
            
            $response->assertStatus(403);
        }
    }

    /** @test */
    public function it_blocks_suspicious_user_agents()
    {
        $suspiciousAgents = [
            'sqlmap/1.0',
            'nikto/2.1.5',
            'nessus/6.0',
            'metasploit/4.0',
        ];

        foreach ($suspiciousAgents as $agent) {
            $response = $this->withHeader('User-Agent', $agent)
                ->get('/');
            
            $response->assertStatus(403);
        }
    }

    /** @test */
    public function it_blocks_dangerous_file_uploads()
    {
        $dangerousExtensions = ['php', 'exe', 'sh', 'bat'];

        foreach ($dangerousExtensions as $ext) {
            $file = \Illuminate\Http\UploadedFile::fake()->create("test.{$ext}", 100);
            
            $response = $this->postJson('/api/v1/uploads', [
                'file' => $file
            ]);
            
            $response->assertStatus(403);
        }
    }

    /** @test */
    public function it_allows_safe_requests()
    {
        // Normal search query
        $response = $this->get('/search?q=normal+search+term');
        $this->assertNotEquals(403, $response->status());

        // Normal form submission
        $response = $this->postJson('/api/v1/customers', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890'
        ]);
        $this->assertNotEquals(403, $response->status());
    }

    /** @test */
    public function it_logs_blocked_requests()
    {
        Log::spy();

        $response = $this->get('/search?q=' . urlencode("1' OR '1'='1"));
        
        Log::shouldHaveReceived('channel')
            ->with('security')
            ->once();
    }

    /** @test */
    public function it_blocks_oversized_requests()
    {
        // Create a large payload (over 10MB)
        $largeData = str_repeat('x', 11 * 1024 * 1024);
        
        $response = $this->withHeader('Content-Length', strlen($largeData))
            ->postJson('/api/v1/data', ['data' => $largeData]);
        
        $response->assertStatus(403);
    }

    /** @test */
    public function it_bans_ip_after_multiple_violations()
    {
        // Make multiple malicious requests
        for ($i = 0; $i < 11; $i++) {
            $this->get('/search?q=' . urlencode("' OR 1=1--"));
        }

        // Next request should be blocked even with safe content
        $response = $this->get('/');
        $response->assertStatus(403);
    }

    /** @test */
    public function it_detects_missing_headers_anomaly()
    {
        $response = $this->withHeaders([
            'User-Agent' => '', // Empty user agent
        ])->get('/api/v1/customers');

        $response->assertStatus(403);
    }

    /** @test */
    public function it_allows_whitelisted_file_types()
    {
        $allowedExtensions = ['jpg', 'pdf', 'csv', 'xlsx'];

        foreach ($allowedExtensions as $ext) {
            $file = \Illuminate\Http\UploadedFile::fake()->create("test.{$ext}", 100);
            
            $response = $this->postJson('/api/v1/uploads', [
                'file' => $file
            ]);
            
            $this->assertNotEquals(403, $response->status());
        }
    }
}