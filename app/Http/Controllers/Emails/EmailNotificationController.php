<?php

namespace App\Http\Controllers\Emails;

use App\Http\Controllers\Controller;
use App\Models\EmailNotification;
use App\Models\TaxDocument;
use App\Services\EmailNotificationService;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailNotificationController extends Controller
{
    use ChecksPermissions;
    
    protected EmailNotificationService $emailService;

    public function __construct(EmailNotificationService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function index(Request $request): Response
    {
        $this->checkPermission('emails.view');
        $tenantId = auth()->user()->tenant_id;
        
        $query = EmailNotification::where('tenant_id', $tenantId)
            ->with(['notifiable'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('email_type')) {
            $query->where('email_type', $request->email_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('recipient_email', 'like', '%' . $request->search . '%');
        }

        $notifications = $query->paginate(20);

        $statistics = EmailNotification::getStatistics($tenantId);

        return Inertia::render('Emails/Index', [
            'notifications' => $notifications,
            'statistics' => $statistics,
            'filters' => $request->only(['email_type', 'status', 'search'])
        ]);
    }

    public function sendInvoice(Request $request, TaxDocument $invoice)
    {
        $this->checkPermission('emails.send');
        
        if ($invoice->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        $request->validate([
            'recipient_email' => 'nullable|email',
            'attach_pdf' => 'boolean',
            'custom_message' => 'nullable|string|max:1000'
        ]);

        try {
            $options = [
                'recipient_email' => $request->recipient_email,
                'attach_pdf' => $request->attach_pdf ?? true,
                'custom_message' => $request->custom_message
            ];

            $notification = $this->emailService->sendInvoice($invoice, $options);

            if ($notification) {
                return back()->with('success', 'Factura enviada por correo exitosamente.');
            } else {
                return back()->with('error', 'Error al enviar la factura por correo.');
            }
        } catch (\Exception $e) {
            \Log::error('Error sending invoice email', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al enviar la factura: ' . $e->getMessage());
        }
    }

    public function sendPaymentReminder(Request $request, TaxDocument $invoice)
    {
        $this->checkPermission('emails.send');
        
        if ($invoice->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        $request->validate([
            'recipient_email' => 'nullable|email',
            'reminder_type' => 'in:payment_reminder,payment_overdue',
            'custom_message' => 'nullable|string|max:1000'
        ]);

        try {
            $options = [
                'recipient_email' => $request->recipient_email,
                'reminder_type' => $request->reminder_type ?? 'payment_reminder',
                'custom_message' => $request->custom_message
            ];

            $notification = $this->emailService->sendPaymentReminder($invoice, $options);

            if ($notification) {
                return back()->with('success', 'Recordatorio de pago enviado exitosamente.');
            } else {
                return back()->with('error', 'Error al enviar el recordatorio de pago.');
            }
        } catch (\Exception $e) {
            \Log::error('Error sending payment reminder', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al enviar recordatorio: ' . $e->getMessage());
        }
    }

    public function sendOverdueReminders(Request $request)
    {
        $this->checkPermission('emails.send');
        $request->validate([
            'days_overdue' => 'nullable|integer|min:1',
            'send_to_all' => 'boolean'
        ]);

        try {
            $tenantId = auth()->user()->tenant_id;
            
            $options = [
                'days_overdue' => $request->days_overdue ?? 1,
                'send_to_all' => $request->send_to_all ?? false
            ];

            $results = $this->emailService->sendOverdueReminders($tenantId, $options);

            $sentCount = count($results);
            $message = $sentCount > 0 
                ? "Se enviaron {$sentCount} recordatorios de facturas vencidas."
                : "No se encontraron facturas vencidas para enviar recordatorios.";

            return back()->with('success', $message);
        } catch (\Exception $e) {
            \Log::error('Error sending overdue reminders', [
                'tenant_id' => auth()->user()->tenant_id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al enviar recordatorios: ' . $e->getMessage());
        }
    }

    public function show(EmailNotification $notification): Response
    {
        $this->checkPermission('emails.view');
        
        if ($notification->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $notification->load(['notifiable']);

        return Inertia::render('Emails/Show', [
            'notification' => $notification
        ]);
    }

    public function markAsRead(EmailNotification $notification)
    {
        $this->checkPermission('emails.view');
        
        if ($notification->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $notification->markAsOpened();

        return response()->json(['success' => true]);
    }

    public function resend(EmailNotification $notification)
    {
        $this->checkPermission('emails.send');
        
        if ($notification->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        try {
            if ($notification->status === 'sent') {
                return back()->with('error', 'Esta notificación ya fue enviada exitosamente.');
            }

            $invoice = $notification->notifiable;
            
            if (!$invoice instanceof TaxDocument) {
                return back()->with('error', 'Solo se pueden reenviar notificaciones de facturas.');
            }

            $options = [
                'recipient_email' => $notification->recipient_email
            ];

            switch ($notification->email_type) {
                case 'invoice_sent':
                    $newNotification = $this->emailService->sendInvoice($invoice, $options);
                    break;
                case 'payment_reminder':
                case 'payment_overdue':
                    $options['reminder_type'] = $notification->email_type;
                    $newNotification = $this->emailService->sendPaymentReminder($invoice, $options);
                    break;
                default:
                    return back()->with('error', 'Tipo de notificación no soportado para reenvío.');
            }

            if ($newNotification) {
                return back()->with('success', 'Notificación reenviada exitosamente.');
            } else {
                return back()->with('error', 'Error al reenviar la notificación.');
            }
        } catch (\Exception $e) {
            \Log::error('Error resending notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Error al reenviar: ' . $e->getMessage());
        }
    }

    public function trackOpen(Request $request, EmailNotification $notification)
    {
        // No permission check needed for tracking
        try {
            $notification->markAsOpened();
            
            return response('', 200, [
                'Content-Type' => 'image/gif',
                'Content-Length' => 0
            ]);
        } catch (\Exception $e) {
            \Log::error('Error tracking email open', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
            
            return response('', 200);
        }
    }
}