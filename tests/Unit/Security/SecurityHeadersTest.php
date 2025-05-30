<?php

namespace Tests\Unit\Security;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_includes_xss_protection_header()
    {
        $response = $this->get('/');
        
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
    }

    /** @test */
    public function it_includes_frame_options_header()
    {
        $response = $this->get('/');
        
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    /** @test */
    public function it_includes_content_type_options_header()
    {
        $response = $this->get('/');
        
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** @test */
    public function it_includes_referrer_policy_header()
    {
        $response = $this->get('/');
        
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /** @test */
    public function it_includes_permissions_policy_header()
    {
        $response = $this->get('/');
        
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');
    }

    /** @test */
    public function it_includes_content_security_policy_header()
    {
        $response = $this->get('/');
        
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("style-src 'self'", $csp);
        $this->assertStringContainsString('upgrade-insecure-requests', $csp);
    }

    /** @test */
    public function it_includes_strict_transport_security_for_https()
    {
        // Simulate HTTPS request
        $response = $this->withServerVariables(['HTTPS' => 'on'])
            ->get('/');
        
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
    }

    /** @test */
    public function it_does_not_include_hsts_for_http()
    {
        // Simulate HTTP request
        $response = $this->withServerVariables(['HTTPS' => 'off'])
            ->get('/');
        
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    /** @test */
    public function it_removes_server_identification()
    {
        $response = $this->get('/');
        
        $response->assertHeader('Server', 'CrecePyme');
    }

    /** @test */
    public function it_generates_unique_csp_nonce()
    {
        $response1 = $this->get('/');
        $response2 = $this->get('/');
        
        $csp1 = $response1->headers->get('Content-Security-Policy');
        $csp2 = $response2->headers->get('Content-Security-Policy');
        
        // Extract nonces
        preg_match('/nonce-([^\']+)/', $csp1, $matches1);
        preg_match('/nonce-([^\']+)/', $csp2, $matches2);
        
        $this->assertNotEquals($matches1[1], $matches2[1]);
    }

    /** @test */
    public function it_applies_headers_to_all_responses()
    {
        $routes = [
            '/',
            '/login',
            '/dashboard',
            '/api/v1/customers',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            
            $this->assertTrue($response->headers->has('X-XSS-Protection'));
            $this->assertTrue($response->headers->has('X-Frame-Options'));
            $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        }
    }

    /** @test */
    public function it_stores_csp_nonce_in_request()
    {
        $this->get('/', [], function ($request) {
            $this->assertTrue($request->attributes->has('csp-nonce'));
            $this->assertIsString($request->attributes->get('csp-nonce'));
        });
    }

    /** @test */
    public function it_includes_report_uri_when_configured()
    {
        config(['security.csp_report_uri' => 'https://csp-report.crecepyme.cl']);
        
        $response = $this->get('/');
        
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('report-uri https://csp-report.crecepyme.cl', $csp);
    }
}