<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Events\NotificationSent;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;
use Carbon\Carbon;

class PushNotificationService
{
    protected Pusher $pusher;
    protected array $channels = [];

    public function __construct()
    {
        $this->pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTLS' => config('broadcasting.connections.pusher.options.useTLS'),
                'encrypted' => true
            ]
        );
    }

    /**
     * Send notification to specific user
     */
    public function sendToUser(User $user, array $notification): bool
    {
        try {
            $channel = "user.{$user->id}";
            
            $data = $this->formatNotification($notification, $user);
            
            // Send via Pusher
            $this->pusher->trigger($channel, 'notification', $data);
            
            // Store in Redis for offline users
            $this->storeForOfflineUser($user->id, $data);
            
            // Log notification
            $this->logNotification($user, $data);
            
            // Fire event
            Event::dispatch(new NotificationSent($user, $data));
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send push notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'notification' => $notification
            ]);
            
            return false;
        }
    }

    /**
     * Send notification to all users in tenant
     */
    public function sendToTenant(Tenant $tenant, array $notification): array
    {
        $users = User::where('tenant_id', $tenant->id)->where('is_active', true)->get();
        $results = ['success' => 0, 'failed' => 0];
        
        foreach ($users as $user) {
            if ($this->sendToUser($user, $notification)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }

    /**
     * Send notification to users with specific role
     */
    public function sendToRole(string $role, array $notification, ?Tenant $tenant = null): array
    {
        $query = User::whereHas('roles', function($q) use ($role) {
            $q->where('name', $role);
        })->where('is_active', true);
        
        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }
        
        $users = $query->get();
        $results = ['success' => 0, 'failed' => 0];
        
        foreach ($users as $user) {
            if ($this->sendToUser($user, $notification)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }

    /**
     * Send system-wide notification
     */
    public function sendSystemNotification(array $notification): bool
    {
        try {
            $data = $this->formatNotification($notification);
            
            // Send to system channel
            $this->pusher->trigger('system', 'notification', $data);
            
            // Store for all active users
            $activeUsers = User::where('is_active', true)->pluck('id');
            foreach ($activeUsers as $userId) {
                $this->storeForOfflineUser($userId, $data);
            }
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send system notification', [
                'error' => $e->getMessage(),
                'notification' => $notification
            ]);
            
            return false;
        }
    }

    /**
     * Send real-time update for specific entity
     */
    public function sendEntityUpdate(string $entityType, int $entityId, array $data, ?Tenant $tenant = null): bool
    {
        try {
            $channel = $tenant ? "tenant.{$tenant->id}.{$entityType}" : "system.{$entityType}";
            
            $updateData = [
                'type' => 'entity_update',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ];
            
            $this->pusher->trigger($channel, 'entity.updated', $updateData);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send entity update', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Send progress update for long-running operations
     */
    public function sendProgressUpdate(User $user, string $operationId, int $progress, string $message = ''): bool
    {
        try {
            $channel = "user.{$user->id}";
            
            $data = [
                'type' => 'progress_update',
                'operation_id' => $operationId,
                'progress' => min(100, max(0, $progress)),
                'message' => $message,
                'timestamp' => now()->toISOString()
            ];
            
            $this->pusher->trigger($channel, 'progress', $data);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send progress update', [
                'user_id' => $user->id,
                'operation_id' => $operationId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get offline notifications for user
     */
    public function getOfflineNotifications(int $userId): array
    {
        try {
            $key = "notifications:user:{$userId}";
            $notifications = Redis::lrange($key, 0, -1);
            
            // Clear retrieved notifications
            Redis::del($key);
            
            return array_map(function($notification) {
                return json_decode($notification, true);
            }, $notifications);
            
        } catch (\Exception $e) {
            Log::error('Failed to get offline notifications', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Create notification for invoice events
     */
    public function notifyInvoiceEvent(string $event, $invoice, ?User $user = null): bool
    {
        $notification = [
            'type' => 'invoice_event',
            'event' => $event,
            'title' => $this->getInvoiceEventTitle($event),
            'message' => $this->getInvoiceEventMessage($event, $invoice),
            'data' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'amount' => $invoice->total_amount,
                'customer' => $invoice->customer->name ?? 'N/A'
            ],
            'action_url' => route('invoices.show', $invoice->id),
            'icon' => 'document-text',
            'priority' => $this->getEventPriority($event)
        ];
        
        if ($user) {
            return $this->sendToUser($user, $notification);
        } else {
            // Send to relevant roles based on event
            $roles = $this->getRelevantRoles($event);
            $tenant = app('currentTenant');
            
            foreach ($roles as $role) {
                $this->sendToRole($role, $notification, $tenant);
            }
            
            return true;
        }
    }

    /**
     * Create notification for payment events
     */
    public function notifyPaymentEvent(string $event, $payment): bool
    {
        $notification = [
            'type' => 'payment_event',
            'event' => $event,
            'title' => $this->getPaymentEventTitle($event),
            'message' => $this->getPaymentEventMessage($event, $payment),
            'data' => [
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'customer' => $payment->customer->name ?? 'N/A',
                'method' => $payment->payment_method
            ],
            'action_url' => route('payments.show', $payment->id),
            'icon' => 'currency-dollar',
            'priority' => 'high'
        ];
        
        $tenant = app('currentTenant');
        return $this->sendToRole('admin', $notification, $tenant) ||
               $this->sendToRole('contador', $notification, $tenant);
    }

    /**
     * Create notification for system events
     */
    public function notifySystemEvent(string $event, array $data = []): bool
    {
        $notification = [
            'type' => 'system_event',
            'event' => $event,
            'title' => $this->getSystemEventTitle($event),
            'message' => $this->getSystemEventMessage($event, $data),
            'data' => $data,
            'icon' => 'exclamation-circle',
            'priority' => 'medium'
        ];
        
        return $this->sendSystemNotification($notification);
    }

    /**
     * Create notification for backup events
     */
    public function notifyBackupEvent(string $event, $backup, ?$schedule = null): bool
    {
        $notification = [
            'type' => 'backup_event',
            'event' => $event,
            'title' => $this->getBackupEventTitle($event),
            'message' => $this->getBackupEventMessage($event, $backup, $schedule),
            'data' => [
                'backup_id' => $backup->id ?? null,
                'filename' => $backup->filename ?? null,
                'size' => $backup->file_size ?? null,
                'type' => $backup->type ?? null
            ],
            'action_url' => route('backups.index'),
            'icon' => 'server',
            'priority' => $event === 'failed' ? 'high' : 'medium'
        ];
        
        $tenant = app('currentTenant');
        return $this->sendToRole('admin', $notification, $tenant);
    }

    /**
     * Format notification data
     */
    protected function formatNotification(array $notification, ?User $user = null): array
    {
        return [
            'id' => uniqid('notif_'),
            'type' => $notification['type'] ?? 'general',
            'title' => $notification['title'] ?? 'Notificación',
            'message' => $notification['message'] ?? '',
            'data' => $notification['data'] ?? [],
            'action_url' => $notification['action_url'] ?? null,
            'icon' => $notification['icon'] ?? 'bell',
            'priority' => $notification['priority'] ?? 'medium',
            'timestamp' => now()->toISOString(),
            'read' => false,
            'user_id' => $user?->id
        ];
    }

    /**
     * Store notification for offline user
     */
    protected function storeForOfflineUser(int $userId, array $data): void
    {
        try {
            $key = "notifications:user:{$userId}";
            Redis::lpush($key, json_encode($data));
            
            // Keep only last 50 notifications
            Redis::ltrim($key, 0, 49);
            
            // Set expiration (7 days)
            Redis::expire($key, 7 * 24 * 60 * 60);
            
        } catch (\Exception $e) {
            Log::error('Failed to store offline notification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log notification for audit
     */
    protected function logNotification(User $user, array $data): void
    {
        Log::channel('notifications')->info('Push notification sent', [
            'notification_id' => $data['id'],
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'type' => $data['type'],
            'title' => $data['title'],
            'priority' => $data['priority']
        ]);
    }

    // Helper methods for event messages
    protected function getInvoiceEventTitle(string $event): string
    {
        return match($event) {
            'created' => 'Nueva Factura Creada',
            'sent' => 'Factura Enviada',
            'paid' => 'Factura Pagada',
            'overdue' => 'Factura Vencida',
            'cancelled' => 'Factura Cancelada',
            default => 'Evento de Factura'
        };
    }

    protected function getInvoiceEventMessage(string $event, $invoice): string
    {
        $customer = $invoice->customer->name ?? 'Cliente';
        $amount = number_format($invoice->total_amount, 0, ',', '.');
        
        return match($event) {
            'created' => "Se creó la factura #{$invoice->number} para {$customer} por \${$amount}",
            'sent' => "Se envió la factura #{$invoice->number} a {$customer}",
            'paid' => "Se pagó la factura #{$invoice->number} de {$customer} por \${$amount}",
            'overdue' => "La factura #{$invoice->number} de {$customer} está vencida",
            'cancelled' => "Se canceló la factura #{$invoice->number} de {$customer}",
            default => "Evento en factura #{$invoice->number}"
        };
    }

    protected function getPaymentEventTitle(string $event): string
    {
        return match($event) {
            'received' => 'Pago Recibido',
            'pending' => 'Pago Pendiente',
            'failed' => 'Pago Fallido',
            default => 'Evento de Pago'
        };
    }

    protected function getPaymentEventMessage(string $event, $payment): string
    {
        $customer = $payment->customer->name ?? 'Cliente';
        $amount = number_format($payment->amount, 0, ',', '.');
        
        return match($event) {
            'received' => "Se recibió un pago de {$customer} por \${$amount}",
            'pending' => "Pago pendiente de {$customer} por \${$amount}",
            'failed' => "Falló el pago de {$customer} por \${$amount}",
            default => "Evento de pago"
        };
    }

    protected function getSystemEventTitle(string $event): string
    {
        return match($event) {
            'maintenance' => 'Mantenimiento del Sistema',
            'update' => 'Actualización del Sistema',
            'error' => 'Error del Sistema',
            'backup_completed' => 'Backup Completado',
            'backup_failed' => 'Backup Fallido',
            default => 'Evento del Sistema'
        };
    }

    protected function getSystemEventMessage(string $event, array $data): string
    {
        return match($event) {
            'maintenance' => 'El sistema entrará en mantenimiento programado',
            'update' => 'Se ha actualizado el sistema con nuevas funcionalidades',
            'error' => 'Se detectó un error en el sistema: ' . ($data['message'] ?? 'Error desconocido'),
            'backup_completed' => 'Se completó el backup del sistema exitosamente',
            'backup_failed' => 'Falló el backup del sistema',
            default => 'Evento del sistema'
        };
    }

    protected function getBackupEventTitle(string $event): string
    {
        return match($event) {
            'completed' => 'Backup Completado',
            'failed' => 'Backup Fallido',
            'started' => 'Backup Iniciado',
            default => 'Evento de Backup'
        };
    }

    protected function getBackupEventMessage(string $event, $backup, ?$schedule): string
    {
        $type = $backup->type ?? 'completo';
        $scheduleName = $schedule?->name ?? 'manual';
        
        return match($event) {
            'completed' => "Se completó el backup {$type} ({$scheduleName}) exitosamente",
            'failed' => "Falló el backup {$type} ({$scheduleName})",
            'started' => "Se inició el backup {$type} ({$scheduleName})",
            default => "Evento de backup"
        };
    }

    protected function getEventPriority(string $event): string
    {
        return match($event) {
            'overdue', 'failed', 'cancelled' => 'high',
            'paid', 'received' => 'medium',
            default => 'low'
        };
    }

    protected function getRelevantRoles(string $event): array
    {
        return match($event) {
            'created', 'sent' => ['admin', 'vendedor'],
            'paid', 'received' => ['admin', 'contador'],
            'overdue', 'failed' => ['admin', 'gerente', 'contador'],
            default => ['admin']
        };
    }
}