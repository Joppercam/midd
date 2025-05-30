<?php

namespace App\Modules\POS\Services;

use App\Models\Product;
use App\Models\Customer;
use App\Models\Category;
use App\Modules\POS\Models\Terminal;
use App\Modules\POS\Models\Transaction;
use App\Modules\POS\Models\CashSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class POSService
{
    private function getTenantId(): int
    {
        return Auth::user()->tenant_id;
    }

    public function getDashboardStats(): array
    {
        $tenantId = $this->getTenantId();
        $today = Carbon::today();

        return [
            'today_sales' => [
                'count' => Transaction::where('tenant_id', $tenantId)
                    ->whereDate('created_at', $today)
                    ->where('type', 'sale')
                    ->where('status', 'completed')
                    ->count(),
                'total' => Transaction::where('tenant_id', $tenantId)
                    ->whereDate('created_at', $today)
                    ->where('type', 'sale')
                    ->where('status', 'completed')
                    ->sum('total_amount'),
            ],
            'active_sessions' => CashSession::where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->count(),
            'active_terminals' => Terminal::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count(),
            'total_cash' => CashSession::where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->sum('current_balance'),
        ];
    }

    public function getAvailableTerminals(): \Illuminate\Database\Eloquent\Collection
    {
        return Terminal::where('tenant_id', $this->getTenantId())
            ->where('status', 'active')
            ->whereDoesntHave('activeSessions')
            ->orderBy('name')
            ->get();
    }

    public function getTodayStats(int $userId): array
    {
        $tenantId = $this->getTenantId();
        $today = Carbon::today();

        $userSessions = CashSession::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereDate('opened_at', $today)
            ->get();

        $userTransactions = Transaction::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->where('type', 'sale')
            ->where('status', 'completed');

        return [
            'sessions_today' => $userSessions->count(),
            'sales_count' => $userTransactions->count(),
            'sales_total' => $userTransactions->sum('total_amount'),
            'avg_ticket' => $userTransactions->count() > 0 ? 
                $userTransactions->sum('total_amount') / $userTransactions->count() : 0,
            'cash_handled' => $userSessions->sum('opening_amount'),
        ];
    }

    public function getQuickAccessProducts(int $limit = 12): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('tenant_id', $this->getTenantId())
            ->where('is_active', true)
            ->where('pos_featured', true)
            ->with('category')
            ->orderBy('pos_sort_order')
            ->limit($limit)
            ->get();
    }

    public function getCategories(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::where('tenant_id', $this->getTenantId())
            ->where('is_active', true)
            ->whereHas('products', function ($query) {
                $query->where('is_active', true);
            })
            ->withCount('products')
            ->orderBy('name')
            ->get();
    }

    public function getFrequentCustomers(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Customer::where('tenant_id', $this->getTenantId())
            ->where('is_active', true)
            ->withCount('taxDocuments')
            ->orderByDesc('tax_documents_count')
            ->limit($limit)
            ->get();
    }

    public function searchProducts(string $query): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('tenant_id', $this->getTenantId())
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->with('category')
            ->limit(20)
            ->get();
    }

    public function getProductByCode(string $code): ?Product
    {
        return Product::where('tenant_id', $this->getTenantId())
            ->where('is_active', true)
            ->where(function ($query) use ($code) {
                $query->where('sku', $code)
                    ->orWhere('barcode', $code);
            })
            ->with('category')
            ->first();
    }

    public function searchCustomers(string $query): \Illuminate\Database\Eloquent\Collection
    {
        return Customer::where('tenant_id', $this->getTenantId())
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('rut', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get();
    }

    public function applyDiscount(array $data): array
    {
        $discountConfig = config('pos.discounts');
        $maxWithoutApproval = $discountConfig['max_discount_without_approval'];

        // Validar si requiere aprobación gerencial
        if ($data['type'] === 'percentage' && $data['value'] > $maxWithoutApproval) {
            if (empty($data['manager_pin'])) {
                throw new \Exception('Se requiere PIN gerencial para descuentos mayores al ' . $maxWithoutApproval . '%');
            }
            // TODO: Validar PIN gerencial
        }

        // Validar límites
        if ($data['type'] === 'percentage' && $data['value'] > config('pos.sales.max_discount_percentage')) {
            throw new \Exception('El descuento no puede ser mayor al ' . config('pos.sales.max_discount_percentage') . '%');
        }

        return [
            'type' => $data['type'],
            'value' => $data['value'],
            'reason' => $data['reason'],
            'applied_by' => Auth::id(),
            'applied_at' => now(),
        ];
    }

    public function generateReceipt(int $saleId): array
    {
        $transaction = Transaction::with(['items.product', 'customer', 'payments'])
            ->findOrFail($saleId);

        $receiptConfig = config('pos.receipts');
        $company = Auth::user()->tenant;

        $receipt = [
            'header' => [],
            'body' => [],
            'footer' => [],
        ];

        // Header
        if ($receiptConfig['header']['show_logo']) {
            $receipt['header']['logo'] = $company->logo_path;
        }
        if ($receiptConfig['header']['show_company_name']) {
            $receipt['header']['company_name'] = $company->name;
        }
        if ($receiptConfig['header']['show_address']) {
            $receipt['header']['address'] = $company->address;
        }
        if ($receiptConfig['header']['show_phone']) {
            $receipt['header']['phone'] = $company->phone;
        }
        if ($receiptConfig['header']['show_rut']) {
            $receipt['header']['rut'] = $company->rut;
        }

        // Body
        $receipt['body'] = [
            'transaction_number' => $transaction->number,
            'date' => $transaction->created_at->format('d/m/Y H:i:s'),
            'cashier' => $transaction->user->name,
            'customer' => $transaction->customer ? $transaction->customer->name : 'Cliente General',
            'items' => $transaction->items->map(function ($item) use ($receiptConfig) {
                $itemData = [];
                
                if ($receiptConfig['body']['show_product_code']) {
                    $itemData['code'] = $item->product->sku;
                }
                if ($receiptConfig['body']['show_product_description']) {
                    $itemData['description'] = $item->product->name;
                }
                if ($receiptConfig['body']['show_quantity']) {
                    $itemData['quantity'] = $item->quantity;
                }
                if ($receiptConfig['body']['show_unit_price']) {
                    $itemData['unit_price'] = $item->unit_price;
                }
                if ($receiptConfig['body']['show_line_total']) {
                    $itemData['line_total'] = $item->line_total;
                }
                
                return $itemData;
            }),
        ];

        // Footer
        if ($receiptConfig['footer']['show_subtotal']) {
            $receipt['footer']['subtotal'] = $transaction->subtotal;
        }
        if ($receiptConfig['footer']['show_tax_breakdown']) {
            $receipt['footer']['tax_amount'] = $transaction->tax_amount;
        }
        if ($receiptConfig['footer']['show_total']) {
            $receipt['footer']['total'] = $transaction->total_amount;
        }
        if ($receiptConfig['footer']['show_payment_method']) {
            $receipt['footer']['payments'] = $transaction->payments->map(function ($payment) {
                return [
                    'method' => $payment->method,
                    'amount' => $payment->amount,
                ];
            });
        }
        if ($receiptConfig['footer']['show_thank_you']) {
            $receipt['footer']['message'] = $receiptConfig['footer']['custom_message'];
        }

        return $receipt;
    }

    public function emailReceipt(int $saleId, string $email): void
    {
        $receipt = $this->generateReceipt($saleId);
        
        // TODO: Implementar envío de email
        // Mail::to($email)->send(new ReceiptMail($receipt));
    }

    public function openCashDrawer(): void
    {
        // TODO: Implementar apertura de cajón físico
        // Esto depende del hardware específico (impresora fiscal, cajón USB, etc.)
        
        // Por ahora solo logeamos la acción
        activity()
            ->causedBy(Auth::user())
            ->log('Cash drawer opened');
    }

    public function getSystemAlerts(): array
    {
        $alerts = [];
        $tenantId = $this->getTenantId();

        // Alertas de stock bajo
        $lowStockProducts = Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('track_quantity', true)
            ->whereRaw('quantity_available <= min_quantity')
            ->count();

        if ($lowStockProducts > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$lowStockProducts} productos con stock bajo",
                'action' => 'Ver productos',
                'url' => route('products.index', ['filter' => 'low_stock']),
            ];
        }

        // Alertas de sesiones abiertas por mucho tiempo
        $longSessions = CashSession::where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->where('opened_at', '<', Carbon::now()->subHours(12))
            ->count();

        if ($longSessions > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => "{$longSessions} sesiones abiertas por más de 12 horas",
                'action' => 'Ver sesiones',
                'url' => route('pos.cash-sessions.index'),
            ];
        }

        return $alerts;
    }

    public function getTrainingProducts(): \Illuminate\Database\Eloquent\Collection
    {
        // Productos de ejemplo para modo entrenamiento
        return Product::where('tenant_id', $this->getTenantId())
            ->where('is_active', true)
            ->limit(20)
            ->get()
            ->map(function ($product) {
                $product->name = '[TRAINING] ' . $product->name;
                $product->price = 1000; // Precio fijo para entrenamiento
                return $product;
            });
    }

    public function getTrainingCustomers(): array
    {
        return [
            ['id' => 'training-1', 'name' => '[TRAINING] Cliente Ejemplo 1', 'rut' => '11111111-1'],
            ['id' => 'training-2', 'name' => '[TRAINING] Cliente Ejemplo 2', 'rut' => '22222222-2'],
        ];
    }

    public function getAvailableReports(): array
    {
        return [
            'sales' => [
                'name' => 'Reporte de Ventas',
                'description' => 'Ventas por período, usuario y terminal',
                'permissions' => ['pos.reports.sales'],
            ],
            'products' => [
                'name' => 'Reporte de Productos',
                'description' => 'Productos más vendidos y rentabilidad',
                'permissions' => ['pos.reports.products'],
            ],
            'cash' => [
                'name' => 'Reporte de Caja',
                'description' => 'Movimientos de caja y arqueos',
                'permissions' => ['pos.reports.cash'],
            ],
            'users' => [
                'name' => 'Reporte de Usuarios',
                'description' => 'Rendimiento por usuario',
                'permissions' => ['pos.reports.users'],
            ],
        ];
    }

    public function getReportDateRanges(): array
    {
        return [
            'today' => 'Hoy',
            'yesterday' => 'Ayer',
            'this_week' => 'Esta semana',
            'last_week' => 'Semana pasada',
            'this_month' => 'Este mes',
            'last_month' => 'Mes pasado',
            'custom' => 'Personalizado',
        ];
    }

    public function generateReport(array $data): array
    {
        $type = $data['type'];
        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $tenantId = $this->getTenantId();

        switch ($type) {
            case 'sales':
                return $this->generateSalesReport($tenantId, $startDate, $endDate);
            case 'products':
                return $this->generateProductsReport($tenantId, $startDate, $endDate);
            case 'cash':
                return $this->generateCashReport($tenantId, $startDate, $endDate);
            case 'users':
                return $this->generateUsersReport($tenantId, $startDate, $endDate);
            default:
                throw new \Exception('Tipo de reporte no válido');
        }
    }

    private function generateSalesReport(int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        $transactions = Transaction::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('type', 'sale')
            ->where('status', 'completed')
            ->with(['user', 'cashSession.terminal'])
            ->get();

        return [
            'period' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            'total_sales' => $transactions->count(),
            'total_amount' => $transactions->sum('total_amount'),
            'avg_ticket' => $transactions->count() > 0 ? $transactions->sum('total_amount') / $transactions->count() : 0,
            'by_day' => $transactions->groupBy(function ($transaction) {
                return $transaction->created_at->format('Y-m-d');
            })->map(function ($dayTransactions) {
                return [
                    'count' => $dayTransactions->count(),
                    'total' => $dayTransactions->sum('total_amount'),
                ];
            }),
            'by_user' => $transactions->groupBy('user.name')->map(function ($userTransactions) {
                return [
                    'count' => $userTransactions->count(),
                    'total' => $userTransactions->sum('total_amount'),
                ];
            }),
        ];
    }

    private function generateProductsReport(int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        // TODO: Implementar reporte de productos
        return [];
    }

    private function generateCashReport(int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        // TODO: Implementar reporte de caja
        return [];
    }

    private function generateUsersReport(int $tenantId, Carbon $startDate, Carbon $endDate): array
    {
        // TODO: Implementar reporte de usuarios
        return [];
    }
}