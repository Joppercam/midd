<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\ProcessWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookCall;
use App\Models\Tenant;
use App\Models\TaxDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

class ProcessWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    protected $webhook;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://example.com/webhook',
            'events' => ['invoice.created', 'invoice.paid'],
            'is_active' => true,
        ]);
        
        Queue::fake();
        Http::fake();
    }

    /** @test */
    public function it_processes_webhook_successfully()
    {
        Http::fake([
            'example.com/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $payload = [
            'event' => 'invoice.created',
            'data' => [
                'id' => 123,
                'total' => 100000,
            ],
        ];

        $job = new ProcessWebhookJob($this->webhook, $payload);
        $job->handle();

        // Verificar que se creó el registro de llamada
        $this->assertDatabaseHas('webhook_calls', [
            'webhook_id' => $this->webhook->id,
            'event' => 'invoice.created',
            'status' => 'success',
            'response_code' => 200,
        ]);

        // Verificar que se hizo la petición HTTP
        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/webhook' &&
                   $request->hasHeader('X-Webhook-Signature') &&
                   $request['event'] === 'invoice.created';
        });
    }

    /** @test */
    public function it_handles_webhook_failure_and_retries()
    {
        Http::fake([
            'example.com/*' => Http::response('Server Error', 500),
        ]);

        $payload = [
            'event' => 'invoice.created',
            'data' => ['id' => 123],
        ];

        $job = new ProcessWebhookJob($this->webhook, $payload);
        
        $this->expectException(\Exception::class);
        $job->handle();

        // Verificar que se registró el fallo
        $this->assertDatabaseHas('webhook_calls', [
            'webhook_id' => $this->webhook->id,
            'status' => 'failed',
            'response_code' => 500,
        ]);
    }

    /** @test */
    public function it_includes_correct_headers_in_webhook_request()
    {
        Http::fake();

        $payload = [
            'event' => 'invoice.paid',
            'data' => ['id' => 123],
        ];

        $job = new ProcessWebhookJob($this->webhook, $payload);
        $job->handle();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Content-Type', 'application/json') &&
                   $request->hasHeader('X-Webhook-Event', 'invoice.paid') &&
                   $request->hasHeader('X-Webhook-ID') &&
                   $request->hasHeader('X-Webhook-Timestamp') &&
                   $request->hasHeader('X-Webhook-Signature');
        });
    }

    /** @test */
    public function it_generates_correct_hmac_signature()
    {
        Http::fake();

        $payload = [
            'event' => 'test.event',
            'data' => ['test' => 'data'],
        ];

        $job = new ProcessWebhookJob($this->webhook, $payload);
        $job->handle();

        Http::assertSent(function ($request) use ($payload) {
            $timestamp = $request->header('X-Webhook-Timestamp')[0];
            $body = json_encode($payload);
            $expectedSignature = hash_hmac(
                'sha256',
                $timestamp . '.' . $body,
                $this->webhook->secret
            );
            
            return $request->header('X-Webhook-Signature')[0] === $expectedSignature;
        });
    }

    /** @test */
    public function it_respects_webhook_retry_configuration()
    {
        Http::fake([
            'example.com/*' => Http::sequence()
                ->push('Server Error', 500)
                ->push('Server Error', 500)
                ->push(['status' => 'ok'], 200),
        ]);

        $this->webhook->update(['retry_times' => 3]);

        $payload = ['event' => 'test.event', 'data' => []];
        $job = new ProcessWebhookJob($this->webhook, $payload);
        
        // Simular reintentos
        try {
            $job->handle();
        } catch (\Exception $e) {
            // Primer intento falla
        }
        
        try {
            $job->handle();
        } catch (\Exception $e) {
            // Segundo intento falla
        }
        
        // Tercer intento exitoso
        $job->handle();

        $calls = WebhookCall::where('webhook_id', $this->webhook->id)->get();
        $this->assertCount(3, $calls);
        $this->assertEquals('success', $calls->last()->status);
    }

    /** @test */
    public function it_disables_webhook_after_max_consecutive_failures()
    {
        Http::fake([
            'example.com/*' => Http::response('Server Error', 500),
        ]);

        // Simular múltiples fallos consecutivos
        for ($i = 0; $i < 10; $i++) {
            WebhookCall::factory()->create([
                'webhook_id' => $this->webhook->id,
                'status' => 'failed',
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $payload = ['event' => 'test.event', 'data' => []];
        $job = new ProcessWebhookJob($this->webhook, $payload);
        
        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected
        }

        // Verificar que el webhook fue desactivado
        $this->assertFalse($this->webhook->fresh()->is_active);
    }

    /** @test */
    public function it_logs_webhook_processing()
    {
        Http::fake([
            'example.com/*' => Http::response(['status' => 'ok'], 200),
        ]);

        Log::shouldReceive('channel')
            ->with('webhooks')
            ->twice()
            ->andReturnSelf();
        
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Processing webhook') &&
                       isset($context['webhook_id']) &&
                       isset($context['event']);
            });
        
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Webhook processed successfully') &&
                       isset($context['webhook_id']) &&
                       isset($context['response_code']);
            });

        $payload = ['event' => 'test.event', 'data' => []];
        $job = new ProcessWebhookJob($this->webhook, $payload);
        $job->handle();
    }

    /** @test */
    public function it_handles_timeout_correctly()
    {
        Http::fake(function ($request) {
            sleep(35); // Simular timeout
            return Http::response(['status' => 'ok'], 200);
        });

        $payload = ['event' => 'test.event', 'data' => []];
        $job = new ProcessWebhookJob($this->webhook, $payload);
        $job->timeout = 30;
        
        $this->expectException(\Exception::class);
        $job->handle();

        $this->assertDatabaseHas('webhook_calls', [
            'webhook_id' => $this->webhook->id,
            'status' => 'failed',
            'error_message' => 'Request timeout',
        ]);
    }

    /** @test */
    public function it_validates_webhook_payload_size()
    {
        $largePayload = [
            'event' => 'test.event',
            'data' => str_repeat('x', 1024 * 1024 * 2), // 2MB
        ];

        $job = new ProcessWebhookJob($this->webhook, $largePayload);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payload too large');
        
        $job->handle();
    }

    /** @test */
    public function it_filters_sensitive_data_from_payload()
    {
        Http::fake();

        $payload = [
            'event' => 'invoice.created',
            'data' => [
                'id' => 123,
                'customer' => [
                    'name' => 'John Doe',
                    'password' => 'secret123',
                    'credit_card' => '1234-5678-9012-3456',
                    'api_key' => 'sk_test_123',
                ],
            ],
        ];

        $job = new ProcessWebhookJob($this->webhook, $payload);
        $job->handle();

        Http::assertSent(function ($request) {
            $data = $request->data();
            return !isset($data['data']['customer']['password']) &&
                   !isset($data['data']['customer']['credit_card']) &&
                   !isset($data['data']['customer']['api_key']);
        });
    }
}