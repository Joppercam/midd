<?php

namespace App\Mail;

use App\Models\TaxDocument;
use App\Models\EmailNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TaxDocument $invoice,
        public EmailNotification $notification,
        public array $paymentDetails = [],
        public array $options = []
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Confirmación de pago - Factura {$this->invoice->document_number}",
            from: config('mail.from.address', 'noreply@crecepyme.cl'),
            replyTo: $this->invoice->tenant->email ?? config('mail.from.address')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-received',
            with: [
                'invoice' => $this->invoice,
                'notification' => $this->notification,
                'emailType' => 'payment_received',
                'tenant' => $this->invoice->tenant,
                'customer' => $this->invoice->customer,
                'paymentDetails' => $this->paymentDetails,
                'options' => $this->options
            ]
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->options['attach_receipt'] ?? true) {
            try {
                $pdf = app('dompdf.wrapper');
                $pdf->loadView('pdf.payment-receipt', [
                    'invoice' => $this->invoice,
                    'tenant' => $this->invoice->tenant,
                    'customer' => $this->invoice->customer,
                    'paymentDetails' => $this->paymentDetails
                ]);
                
                $attachments[] = [
                    'data' => $pdf->output(),
                    'name' => "comprobante_pago_{$this->invoice->document_number}.pdf",
                    'options' => ['mime' => 'application/pdf']
                ];
            } catch (\Exception $e) {
                \Log::error('Error generating payment receipt PDF', [
                    'invoice_id' => $this->invoice->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $attachments;
    }

    public function build()
    {
        return $this;
    }
}