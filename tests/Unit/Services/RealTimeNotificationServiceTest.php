<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\RealTimeNotificationService;
use App\Events\RealTimeNotification;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class RealTimeNotificationServiceTest extends TestCase
{
    private RealTimeNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new RealTimeNotificationService();
    }

    /** @test */
    public function it_can_notify_single_user()
    {
        Event::fake();
        
        $this->service->notifyUser(
            $this->user->id,
            'test',
            ['title' => 'Test', 'message' => 'Test message'],
            $this->tenant->id
        );

        Event::assertDispatched(RealTimeNotification::class, function ($event) {
            return $event->type === 'test' && 
                   $event->userId === $this->user->id &&
                   $event->tenantId === $this->tenant->id;
        });
    }

    /** @test */
    public function it_can_notify_multiple_users()
    {
        Event::fake();
        
        $user2 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $this->service->notifyUsers(
            [$this->user->id, $user2->id],
            'bulk_test',
            ['title' => 'Bulk Test', 'message' => 'Bulk message'],
            $this->tenant->id
        );

        Event::assertDispatchedTimes(RealTimeNotification::class, 2);
    }

    /** @test */
    public function it_can_notify_entire_tenant()
    {
        Event::fake();
        
        $this->service->notifyTenant(
            $this->tenant->id,
            'tenant_wide',
            ['title' => 'Tenant Wide', 'message' => 'All users message']
        );

        Event::assertDispatched(RealTimeNotification::class, function ($event) {
            return $event->type === 'tenant_wide' && 
                   $event->tenantId === $this->tenant->id &&
                   $event->userId === null;
        });
    }

    /** @test */
    public function it_can_notify_new_invoice()
    {
        Event::fake();
        
        $invoiceData = [
            'number' => 'F-0001',
            'total' => '150000',
            'customer' => 'Test Customer',
            'date' => '2025-05-29'
        ];

        $this->service->notifyNewInvoice($this->tenant->id, $invoiceData);

        Event::assertDispatched(RealTimeNotification::class, function ($event) use ($invoiceData) {
            return $event->type === 'invoice.created' && 
                   $event->data['invoice']['number'] === $invoiceData['number'];
        });
    }

    /** @test */
    public function it_can_notify_payment_received()
    {
        Event::fake();
        
        $paymentData = [
            'amount' => '75000',
            'method' => 'Transferencia',
            'customer' => 'Test Customer',
            'date' => '2025-05-29 10:30'
        ];

        $this->service->notifyPaymentReceived($this->tenant->id, $paymentData);

        Event::assertDispatched(RealTimeNotification::class, function ($event) use ($paymentData) {
            return $event->type === 'payment.received' && 
                   $event->data['payment']['amount'] === $paymentData['amount'];
        });
    }

    /** @test */
    public function it_can_notify_low_stock()
    {
        Event::fake();
        
        $productData = [
            'name' => 'Test Product',
            'stock' => 3,
            'min_stock' => 10,
            'sku' => 'TST-001'
        ];

        $this->service->notifyLowStock($this->tenant->id, $productData);

        Event::assertDispatched(RealTimeNotification::class, function ($event) use ($productData) {
            return $event->type === 'inventory.low_stock' && 
                   $event->data['product']['name'] === $productData['name'];
        });
    }

    /** @test */
    public function it_can_notify_bank_reconciliation()
    {
        Event::fake();
        
        $reconciliationData = [
            'bank_account' => 'Banco Chile - CC',
            'status' => 'completed',
            'transactions_matched' => 25,
            'differences' => 0,
            'date' => '2025-05-29'
        ];

        $this->service->notifyBankReconciliation($this->tenant->id, $reconciliationData);

        Event::assertDispatched(RealTimeNotification::class, function ($event) use ($reconciliationData) {
            return $event->type === 'bank.reconciliation' && 
                   $event->data['reconciliation']['status'] === $reconciliationData['status'];
        });
    }

    /** @test */
    public function it_can_notify_system_alert()
    {
        Event::fake();
        
        $alertData = [
            'message' => 'Backup failed',
            'severity' => 'high',
            'timestamp' => now()->toISOString()
        ];

        $this->service->notifySystemAlert($this->tenant->id, 'backup_failed', $alertData);

        Event::assertDispatched(RealTimeNotification::class, function ($event) use ($alertData) {
            return $event->type === 'system.alert' && 
                   $event->data['data']['message'] === $alertData['message'];
        });
    }

    /** @test */
    public function it_can_notify_dashboard_update()
    {
        Event::fake();
        
        $metrics = [
            'revenue' => 500000,
            'invoices' => 25,
            'customers' => 10,
            'cash_flow' => 150000
        ];

        $this->service->notifyDashboardUpdate($this->tenant->id, $metrics);

        Event::assertDispatched(RealTimeNotification::class, function ($event) use ($metrics) {
            return $event->type === 'dashboard.update' && 
                   $event->data['metrics']['revenue'] === $metrics['revenue'];
        });
    }

    /** @test */
    public function it_logs_successful_notifications()
    {
        \Illuminate\Support\Facades\Log::spy();
        Event::fake();
        
        $this->service->notifyUser(
            $this->user->id,
            'test',
            ['title' => 'Test', 'message' => 'Test message'],
            $this->tenant->id
        );

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->once()
            ->with('Real-time notification sent', \Mockery::any());
    }

    /** @test */
    public function it_creates_persistent_notification()
    {
        $data = [
            'title' => 'Persistent Test',
            'message' => 'This notification is persisted'
        ];

        $notification = $this->service->createPersistentNotification(
            $this->user->id,
            'persistent_test',
            $data,
            false // No enviar en tiempo real
        );

        $this->assertDatabaseHas('email_notifications', [
            'id' => $notification->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'subject' => $data['title'],
            'body' => $data['message'],
            'email_type' => 'persistent_test'
        ]);
    }

    /** @test */
    public function it_creates_persistent_notification_with_real_time()
    {
        Event::fake();
        
        $data = [
            'title' => 'Persistent + Real Time',
            'message' => 'Both persistent and real time'
        ];

        $notification = $this->service->createPersistentNotification(
            $this->user->id,
            'both_test',
            $data,
            true // Enviar también en tiempo real
        );

        $this->assertDatabaseHas('email_notifications', [
            'id' => $notification->id,
            'email_type' => 'both_test'
        ]);

        Event::assertDispatched(RealTimeNotification::class);
    }

    /** @test */
    public function it_handles_null_tenant_gracefully()
    {
        Event::fake();
        
        $this->service->notifyUser(
            $this->user->id,
            'test',
            ['title' => 'No Tenant', 'message' => 'Message without tenant'],
            null
        );

        Event::assertDispatched(RealTimeNotification::class, function ($event) {
            return $event->tenantId === null;
        });
    }

    /** @test */
    public function it_includes_correct_notification_structure()
    {
        Event::fake();
        
        $data = [
            'title' => 'Structure Test',
            'message' => 'Testing notification structure',
            'icon' => 'test-icon',
            'color' => 'blue'
        ];

        $this->service->notifyUser($this->user->id, 'structure_test', $data, $this->tenant->id);

        Event::assertDispatched(RealTimeNotification::class, function ($event) use ($data) {
            return $event->data === $data &&
                   isset($event->type) &&
                   isset($event->userId) &&
                   isset($event->tenantId);
        });
    }
}