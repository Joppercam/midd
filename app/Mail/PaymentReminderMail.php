<?php

namespace App\Mail;

use App\Models\TaxDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public TaxDocument $invoice;
    public string $reminderType;
    public ?string $customMessage;
    public ?string $pdfContent;

    /**
     * Create a new message instance.
     */
    public function __construct(TaxDocument $invoice, string $reminderType, ?string $customMessage = null, ?string $pdfContent = null)
    {
        $this->invoice = $invoice;
        $this->reminderType = $reminderType;
        $this->customMessage = $customMessage;
        $this->pdfContent = $pdfContent;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $type = match($this->invoice->type) {
            'invoice' => 'Factura',
            'ticket' => 'Boleta',
            'credit_note' => 'Nota de Crédito',
            'debit_note' => 'Nota de Débito',
            default => 'Documento'
        };

        $subject = match($this->reminderType) {
            'payment_reminder' => "Recordatorio de Pago - {$type} {$this->invoice->number}",
            'payment_overdue' => "URGENTE: Pago Vencido - {$type} {$this->invoice->number}",
            default => "Recordatorio - {$type} {$this->invoice->number}"
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $template = $this->reminderType === 'payment_overdue' 
            ? 'emails.payment-overdue' 
            : 'emails.payment-reminder';

        return new Content(
            view: $template,
            with: [
                'invoice' => $this->invoice,
                'customMessage' => $this->customMessage,
                'tenant' => $this->invoice->tenant,
                'customer' => $this->invoice->customer,
                'daysOverdue' => $this->invoice->due_date->diffInDays(now()),
                'isOverdue' => $this->invoice->due_date->isPast(),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->pdfContent) {
            $type = match($this->invoice->type) {
                'invoice' => 'Factura',
                'ticket' => 'Boleta',
                'credit_note' => 'NotaCredito',
                'debit_note' => 'NotaDebito',
                default => 'Documento'
            };

            $attachments[] = Attachment::fromData(
                fn () => $this->pdfContent,
                "{$type}_{$this->invoice->number}.pdf"
            )->withMime('application/pdf');
        }

        return $attachments;
    }
}