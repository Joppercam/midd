<?php

namespace App\Services;

use App\Mail\InvoiceMail;
use App\Mail\PaymentReminderMail;
use App\Models\EmailNotification;
use App\Models\TaxDocument;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

class EmailNotificationService
{
    public function sendInvoice(TaxDocument $invoice, array $options = []): ?EmailNotification
    {
        $recipientEmail = $options['recipient_email'] ?? $invoice->customer->email;
        $attachPdf = $options['attach_pdf'] ?? true;
        $customMessage = $options['custom_message'] ?? null;

        if (!$recipientEmail) {
            throw new \Exception('No se especificó un correo electrónico de destino.');
        }

        // Crear registro de notificación
        $notification = EmailNotification::create([
            'tenant_id' => $invoice->tenant_id,
            'notifiable_type' => TaxDocument::class,
            'notifiable_id' => $invoice->id,
            'email_type' => 'invoice_sent',
            'recipient_email' => $recipientEmail,
            'recipient_name' => $invoice->customer->business_name,
            'subject' => $this->getInvoiceSubject($invoice),
            'body' => $customMessage,
            'status' => 'pending'
        ]);

        try {
            // Generar PDF si es necesario
            $pdfContent = null;
            if ($attachPdf) {
                $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $invoice]);
                $pdfContent = $pdf->output();
                
                $notification->attachments = json_encode([
                    [
                        'name' => $this->getInvoiceFileName($invoice),
                        'mime' => 'application/pdf',
                        'size' => strlen($pdfContent)
                    ]
                ]);
                $notification->save();
            }

            // Enviar email
            Mail::to($recipientEmail)
                ->send(new InvoiceMail($invoice, $customMessage, $pdfContent));

            // Actualizar estado
            $notification->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);

            return $notification;
        } catch (\Exception $e) {
            $notification->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function sendPaymentReminder(TaxDocument $invoice, array $options = []): ?EmailNotification
    {
        $recipientEmail = $options['recipient_email'] ?? $invoice->customer->email;
        $reminderType = $options['reminder_type'] ?? 'payment_reminder';
        $customMessage = $options['custom_message'] ?? null;

        if (!$recipientEmail) {
            throw new \Exception('No se especificó un correo electrónico de destino.');
        }

        // Crear registro de notificación
        $notification = EmailNotification::create([
            'tenant_id' => $invoice->tenant_id,
            'notifiable_type' => TaxDocument::class,
            'notifiable_id' => $invoice->id,
            'email_type' => $reminderType,
            'recipient_email' => $recipientEmail,
            'recipient_name' => $invoice->customer->business_name,
            'subject' => $this->getReminderSubject($invoice, $reminderType),
            'body' => $customMessage,
            'status' => 'pending'
        ]);

        try {
            // Generar PDF
            $pdf = Pdf::loadView('invoices.pdf', ['invoice' => $invoice]);
            $pdfContent = $pdf->output();

            // Enviar email
            Mail::to($recipientEmail)
                ->send(new PaymentReminderMail($invoice, $reminderType, $customMessage, $pdfContent));

            // Actualizar estado
            $notification->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);

            return $notification;
        } catch (\Exception $e) {
            $notification->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function sendOverdueReminders(int $tenantId, array $options = []): array
    {
        $daysOverdue = $options['days_overdue'] ?? 1;
        
        // Obtener facturas vencidas
        $overdueInvoices = TaxDocument::where('tenant_id', $tenantId)
            ->where('type', 'invoice')
            ->where('status', 'sent')
            ->where('due_date', '<', now()->subDays($daysOverdue))
            ->whereDoesntHave('payments', function ($query) {
                $query->where('status', 'paid');
            })
            ->with('customer')
            ->get();

        $results = [];
        foreach ($overdueInvoices as $invoice) {
            try {
                $notification = $this->sendPaymentReminder($invoice, [
                    'reminder_type' => 'payment_overdue'
                ]);
                $results[] = $notification;
            } catch (\Exception $e) {
                \Log::error('Error sending overdue reminder for invoice ' . $invoice->id, [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    private function getInvoiceSubject(TaxDocument $invoice): string
    {
        $type = match($invoice->type) {
            'invoice' => 'Factura',
            'ticket' => 'Boleta',
            'credit_note' => 'Nota de Crédito',
            'debit_note' => 'Nota de Débito',
            default => 'Documento'
        };

        return "{$type} {$invoice->number} - {$invoice->tenant->business_name}";
    }

    private function getReminderSubject(TaxDocument $invoice, string $reminderType): string
    {
        $base = $this->getInvoiceSubject($invoice);
        
        return match($reminderType) {
            'payment_reminder' => "Recordatorio de Pago - {$base}",
            'payment_overdue' => "URGENTE: Pago Vencido - {$base}",
            default => "Recordatorio - {$base}"
        };
    }

    private function getInvoiceFileName(TaxDocument $invoice): string
    {
        $type = match($invoice->type) {
            'invoice' => 'Factura',
            'ticket' => 'Boleta',
            'credit_note' => 'NotaCredito',
            'debit_note' => 'NotaDebito',
            default => 'Documento'
        };

        return "{$type}_{$invoice->number}.pdf";
    }
}