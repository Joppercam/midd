<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\EmailNotificationService;
use App\Models\EmailNotification;
use App\Models\TaxDocument;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Mail\InvoiceMail;
use App\Mail\PaymentReminderMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;

class EmailNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EmailNotificationService $emailService;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->emailService = new EmailNotificationService();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'customer@example.com',
        ]);
        
        Mail::fake();
        Queue::fake();
    }

    /** @test */
    public function it_sends_invoice_email_to_customer()
    {
        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'total' => 100000,
        ]);

        $notification = $this->emailService->sendInvoice($invoice);

        // Verificar que se creó la notificación
        $this->assertInstanceOf(EmailNotification::class, $notification);
        $this->assertEquals('invoice', $notification->type);
        $this->assertEquals($this->customer->email, $notification->to_email);
        $this->assertEquals($invoice->id, $notification->related_id);
        $this->assertEquals('pending', $notification->status);

        // Verificar que se envió el email
        Mail::assertQueued(InvoiceMail::class, function ($mail) use ($invoice, $customer) {
            return $mail->invoice->id === $invoice->id &&
                   $mail->hasTo($this->customer->email);
        });
    }

    /** @test */
    public function it_sends_payment_reminder_email()
    {
        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'payment_status' => 'pending',
            'due_date' => now()->subDays(5),
        ]);

        $notification = $this->emailService->sendPaymentReminder($invoice);

        $this->assertInstanceOf(EmailNotification::class, $notification);
        $this->assertEquals('payment_reminder', $notification->type);
        $this->assertStringContainsString('5 días de vencida', $notification->subject);
        
        Mail::assertQueued(PaymentReminderMail::class);
    }

    /** @test */
    public function it_tracks_email_open()
    {
        $notification = EmailNotification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'sent',
        ]);

        $this->emailService->trackOpen($notification);

        $notification->refresh();
        $this->assertEquals('opened', $notification->status);
        $this->assertNotNull($notification->opened_at);
        $this->assertEquals(1, $notification->open_count);
    }

    /** @test */
    public function it_increments_open_count_on_multiple_opens()
    {
        $notification = EmailNotification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'opened',
            'open_count' => 3,
        ]);

        $this->emailService->trackOpen($notification);

        $notification->refresh();
        $this->assertEquals(4, $notification->open_count);
    }

    /** @test */
    public function it_marks_email_as_clicked()
    {
        $notification = EmailNotification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'sent',
        ]);

        $this->emailService->trackClick($notification, 'https://example.com/invoice/123');

        $notification->refresh();
        $this->assertEquals('clicked', $notification->status);
        $this->assertNotNull($notification->clicked_at);
        $this->assertArrayHasKey('clicked_links', $notification->metadata);
    }

    /** @test */
    public function it_handles_email_bounce()
    {
        $notification = EmailNotification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'sent',
        ]);

        $this->emailService->handleBounce($notification, 'Hard bounce: Invalid email address');

        $notification->refresh();
        $this->assertEquals('bounced', $notification->status);
        $this->assertNotNull($notification->bounced_at);
        $this->assertEquals('Hard bounce: Invalid email address', $notification->error_message);
    }

    /** @test */
    public function it_resends_email_notification()
    {
        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $originalNotification = EmailNotification::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'invoice',
            'related_type' => TaxDocument::class,
            'related_id' => $invoice->id,
            'status' => 'failed',
        ]);

        $newNotification = $this->emailService->resendNotification($originalNotification);

        $this->assertInstanceOf(EmailNotification::class, $newNotification);
        $this->assertNotEquals($originalNotification->id, $newNotification->id);
        $this->assertEquals('pending', $newNotification->status);
        $this->assertEquals($originalNotification->related_id, $newNotification->related_id);
        
        Mail::assertQueued(InvoiceMail::class);
    }

    /** @test */
    public function it_sends_bulk_payment_reminders()
    {
        // Crear varias facturas vencidas
        $overdueInvoices = TaxDocument::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'invoice',
            'payment_status' => 'pending',
            'due_date' => now()->subDays(10),
        ]);

        $results = $this->emailService->sendBulkPaymentReminders($this->tenant->id);

        $this->assertEquals(5, $results['sent']);
        $this->assertEquals(0, $results['failed']);
        $this->assertCount(5, $results['notifications']);
        
        Mail::assertQueued(PaymentReminderMail::class, 5);
    }

    /** @test */
    public function it_gets_email_statistics_for_tenant()
    {
        // Crear varias notificaciones con diferentes estados
        EmailNotification::factory()->count(10)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'sent',
        ]);
        
        EmailNotification::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'opened',
        ]);
        
        EmailNotification::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'clicked',
        ]);
        
        EmailNotification::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'bounced',
        ]);

        $stats = $this->emailService->getEmailStatistics($this->tenant->id, now()->subMonth(), now());

        $this->assertEquals(20, $stats['total_sent']);
        $this->assertEquals(8, $stats['total_opened']); // opened + clicked
        $this->assertEquals(3, $stats['total_clicked']);
        $this->assertEquals(2, $stats['total_bounced']);
        $this->assertEquals(40, $stats['open_rate']); // 8/20 * 100
        $this->assertEquals(15, $stats['click_rate']); // 3/20 * 100
        $this->assertEquals(10, $stats['bounce_rate']); // 2/20 * 100
    }

    /** @test */
    public function it_validates_email_before_sending()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'invalid-email',
        ]);

        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid email address');

        $this->emailService->sendInvoice($invoice);
    }

    /** @test */
    public function it_respects_email_sending_limits()
    {
        // Simular que ya se han enviado muchos emails hoy
        EmailNotification::factory()->count(100)->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now(),
        ]);

        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Daily email limit exceeded');

        $this->emailService->sendInvoice($invoice);
    }

    /** @test */
    public function it_handles_email_templates()
    {
        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $notification = $this->emailService->sendInvoice($invoice, [
            'template' => 'custom_invoice_template',
            'variables' => [
                'custom_message' => 'Thank you for your business!',
            ],
        ]);

        $this->assertArrayHasKey('template', $notification->metadata);
        $this->assertEquals('custom_invoice_template', $notification->metadata['template']);
        $this->assertArrayHasKey('custom_message', $notification->metadata['variables']);
    }

    /** @test */
    public function it_schedules_email_for_future_sending()
    {
        $invoice = TaxDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $scheduledTime = now()->addHours(2);
        
        $notification = $this->emailService->scheduleInvoice($invoice, $scheduledTime);

        $this->assertEquals('scheduled', $notification->status);
        $this->assertEquals($scheduledTime->format('Y-m-d H:i:s'), $notification->scheduled_at->format('Y-m-d H:i:s'));
        
        // Verificar que no se envió inmediatamente
        Mail::assertNothingQueued();
    }

    /** @test */
    public function it_processes_scheduled_emails()
    {
        // Crear emails programados para ser enviados
        $scheduledNotifications = EmailNotification::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinutes(5),
            'type' => 'invoice',
            'related_type' => TaxDocument::class,
        ]);

        $results = $this->emailService->processScheduledEmails();

        $this->assertEquals(3, $results['processed']);
        $this->assertEquals(3, $results['sent']);
        $this->assertEquals(0, $results['failed']);
        
        Mail::assertQueued(InvoiceMail::class, 3);
    }

    /** @test */
    public function it_creates_email_activity_log()
    {
        $notification = EmailNotification::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->emailService->logActivity($notification, 'sent', [
            'smtp_response' => '250 OK',
            'delivery_time' => 1.23,
        ]);

        $this->assertDatabaseHas('email_activities', [
            'email_notification_id' => $notification->id,
            'action' => 'sent',
            'metadata->smtp_response' => '250 OK',
        ]);
    }
}