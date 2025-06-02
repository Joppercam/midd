<?php

namespace App\Http\Controllers;

use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;

class NotificationController extends Controller
{
    protected PushNotificationService $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->middleware('auth');
        $this->notificationService = $notificationService;
    }

    /**
     * Display notifications interface
     */
    public function index()
    {
        $user = Auth::user();
        
        // Get offline notifications
        $offlineNotifications = $this->notificationService->getOfflineNotifications($user->id);
        
        // Get notification settings
        $settings = $this->getNotificationSettings($user);
        
        // Get notification statistics
        $stats = $this->getNotificationStats($user);
        
        return Inertia::render('Notifications/Index', [
            'offlineNotifications' => $offlineNotifications,
            'settings' => $settings,
            'statistics' => $stats,
            'pusherConfig' => [
                'key' => config('broadcasting.connections.pusher.key'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'forceTLS' => config('broadcasting.connections.pusher.options.useTLS')
            ]
        ]);
    }

    /**
     * Send test notification
     */
    public function sendTest(Request $request)
    {
        $request->validate([
            'type' => 'required|in:success,info,warning,error',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500'
        ]);

        $user = Auth::user();
        
        $notification = [
            'type' => 'test',
            'title' => $request->title,
            'message' => $request->message,
            'icon' => match($request->type) {
                'success' => 'check-circle',
                'info' => 'information-circle',
                'warning' => 'exclamation-triangle',
                'error' => 'x-circle',
                default => 'bell'
            },
            'priority' => $request->type === 'error' ? 'high' : 'medium'
        ];

        $success = $this->notificationService->sendToUser($user, $notification);

        return back()->with(
            $success ? 'success' : 'error',
            $success ? 'Notificación de prueba enviada' : 'Error al enviar notificación'
        );
    }

    /**
     * Update notification settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'sound_enabled' => 'boolean',
            'notification_types' => 'array',
            'quiet_hours' => 'array',
            'quiet_hours.enabled' => 'boolean',
            'quiet_hours.start' => 'nullable|date_format:H:i',
            'quiet_hours.end' => 'nullable|date_format:H:i'
        ]);

        $user = Auth::user();
        
        // Store settings in Redis
        $settings = [
            'email_notifications' => $request->boolean('email_notifications', true),
            'push_notifications' => $request->boolean('push_notifications', true),
            'sound_enabled' => $request->boolean('sound_enabled', true),
            'notification_types' => $request->notification_types ?? [
                'invoices' => true,
                'payments' => true,
                'system' => true,
                'backups' => false
            ],
            'quiet_hours' => $request->quiet_hours ?? [
                'enabled' => false,
                'start' => '22:00',
                'end' => '08:00'
            ]
        ];

        Redis::setex(
            "notification_settings:user:{$user->id}",
            30 * 24 * 60 * 60, // 30 days
            json_encode($settings)
        );

        return back()->with('success', 'Configuración actualizada exitosamente');
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|string'
        ]);

        $user = Auth::user();
        
        // Store read status in Redis
        Redis::setex(
            "notification_read:{$user->id}:{$request->notification_id}",
            7 * 24 * 60 * 60, // 7 days
            json_encode(['read_at' => now()->toISOString()])
        );

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        
        // Clear offline notifications
        Redis::del("notifications:user:{$user->id}");
        
        return response()->json(['success' => true]);
    }

    /**
     * Get real-time connection status
     */
    public function connectionStatus()
    {
        $user = Auth::user();
        
        $status = [
            'connected' => true,
            'user_id' => $user->id,
            'channels' => [
                'user' => "user.{$user->id}",
                'tenant' => "tenant.{$user->tenant_id}",
                'system' => 'system'
            ],
            'server_time' => now()->toISOString()
        ];

        return response()->json($status);
    }

    /**
     * Send notification to specific user (admin only)
     */
    public function sendToUser(Request $request)
    {
        $this->authorize('admin');
        
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'type' => 'string|in:info,success,warning,error',
            'action_url' => 'nullable|url'
        ]);

        $user = \App\Models\User::find($request->user_id);
        
        $notification = [
            'type' => 'admin_message',
            'title' => $request->title,
            'message' => $request->message,
            'action_url' => $request->action_url,
            'icon' => 'megaphone',
            'priority' => $request->type === 'error' ? 'high' : 'medium'
        ];

        $success = $this->notificationService->sendToUser($user, $notification);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Notificación enviada' : 'Error al enviar'
        ]);
    }

    /**
     * Send notification to tenant (admin only)
     */
    public function sendToTenant(Request $request)
    {
        $this->authorize('admin');
        
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'type' => 'string|in:info,success,warning,error',
            'action_url' => 'nullable|url'
        ]);

        $tenant = app('currentTenant');
        
        $notification = [
            'type' => 'tenant_announcement',
            'title' => $request->title,
            'message' => $request->message,
            'action_url' => $request->action_url,
            'icon' => 'speakerphone',
            'priority' => 'medium'
        ];

        $results = $this->notificationService->sendToTenant($tenant, $notification);

        return response()->json([
            'success' => $results['success'] > 0,
            'results' => $results,
            'message' => "Enviado a {$results['success']} usuarios"
        ]);
    }

    /**
     * Get notification statistics
     */
    public function statistics()
    {
        $user = Auth::user();
        $stats = $this->getNotificationStats($user);
        
        return response()->json($stats);
    }

    /**
     * Get user's notification settings
     */
    protected function getNotificationSettings($user): array
    {
        $key = "notification_settings:user:{$user->id}";
        $settings = Redis::get($key);
        
        if ($settings) {
            return json_decode($settings, true);
        }
        
        // Default settings
        return [
            'email_notifications' => true,
            'push_notifications' => true,
            'sound_enabled' => true,
            'notification_types' => [
                'invoices' => true,
                'payments' => true,
                'system' => true,
                'backups' => false
            ],
            'quiet_hours' => [
                'enabled' => false,
                'start' => '22:00',
                'end' => '08:00'
            ]
        ];
    }

    /**
     * Get notification statistics for user
     */
    protected function getNotificationStats($user): array
    {
        $today = now()->startOfDay();
        $week = now()->startOfWeek();
        $month = now()->startOfMonth();
        
        // This would ideally come from a proper notifications table
        // For now, we'll return sample data
        return [
            'today' => 12,
            'week' => 45,
            'month' => 156,
            'unread' => 8,
            'by_type' => [
                'invoices' => 5,
                'payments' => 2,
                'system' => 1,
                'backups' => 0
            ],
            'last_notification' => now()->subMinutes(15)->toISOString()
        ];
    }

    /**
     * Get offline notifications endpoint
     */
    public function getOfflineNotifications()
    {
        $user = Auth::user();
        $notifications = $this->notificationService->getOfflineNotifications($user->id);
        
        return response()->json($notifications);
    }

    /**
     * Send notification to role (admin only)
     */
    public function sendToRole(Request $request)
    {
        $this->authorize('admin');
        
        $request->validate([
            'role' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'type' => 'string|in:info,success,warning,error',
            'action_url' => 'nullable|url'
        ]);

        $tenant = app('currentTenant');
        
        $notification = [
            'type' => 'role_message',
            'title' => $request->title,
            'message' => $request->message,
            'action_url' => $request->action_url,
            'icon' => 'user-group',
            'priority' => $request->type === 'error' ? 'high' : 'medium'
        ];

        $results = $this->notificationService->sendToRole($request->role, $notification, $tenant);

        return response()->json([
            'success' => $results['success'] > 0,
            'results' => $results,
            'message' => "Enviado a {$results['success']} usuarios con rol '{$request->role}'"
        ]);
    }
    
    /**
     * Clear all notifications for user
     */
    public function clearAll()
    {
        $user = Auth::user();
        
        // Clear offline notifications
        Redis::del("notifications:user:{$user->id}");
        
        return response()->json(['success' => true]);
    }
}