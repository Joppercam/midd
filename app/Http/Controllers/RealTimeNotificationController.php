<?php

namespace App\Http\Controllers;

use App\Services\RealTimeNotificationService;
use App\Traits\BelongsToTenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RealTimeNotificationController extends Controller
{
    public function __construct(
        private RealTimeNotificationService $notificationService
    ) {}

    /**
     * Enviar notificación de prueba al usuario actual
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $this->notificationService->notifyUser(
            $user->id,
            'test',
            [
                'title' => 'Notificación de Prueba',
                'message' => 'Esta es una notificación de prueba para verificar el sistema en tiempo real.',
                'icon' => 'info',
                'color' => 'blue'
            ],
            $user->tenant_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Notificación de prueba enviada'
        ]);
    }

    /**
     * Enviar notificación a todo el tenant
     */
    public function sendTenantNotification(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'type' => 'required|string|in:info,success,warning,error',
        ]);

        $user = Auth::user();
        
        $colorMap = [
            'info' => 'blue',
            'success' => 'green',
            'warning' => 'orange',
            'error' => 'red'
        ];

        $this->notificationService->notifyTenant(
            $user->tenant_id,
            $request->type,
            [
                'title' => $request->title,
                'message' => $request->message,
                'icon' => $request->type === 'error' ? 'alert' : $request->type,
                'color' => $colorMap[$request->type]
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Notificación enviada a toda la empresa'
        ]);
    }

    /**
     * Simular notificación de nueva factura
     */
    public function simulateInvoiceNotification(): JsonResponse
    {
        $user = Auth::user();
        
        $this->notificationService->notifyNewInvoice(
            $user->tenant_id,
            [
                'number' => 'F-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'total' => number_format(rand(10000, 500000), 0, ',', '.'),
                'customer' => 'Cliente Demo S.A.',
                'date' => now()->format('d/m/Y')
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Notificación de factura simulada'
        ]);
    }

    /**
     * Simular notificación de pago recibido
     */
    public function simulatePaymentNotification(): JsonResponse
    {
        $user = Auth::user();
        
        $this->notificationService->notifyPaymentReceived(
            $user->tenant_id,
            [
                'amount' => number_format(rand(50000, 300000), 0, ',', '.'),
                'method' => ['Transferencia', 'Efectivo', 'Cheque'][rand(0, 2)],
                'customer' => 'Cliente Demo S.A.',
                'date' => now()->format('d/m/Y H:i')
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Notificación de pago simulada'
        ]);
    }

    /**
     * Simular alerta de stock bajo
     */
    public function simulateLowStockAlert(): JsonResponse
    {
        $user = Auth::user();
        
        $products = [
            'Laptop Dell XPS 13',
            'Monitor Samsung 24"',
            'Teclado Mecánico',
            'Mouse Logitech',
            'Cable HDMI'
        ];

        $this->notificationService->notifyLowStock(
            $user->tenant_id,
            [
                'name' => $products[rand(0, 4)],
                'stock' => rand(1, 5),
                'min_stock' => 10,
                'sku' => 'SKU-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT)
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Alerta de stock bajo simulada'
        ]);
    }

    /**
     * Simular notificación de conciliación bancaria
     */
    public function simulateBankReconciliation(): JsonResponse
    {
        $user = Auth::user();
        
        $this->notificationService->notifyBankReconciliation(
            $user->tenant_id,
            [
                'bank_account' => 'Banco Chile - Cuenta Corriente',
                'status' => ['completed', 'pending'][rand(0, 1)],
                'transactions_matched' => rand(15, 45),
                'differences' => rand(0, 3),
                'date' => now()->format('d/m/Y')
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Notificación de conciliación bancaria simulada'
        ]);
    }

    /**
     * Simular alerta del sistema
     */
    public function simulateSystemAlert(): JsonResponse
    {
        $user = Auth::user();
        
        $alerts = [
            [
                'type' => 'backup_failed',
                'message' => 'Error en respaldo automático programado',
                'severity' => 'high'
            ],
            [
                'type' => 'certificate_expiring',
                'message' => 'Certificado SII expira en 15 días',
                'severity' => 'medium'
            ],
            [
                'type' => 'disk_space_low',
                'message' => 'Espacio en disco menor al 10%',
                'severity' => 'high'
            ]
        ];

        $alert = $alerts[rand(0, 2)];

        $this->notificationService->notifySystemAlert(
            $user->tenant_id,
            $alert['type'],
            [
                'message' => $alert['message'],
                'severity' => $alert['severity'],
                'timestamp' => now()->toISOString()
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Alerta del sistema simulada'
        ]);
    }

    /**
     * Obtener configuración para el componente Vue
     */
    public function getConfiguration(): JsonResponse
    {
        return response()->json([
            'reverb' => [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
            ],
            'user' => Auth::user(),
            'tenant_id' => Auth::user()->tenant_id,
        ]);
    }
}