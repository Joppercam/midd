<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Events\RealTimeNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RealTimeNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_can_send_test_notification()
    {
        Event::fake();

        $response = $this->postJson('/notifications/test');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Notificación de prueba enviada'
        ]);

        Event::assertDispatched(RealTimeNotification::class);
    }

    /** @test */
    public function it_can_send_tenant_notification()
    {
        Event::fake();

        $data = [
            'title' => 'Test Notification',
            'message' => 'This is a test message',
            'type' => 'info'
        ];

        $response = $this->postJson('/notifications/tenant', $data);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Notificación enviada a toda la empresa'
        ]);

        Event::assertDispatched(RealTimeNotification::class, function ($event) use ($data) {
            return $event->type === $data['type'] &&
                   $event->data['title'] === $data['title'];
        });
    }

    /** @test */
    public function it_validates_tenant_notification_data()
    {
        $response = $this->postJson('/notifications/tenant', [
            'title' => '', // Campo requerido vacío
            'message' => 'Test message',
            'type' => 'invalid_type' // Tipo inválido
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'type']);
    }

    /** @test */
    public function it_can_simulate_invoice_notification()
    {
        Event::fake();

        $response = $this->postJson('/notifications/simulate/invoice');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Notificación de factura simulada'
        ]);

        Event::assertDispatched(RealTimeNotification::class, function ($event) {
            return $event->type === 'invoice.created' &&
                   isset($event->data['invoice']);
        });
    }

    /** @test */
    public function it_can_simulate_payment_notification()
    {
        Event::fake();

        $response = $this->postJson('/notifications/simulate/payment');

        $response->assertOk();
        Event::assertDispatched(RealTimeNotification::class, function ($event) {
            return $event->type === 'payment.received';
        });
    }

    /** @test */
    public function it_can_simulate_low_stock_alert()
    {
        Event::fake();

        $response = $this->postJson('/notifications/simulate/low-stock');

        $response->assertOk();
        Event::assertDispatched(RealTimeNotification::class, function ($event) {
            return $event->type === 'inventory.low_stock';
        });
    }

    /** @test */
    public function it_can_simulate_bank_reconciliation()
    {
        Event::fake();

        $response = $this->postJson('/notifications/simulate/bank-reconciliation');

        $response->assertOk();
        Event::assertDispatched(RealTimeNotification::class, function ($event) {
            return $event->type === 'bank.reconciliation';
        });
    }

    /** @test */
    public function it_can_simulate_system_alert()
    {
        Event::fake();

        $response = $this->postJson('/notifications/simulate/system-alert');

        $response->assertOk();
        Event::assertDispatched(RealTimeNotification::class, function ($event) {
            return $event->type === 'system.alert';
        });
    }

    /** @test */
    public function it_can_get_configuration()
    {
        $response = $this->getJson('/notifications/config');

        $response->assertOk();
        $response->assertJsonStructure([
            'reverb' => [
                'key',
                'host',
                'port',
                'scheme'
            ],
            'user' => [
                'id',
                'name',
                'email'
            ],
            'tenant' => [
                'id',
                'name'
            ]
        ]);
    }

    /** @test */
    public function it_can_access_demo_page()
    {
        $response = $this->get('/notifications/demo');

        $response->assertOk();
        // Verificar que se renderiza la página de Inertia correcta
        $response->assertInertia(fn ($page) => $page->component('Notifications/Demo'));
    }

    /** @test */
    public function it_requires_authentication_for_all_endpoints()
    {
        auth()->logout();

        $endpoints = [
            'POST /notifications/test',
            'POST /notifications/tenant',
            'POST /notifications/simulate/invoice',
            'POST /notifications/simulate/payment',
            'POST /notifications/simulate/low-stock',
            'POST /notifications/simulate/bank-reconciliation',
            'POST /notifications/simulate/system-alert',
            'GET /notifications/config',
            'GET /notifications/demo'
        ];

        foreach ($endpoints as $endpoint) {
            [$method, $url] = explode(' ', $endpoint);
            
            $response = $this->json($method, $url);
            $response->assertUnauthorized();
        }
    }

    /** @test */
    public function it_includes_correct_data_structure_in_notifications()
    {
        Event::fake();

        $this->postJson('/notifications/simulate/invoice');

        Event::assertDispatched(RealTimeNotification::class, function ($event) {
            return isset($event->data['title']) &&
                   isset($event->data['message']) &&
                   isset($event->data['icon']) &&
                   isset($event->data['color']) &&
                   isset($event->data['invoice']);
        });
    }

    /** @test */
    public function it_uses_correct_tenant_context()
    {
        Event::fake();

        $this->postJson('/notifications/test');

        Event::assertDispatched(RealTimeNotification::class, function ($event) {
            return $event->tenantId === $this->user->tenant_id;
        });
    }

    /** @test */
    public function it_generates_random_data_for_simulations()
    {
        Event::fake();

        // Hacer múltiples requests para verificar que los datos son diferentes
        $this->postJson('/notifications/simulate/invoice');
        $this->postJson('/notifications/simulate/invoice');

        Event::assertDispatchedTimes(RealTimeNotification::class, 2);
        
        // Los números de factura deberían ser diferentes
        $notifications = Event::dispatched(RealTimeNotification::class);
        $invoice1 = $notifications[0][0]->data['invoice']['number'];
        $invoice2 = $notifications[1][0]->data['invoice']['number'];
        
        $this->assertNotEquals($invoice1, $invoice2);
    }

    /** @test */
    public function it_handles_different_notification_types_correctly()
    {
        Event::fake();

        $types = ['info', 'success', 'warning', 'error'];
        
        foreach ($types as $type) {
            $this->postJson('/notifications/tenant', [
                'title' => "Test {$type}",
                'message' => "Message for {$type}",
                'type' => $type
            ]);
        }

        Event::assertDispatchedTimes(RealTimeNotification::class, 4);
    }
}