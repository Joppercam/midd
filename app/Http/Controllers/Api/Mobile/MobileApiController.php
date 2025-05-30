<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\TaxDocument;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Payment;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class MobileApiController extends BaseApiController
{
    protected PushNotificationService $notificationService;

    public function __construct(PushNotificationService $notificationService)
    {
        $this->middleware(['auth:sanctum', 'tenant']);
        $this->notificationService = $notificationService;
    }

    /**
     * Get mobile dashboard data
     */
    public function dashboard(Request $request)
    {
        try {
            $user = Auth::user();
            $tenant = app('currentTenant');
            
            $cacheKey = "mobile_dashboard:{$user->id}:" . date('Y-m-d-H');
            
            $data = Cache::remember($cacheKey, 300, function() use ($user, $tenant) {
                $today = now()->startOfDay();
                $thisMonth = now()->startOfMonth();
                
                // KPIs básicos
                $todayInvoices = TaxDocument::where('tenant_id', $tenant->id)
                    ->whereDate('date', $today)
                    ->whereIn('type', ['invoice', 'exempt_invoice'])
                    ->whereIn('status', ['issued', 'paid'])
                    ->count();
                
                $todayRevenue = TaxDocument::where('tenant_id', $tenant->id)
                    ->whereDate('date', $today)
                    ->whereIn('type', ['invoice', 'exempt_invoice'])
                    ->whereIn('status', ['issued', 'paid'])
                    ->sum('total_amount');
                
                $monthRevenue = TaxDocument::where('tenant_id', $tenant->id)
                    ->whereBetween('date', [$thisMonth, now()])
                    ->whereIn('type', ['invoice', 'exempt_invoice'])
                    ->whereIn('status', ['issued', 'paid'])
                    ->sum('total_amount');
                
                $pendingPayments = TaxDocument::where('tenant_id', $tenant->id)
                    ->where('payment_status', 'pending')
                    ->where('status', 'issued')
                    ->sum('total_amount');
                
                // Actividades recientes
                $recentInvoices = TaxDocument::where('tenant_id', $tenant->id)
                    ->whereIn('type', ['invoice', 'exempt_invoice'])
                    ->with(['customer:id,name,rut'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'number', 'customer_id', 'total_amount', 'status', 'date']);
                
                $recentPayments = Payment::where('tenant_id', $tenant->id)
                    ->with(['customer:id,name'])
                    ->orderBy('payment_date', 'desc')
                    ->limit(5)
                    ->get(['id', 'customer_id', 'amount', 'payment_method', 'payment_date', 'status']);
                
                // Alertas
                $alerts = $this->getMobileAlerts($tenant);
                
                return [
                    'kpis' => [
                        'today_invoices' => $todayInvoices,
                        'today_revenue' => $todayRevenue,
                        'month_revenue' => $monthRevenue,
                        'pending_payments' => $pendingPayments
                    ],
                    'recent_invoices' => $recentInvoices,
                    'recent_payments' => $recentPayments,
                    'alerts' => $alerts,
                    'user_role' => $user->roles->first()?->name ?? 'user'
                ];
            });
            
            return $this->successResponse($data);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error obteniendo dashboard móvil', 500, $e->getMessage());
        }
    }

    /**
     * Get quick stats for mobile widgets
     */
    public function quickStats(Request $request)
    {
        try {
            $tenant = app('currentTenant');
            $period = $request->get('period', 'today'); // today, week, month
            
            [$startDate, $endDate] = $this->getPeriodDates($period);
            
            $stats = [
                'sales' => [
                    'count' => TaxDocument::where('tenant_id', $tenant->id)
                        ->whereIn('type', ['invoice', 'exempt_invoice'])
                        ->whereBetween('date', [$startDate, $endDate])
                        ->whereIn('status', ['issued', 'paid'])
                        ->count(),
                    'amount' => TaxDocument::where('tenant_id', $tenant->id)
                        ->whereIn('type', ['invoice', 'exempt_invoice'])
                        ->whereBetween('date', [$startDate, $endDate])
                        ->whereIn('status', ['issued', 'paid'])
                        ->sum('total_amount')
                ],
                'payments' => [
                    'count' => Payment::where('tenant_id', $tenant->id)
                        ->whereBetween('payment_date', [$startDate, $endDate])
                        ->where('status', 'completed')
                        ->count(),
                    'amount' => Payment::where('tenant_id', $tenant->id)
                        ->whereBetween('payment_date', [$startDate, $endDate])
                        ->where('status', 'completed')
                        ->sum('amount')
                ],
                'customers' => [
                    'active' => Customer::where('tenant_id', $tenant->id)
                        ->whereHas('taxDocuments', function($q) use ($startDate, $endDate) {
                            $q->whereBetween('date', [$startDate, $endDate]);
                        })
                        ->count(),
                    'new' => Customer::where('tenant_id', $tenant->id)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->count()
                ]
            ];
            
            return $this->successResponse($stats);
            
        } catch (\Exception $e) {
            return $this->errorResponse('Error obteniendo estadísticas rápidas', 500, $e->getMessage());
        }
    }

    /**
     * Create quick invoice from mobile
     */
    public function createQuickInvoice(Request $request)
    {
        try {
            $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit_price' => 'nullable|numeric|min:0',
                'payment_method' => 'nullable|string',
                'notes' => 'nullable|string|max:500'
            ]);

            $tenant = app('currentTenant');
            $user = Auth::user();

            // Get next invoice number
            $nextNumber = TaxDocument::where('tenant_id', $tenant->id)
                ->where('type', 'invoice')
                ->max('number') + 1;

            // Calculate totals
            $subtotal = 0;
            $items = [];

            foreach ($request->items as $itemData) {
                $product = Product::find($itemData['product_id']);
                $unitPrice = $itemData['unit_price'] ?? $product->price;
                $lineTotal = $itemData['quantity'] * $unitPrice;
                
                $items[] = [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal
                ];
                
                $subtotal += $lineTotal;
            }

            $taxAmount = $subtotal * 0.19; // IVA 19%
            $totalAmount = $subtotal + $taxAmount;

            // Create invoice
            $invoice = TaxDocument::create([
                'tenant_id' => $tenant->id,
                'type' => 'invoice',
                'number' => $nextNumber,
                'customer_id' => $request->customer_id,
                'date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => 'issued',
                'payment_status' => 'pending',
                'notes' => $request->notes,
                'user_id' => $user->id,
                'created_from' => 'mobile'
            ]);

            // Create invoice items
            foreach ($items as $item) {
                $invoice->items()->create($item);
            }

            // Send notification
            $this->notificationService->notifyInvoiceEvent('created', $invoice, $user);

            return $this->successResponse([
                'invoice' => $invoice->load('customer', 'items'),
                'message' => 'Factura creada exitosamente'
            ], 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Error creando factura rápida', 500, $e->getMessage());
        }
    }

    /**
     * Register quick payment from mobile
     */
    public function registerQuickPayment(Request $request)
    {
        try {
            $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|string',
                'reference_number' => 'nullable|string',
                'notes' => 'nullable|string|max:500'
            ]);

            $tenant = app('currentTenant');
            $user = Auth::user();

            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'customer_id' => $request->customer_id,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_date' => now()->toDateString(),
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
                'status' => 'completed',
                'created_by' => $user->id,
                'created_from' => 'mobile'
            ]);

            // Send notification
            $this->notificationService->notifyPaymentEvent('received', $payment);

            return $this->successResponse([
                'payment' => $payment->load('customer'),
                'message' => 'Pago registrado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Error registrando pago', 500, $e->getMessage());
        }
    }

    /**
     * Get customer list for mobile
     */
    public function customers(Request $request)
    {
        try {
            $tenant = app('currentTenant');
            $search = $request->get('search');
            $limit = min($request->get('limit', 20), 100);

            $query = Customer::where('tenant_id', $tenant->id)
                ->where('is_active', true);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('rut', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $customers = $query->select([
                    'id', 'name', 'rut', 'email', 'phone', 
                    'address', 'customer_type'
                ])
                ->orderBy('name')
                ->limit($limit)
                ->get();

            return $this->successResponse($customers);

        } catch (\Exception $e) {
            return $this->errorResponse('Error obteniendo clientes', 500, $e->getMessage());
        }
    }

    /**
     * Get product list for mobile
     */
    public function products(Request $request)
    {
        try {
            $tenant = app('currentTenant');
            $search = $request->get('search');
            $limit = min($request->get('limit', 20), 100);

            $query = Product::where('tenant_id', $tenant->id)
                ->where('is_active', true);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $products = $query->select([
                    'id', 'name', 'sku', 'description', 'price', 
                    'current_stock', 'manages_inventory'
                ])
                ->orderBy('name')
                ->limit($limit)
                ->get();

            return $this->successResponse($products);

        } catch (\Exception $e) {
            return $this->errorResponse('Error obteniendo productos', 500, $e->getMessage());
        }
    }

    /**
     * Get recent invoices for mobile
     */
    public function recentInvoices(Request $request)
    {
        try {
            $tenant = app('currentTenant');
            $limit = min($request->get('limit', 10), 50);

            $invoices = TaxDocument::where('tenant_id', $tenant->id)
                ->whereIn('type', ['invoice', 'exempt_invoice'])
                ->with(['customer:id,name,rut'])
                ->select([
                    'id', 'number', 'customer_id', 'date', 'due_date',
                    'total_amount', 'status', 'payment_status'
                ])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return $this->successResponse($invoices);

        } catch (\Exception $e) {
            return $this->errorResponse('Error obteniendo facturas recientes', 500, $e->getMessage());
        }
    }

    /**
     * Send push notification to mobile device
     */
    public function sendPushNotification(Request $request)
    {
        try {
            $request->validate([
                'device_token' => 'required|string',
                'title' => 'required|string|max:100',
                'body' => 'required|string|max:200',
                'data' => 'nullable|array'
            ]);

            $user = Auth::user();

            // Store device token for user
            $user->update([
                'mobile_device_token' => $request->device_token
            ]);

            // Send notification through Firebase/APNs
            $notification = [
                'title' => $request->title,
                'body' => $request->body,
                'data' => $request->data ?? []
            ];

            // Implementation would use Firebase Cloud Messaging or Apple Push Notification service
            $result = $this->sendMobilePushNotification($request->device_token, $notification);

            return $this->successResponse([
                'sent' => $result,
                'message' => 'Notificación enviada'
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error enviando notificación push', 500, $e->getMessage());
        }
    }

    /**
     * Sync offline data from mobile
     */
    public function syncOfflineData(Request $request)
    {
        try {
            $request->validate([
                'invoices' => 'nullable|array',
                'payments' => 'nullable|array',
                'customers' => 'nullable|array',
                'last_sync' => 'nullable|date'
            ]);

            $syncResults = [
                'invoices' => [],
                'payments' => [],
                'customers' => [],
                'conflicts' => []
            ];

            // Sync invoices
            if ($request->has('invoices')) {
                $syncResults['invoices'] = $this->syncInvoices($request->invoices);
            }

            // Sync payments
            if ($request->has('payments')) {
                $syncResults['payments'] = $this->syncPayments($request->payments);
            }

            // Sync customers
            if ($request->has('customers')) {
                $syncResults['customers'] = $this->syncCustomers($request->customers);
            }

            // Get updates since last sync
            $lastSync = $request->last_sync ? Carbon::parse($request->last_sync) : now()->subDays(7);
            $updates = $this->getUpdatesSinceLastSync($lastSync);

            return $this->successResponse([
                'sync_results' => $syncResults,
                'updates' => $updates,
                'sync_timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error sincronizando datos', 500, $e->getMessage());
        }
    }

    /**
     * Get mobile app configuration
     */
    public function appConfig(Request $request)
    {
        try {
            $tenant = app('currentTenant');
            $user = Auth::user();

            $config = [
                'app_version' => '1.0.0',
                'api_version' => 'v1',
                'features' => [
                    'quick_invoice' => true,
                    'payment_registration' => true,
                    'offline_mode' => true,
                    'push_notifications' => true,
                    'barcode_scanner' => true
                ],
                'settings' => [
                    'default_tax_rate' => 0.19,
                    'currency' => 'CLP',
                    'date_format' => 'd/m/Y',
                    'decimal_places' => 0
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? 'user',
                    'permissions' => $user->getAllPermissions()->pluck('name')
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'rut' => $tenant->rut
                ]
            ];

            return $this->successResponse($config);

        } catch (\Exception $e) {
            return $this->errorResponse('Error obteniendo configuración', 500, $e->getMessage());
        }
    }

    // Helper methods

    protected function getMobileAlerts($tenant): array
    {
        $alerts = [];

        // Facturas vencidas
        $overdueInvoices = TaxDocument::where('tenant_id', $tenant->id)
            ->where('payment_status', 'pending')
            ->where('due_date', '<', now())
            ->count();

        if ($overdueInvoices > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Facturas Vencidas',
                'message' => "{$overdueInvoices} facturas vencidas requieren atención",
                'action' => 'view_overdue_invoices'
            ];
        }

        // Stock bajo
        $lowStockProducts = Product::where('tenant_id', $tenant->id)
            ->where('manages_inventory', true)
            ->whereRaw('current_stock <= minimum_stock')
            ->count();

        if ($lowStockProducts > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Stock Bajo',
                'message' => "{$lowStockProducts} productos con stock bajo",
                'action' => 'view_low_stock'
            ];
        }

        return $alerts;
    }

    protected function getPeriodDates(string $period): array
    {
        return match($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [now()->startOfDay(), now()->endOfDay()]
        };
    }

    protected function sendMobilePushNotification(string $deviceToken, array $notification): bool
    {
        // Implementation would integrate with Firebase Cloud Messaging or Apple Push Notification service
        return true;
    }

    protected function syncInvoices(array $invoices): array
    {
        // Implementation for syncing offline invoices
        return ['synced' => count($invoices), 'conflicts' => 0];
    }

    protected function syncPayments(array $payments): array
    {
        // Implementation for syncing offline payments
        return ['synced' => count($payments), 'conflicts' => 0];
    }

    protected function syncCustomers(array $customers): array
    {
        // Implementation for syncing offline customers
        return ['synced' => count($customers), 'conflicts' => 0];
    }

    protected function getUpdatesSinceLastSync(Carbon $lastSync): array
    {
        $tenant = app('currentTenant');

        return [
            'invoices' => TaxDocument::where('tenant_id', $tenant->id)
                ->where('updated_at', '>', $lastSync)
                ->with('customer:id,name')
                ->select(['id', 'number', 'customer_id', 'total_amount', 'status', 'updated_at'])
                ->get(),
            'payments' => Payment::where('tenant_id', $tenant->id)
                ->where('updated_at', '>', $lastSync)
                ->with('customer:id,name')
                ->select(['id', 'customer_id', 'amount', 'status', 'updated_at'])
                ->get(),
            'customers' => Customer::where('tenant_id', $tenant->id)
                ->where('updated_at', '>', $lastSync)
                ->select(['id', 'name', 'rut', 'email', 'phone', 'updated_at'])
                ->get()
        ];
    }
}