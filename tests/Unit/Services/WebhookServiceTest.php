<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\WebhookService;
use App\Models\Webhook;
use App\Models\WebhookCall;
use App\Models\Tenant;
use App\Models\User;
use App\Models\TaxDocument;
use App\Models\Payment;
use App\Jobs\ProcessWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new WebhookService();
        
        // Create tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Set tenant context
        app()->instance('currentTenant', $this->tenant);
        
        // Fake queue
        Queue::fake();
    }

    /** @test */
    public function it_can_create_webhook()
    {
        $data = [
            'url' => 'https://example.com/webhook',
            'events' => ['invoice.created', 'payment.received'],
            'description' => 'Test webhook',
            'headers' => ['X-Custom-Header' => 'test-value']
        ];
        
        $webhook = $this->service->create($data);
        
        $this->assertInstanceOf(Webhook::class, $webhook);
        $this->assertEquals($this->tenant->id, $webhook->tenant_id);
        $this->assertEquals($data['url'], $webhook->url);
        $this->assertContains('invoice.created', $webhook->events);
        $this->assertContains('payment.received', $webhook->events);
        $this->assertNotNull($webhook->secret);
        $this->assertTrue($webhook->is_active);
    }

    /** @test */
    public function it_generates_unique_secret_for_each_webhook()
    {
        $webhook1 = $this->service->create(['url' => 'https://example1.com']);
        $webhook2 = $this->service->create(['url' => 'https://example2.com']);
        
        $this->assertNotEquals($webhook1->secret, $webhook2->secret);
        $this->assertEquals(32, strlen($webhook1->secret));
        $this->assertEquals(32, strlen($webhook2->secret));
    }

    /** @test */
    public function it_can_trigger_webhook_for_matching_event()
    {
        // Create webhook
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'events' => ['invoice.created'],
            'is_active' => true
        ]);
        
        // Create invoice
        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice'
        ]);
        
        // Trigger webhook
        $this->service->trigger('invoice.created', [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total_amount
        ]);
        
        // Assert job was dispatched
        Queue::assertPushed(ProcessWebhookJob::class, function ($job) use ($webhook) {
            return $job->webhook->id === $webhook->id;
        });
    }

    /** @test */
    public function it_does_not_trigger_inactive_webhooks()
    {
        // Create inactive webhook
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'events' => ['invoice.created'],
            'is_active' => false
        ]);
        
        // Trigger webhook
        $this->service->trigger('invoice.created', ['test' => 'data']);
        
        // Assert job was not dispatched
        Queue::assertNotPushed(ProcessWebhookJob::class);
    }

    /** @test */
    public function it_only_triggers_webhooks_for_subscribed_events()
    {
        // Create webhooks with different events
        $webhook1 = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'events' => ['invoice.created'],
            'is_active' => true
        ]);
        
        $webhook2 = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'events' => ['payment.received'],
            'is_active' => true
        ]);
        
        // Trigger invoice event
        $this->service->trigger('invoice.created', ['test' => 'data']);
        
        // Assert only webhook1 was triggered
        Queue::assertPushed(ProcessWebhookJob::class, 1);
        Queue::assertPushed(ProcessWebhookJob::class, function ($job) use ($webhook1) {
            return $job->webhook->id === $webhook1->id;
        });
    }

    /** @test */
    public function it_can_call_webhook_endpoint()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['success' => true], 200)
        ]);
        
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://example.com/webhook',
            'secret' => 'test-secret'
        ]);
        
        $payload = ['event' => 'test.event', 'data' => ['id' => 123]];
        
        $result = $this->service->call($webhook, $payload);
        
        $this->assertInstanceOf(WebhookCall::class, $result);
        $this->assertEquals(200, $result->response_code);
        $this->assertEquals('completed', $result->status);
        $this->assertEquals($payload, $result->payload);
        
        // Verify signature was sent
        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Webhook-Signature') &&
                   $request->hasHeader('X-Webhook-Timestamp');
        });
    }

    /** @test */
    public function it_handles_webhook_call_failure()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response('Server Error', 500)
        ]);
        
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://example.com/webhook'
        ]);
        
        $result = $this->service->call($webhook, ['test' => 'data']);
        
        $this->assertEquals(500, $result->response_code);
        $this->assertEquals('failed', $result->status);
        $this->assertNotNull($result->error_message);
    }

    /** @test */
    public function it_handles_webhook_timeout()
    {
        Http::fake([
            'https://example.com/webhook' => Http::timeout()
        ]);
        
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://example.com/webhook'
        ]);
        
        $result = $this->service->call($webhook, ['test' => 'data']);
        
        $this->assertEquals(0, $result->response_code);
        $this->assertEquals('failed', $result->status);
        $this->assertStringContains('timeout', strtolower($result->error_message));
    }

    /** @test */
    public function it_includes_custom_headers_in_webhook_call()
    {
        Http::fake();
        
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://example.com/webhook',
            'headers' => [
                'X-Custom-Header' => 'custom-value',
                'Authorization' => 'Bearer token123'
            ]
        ]);
        
        $this->service->call($webhook, ['test' => 'data']);
        
        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Custom-Header', 'custom-value') &&
                   $request->hasHeader('Authorization', 'Bearer token123');
        });
    }

    /** @test */
    public function it_can_verify_webhook_signature()
    {
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'secret' => 'test-secret'
        ]);
        
        $payload = ['test' => 'data'];
        $timestamp = time();
        $payloadString = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp . '.' . $payloadString, 'test-secret');
        
        $isValid = $this->service->verifySignature(
            $webhook,
            $payloadString,
            $signature,
            $timestamp
        );
        
        $this->assertTrue($isValid);
        
        // Test with invalid signature
        $isValid = $this->service->verifySignature(
            $webhook,
            $payloadString,
            'invalid-signature',
            $timestamp
        );
        
        $this->assertFalse($isValid);
    }

    /** @test */
    public function it_prevents_replay_attacks_with_old_timestamps()
    {
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'secret' => 'test-secret'
        ]);
        
        $payload = ['test' => 'data'];
        $oldTimestamp = time() - 3600; // 1 hour ago
        $payloadString = json_encode($payload);
        $signature = hash_hmac('sha256', $oldTimestamp . '.' . $payloadString, 'test-secret');
        
        $isValid = $this->service->verifySignature(
            $webhook,
            $payloadString,
            $signature,
            $oldTimestamp
        );
        
        $this->assertFalse($isValid);
    }

    /** @test */
    public function it_can_get_webhook_statistics()
    {
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create webhook calls with different statuses
        WebhookCall::factory()->count(5)->create([
            'webhook_id' => $webhook->id,
            'status' => 'completed',
            'response_code' => 200
        ]);
        
        WebhookCall::factory()->count(2)->create([
            'webhook_id' => $webhook->id,
            'status' => 'failed',
            'response_code' => 500
        ]);
        
        $stats = $this->service->getStatistics($webhook->id);
        
        $this->assertArrayHasKey('total_calls', $stats);
        $this->assertArrayHasKey('successful_calls', $stats);
        $this->assertArrayHasKey('failed_calls', $stats);
        $this->assertArrayHasKey('success_rate', $stats);
        $this->assertArrayHasKey('average_response_time', $stats);
        
        $this->assertEquals(7, $stats['total_calls']);
        $this->assertEquals(5, $stats['successful_calls']);
        $this->assertEquals(2, $stats['failed_calls']);
        $this->assertEquals(71.43, $stats['success_rate']);
    }

    /** @test */
    public function it_can_retry_failed_webhook_calls()
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['success' => true], 200)
        ]);
        
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://example.com/webhook'
        ]);
        
        // Create failed webhook call
        $failedCall = WebhookCall::factory()->create([
            'webhook_id' => $webhook->id,
            'status' => 'failed',
            'response_code' => 500,
            'payload' => ['test' => 'data']
        ]);
        
        // Retry the call
        $result = $this->service->retry($failedCall->id);
        
        $this->assertTrue($result);
        
        // Check new call was created
        $retriedCall = WebhookCall::where('webhook_id', $webhook->id)
            ->where('id', '!=', $failedCall->id)
            ->first();
        
        $this->assertNotNull($retriedCall);
        $this->assertEquals(200, $retriedCall->response_code);
        $this->assertEquals('completed', $retriedCall->status);
    }

    /** @test */
    public function it_can_deactivate_webhook_after_consecutive_failures()
    {
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'consecutive_failures' => 4 // One more failure will deactivate
        ]);
        
        // Simulate a failed call
        WebhookCall::factory()->create([
            'webhook_id' => $webhook->id,
            'status' => 'failed'
        ]);
        
        $this->service->handleFailure($webhook);
        
        $webhook->refresh();
        $this->assertEquals(5, $webhook->consecutive_failures);
        $this->assertFalse($webhook->is_active); // Should be deactivated
    }

    /** @test */
    public function it_resets_failure_count_on_success()
    {
        $webhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'consecutive_failures' => 3
        ]);
        
        $this->service->handleSuccess($webhook);
        
        $webhook->refresh();
        $this->assertEquals(0, $webhook->consecutive_failures);
    }

    /** @test */
    public function it_respects_tenant_isolation_when_triggering_webhooks()
    {
        // Create webhook for another tenant
        $otherTenant = Tenant::factory()->create();
        $otherWebhook = Webhook::factory()->create([
            'tenant_id' => $otherTenant->id,
            'events' => ['test.event'],
            'is_active' => true
        ]);
        
        // Create webhook for current tenant
        $currentWebhook = Webhook::factory()->create([
            'tenant_id' => $this->tenant->id,
            'events' => ['test.event'],
            'is_active' => true
        ]);
        
        // Trigger event
        $this->service->trigger('test.event', ['data' => 'test']);
        
        // Only current tenant's webhook should be triggered
        Queue::assertPushed(ProcessWebhookJob::class, 1);
        Queue::assertPushed(ProcessWebhookJob::class, function ($job) use ($currentWebhook) {
            return $job->webhook->id === $currentWebhook->id;
        });
    }

    /** @test */
    public function it_can_batch_trigger_webhooks()
    {
        // Create multiple webhooks
        $webhooks = Webhook::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'events' => ['batch.event'],
            'is_active' => true
        ]);
        
        // Batch trigger
        $this->service->batchTrigger('batch.event', [
            ['id' => 1, 'data' => 'item1'],
            ['id' => 2, 'data' => 'item2'],
            ['id' => 3, 'data' => 'item3']
        ]);
        
        // Each webhook should receive all items
        Queue::assertPushed(ProcessWebhookJob::class, 3);
    }

    /** @test */
    public function it_can_list_available_webhook_events()
    {
        $events = $this->service->getAvailableEvents();
        
        $this->assertIsArray($events);
        $this->assertArrayHasKey('invoice.created', $events);
        $this->assertArrayHasKey('invoice.updated', $events);
        $this->assertArrayHasKey('payment.received', $events);
        $this->assertArrayHasKey('customer.created', $events);
        
        // Each event should have a description
        foreach ($events as $event => $description) {
            $this->assertIsString($description);
            $this->assertNotEmpty($description);
        }
    }
}