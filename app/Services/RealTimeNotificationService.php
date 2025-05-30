<?php

namespace App\Services;

use App\Events\RealTimeNotification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\EmailNotification;
use Illuminate\Support\Facades\Log;

class RealTimeNotificationService
{
    /**
     * Enviar notificación en tiempo real a usuarios específicos
     */
    public function notifyUsers(array $userIds, string $type, array $data, ?string $tenantId = null): void
    {
        foreach ($userIds as $userId) {
            $this->notifyUser($userId, $type, $data, $tenantId);
        }
    }

    /**
     * Enviar notificación a un usuario específico
     */
    public function notifyUser(int $userId, string $type, array $data, ?string $tenantId = null): void
    {
        try {
            broadcast(new RealTimeNotification($data, $type, $tenantId, $userId));
            
            // Log para auditoría
            Log::info('Real-time notification sent', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'type' => $type,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send real-time notification', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Enviar notificación a todo un tenant
     */
    public function notifyTenant(string $tenantId, string $type, array $data): void
    {
        try {
            broadcast(new RealTimeNotification($data, $type, $tenantId));
            
            Log::info('Tenant-wide notification sent', [
                'tenant_id' => $tenantId,
                'type' => $type,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send tenant notification', [
                'tenant_id' => $tenantId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notificaciones específicas de negocio
     */
    public function notifyNewInvoice(string $tenantId, array $invoiceData): void
    {
        $this->notifyTenant($tenantId, 'invoice.created', [
            'title' => 'Nueva Factura Creada',
            'message' => "Factura #{$invoiceData['number']} por \${$invoiceData['total']}",
            'invoice' => $invoiceData,
            'icon' => 'invoice',
            'color' => 'green'
        ]);
    }

    public function notifyPaymentReceived(string $tenantId, array $paymentData): void
    {
        $this->notifyTenant($tenantId, 'payment.received', [
            'title' => 'Pago Recibido',
            'message' => "Pago de \${$paymentData['amount']} recibido",
            'payment' => $paymentData,
            'icon' => 'payment',
            'color' => 'blue'
        ]);
    }

    public function notifyLowStock(string $tenantId, array $productData): void
    {
        $this->notifyTenant($tenantId, 'inventory.low_stock', [
            'title' => 'Stock Bajo',
            'message' => "El producto {$productData['name']} tiene stock bajo ({$productData['stock']} unidades)",
            'product' => $productData,
            'icon' => 'warning',
            'color' => 'orange'
        ]);
    }

    public function notifyBankReconciliation(string $tenantId, array $reconciliationData): void
    {
        $status = $reconciliationData['status'] === 'completed' ? 'completada' : 'pendiente';
        
        $this->notifyTenant($tenantId, 'bank.reconciliation', [
            'title' => 'Conciliación Bancaria',
            'message' => "Conciliación bancaria {$status}",
            'reconciliation' => $reconciliationData,
            'icon' => 'bank',
            'color' => $reconciliationData['status'] === 'completed' ? 'green' : 'yellow'
        ]);
    }

    public function notifySystemAlert(string $tenantId, string $alertType, array $alertData): void
    {
        $this->notifyTenant($tenantId, 'system.alert', [
            'title' => 'Alerta del Sistema',
            'message' => $alertData['message'] ?? 'Alerta del sistema',
            'alert_type' => $alertType,
            'data' => $alertData,
            'icon' => 'alert',
            'color' => 'red'
        ]);
    }

    /**
     * Notificar actualizaciones de dashboard
     */
    public function notifyDashboardUpdate(string $tenantId, array $metrics): void
    {
        $this->notifyTenant($tenantId, 'dashboard.update', [
            'metrics' => $metrics,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Crear notificación persistente además de en tiempo real
     */
    public function createPersistentNotification(
        int $userId, 
        string $type, 
        array $data, 
        bool $sendRealTime = true
    ): EmailNotification {
        $user = User::find($userId);
        $notification = EmailNotification::create([
            'notifiable_type' => User::class,
            'notifiable_id' => $userId,
            'tenant_id' => $user->tenant_id,
            'email_type' => $type,
            'recipient_email' => $user->email,
            'recipient_name' => $user->name,
            'subject' => $data['title'] ?? 'Notificación',
            'body' => $data['message'] ?? '',
            'status' => 'sent',
            'sent_at' => now(),
            'metadata' => $data
        ]);

        if ($sendRealTime) {
            $this->notifyUser($userId, $type, $data);
        }

        return $notification;
    }
}