<?php

namespace App\Modules\Inventory\Services;

use App\Models\Supplier;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Validators\RutValidator;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class SupplierService
{
    public function getSuppliersList(array $filters = [], int $perPage = 15)
    {
        $query = Supplier::where('tenant_id', auth()->user()->tenant_id);

        // Búsqueda
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('rut', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filtro por tipo
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filtro por categoría
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Filtro por activos/inactivos
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Ordenamiento
        $sortField = $filters['sort'] ?? 'name';
        $sortDirection = $filters['direction'] ?? 'asc';
        $query->orderBy($sortField, $sortDirection);

        // Incluir estadísticas
        $query->withCount(['expenses', 'purchaseOrders'])
              ->withSum('expenses', 'total');

        return $query->paginate($perPage)->withQueryString();
    }

    public function getSuppliersStats(): array
    {
        $tenantId = auth()->user()->tenant_id;
        
        $supplierStats = Supplier::where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(*) as total_suppliers,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_suppliers,
                COUNT(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 END) as new_this_month,
                COUNT(CASE WHEN category = 'preferred' THEN 1 END) as preferred_suppliers
            ", [now()->month, now()->year])
            ->first();

        $totalPurchases = DB::table('suppliers')
            ->join('expenses', 'suppliers.id', '=', 'expenses.supplier_id')
            ->where('suppliers.tenant_id', $tenantId)
            ->sum('expenses.total');

        $averagePurchase = DB::table('suppliers')
            ->join('expenses', 'suppliers.id', '=', 'expenses.supplier_id')
            ->where('suppliers.tenant_id', $tenantId)
            ->avg('expenses.total');

        $topSuppliers = Supplier::where('tenant_id', $tenantId)
            ->withSum('expenses', 'total')
            ->orderByDesc('expenses_sum_total')
            ->limit(5)
            ->get();

        return [
            'total_suppliers' => $supplierStats->total_suppliers,
            'active_suppliers' => $supplierStats->active_suppliers,
            'new_this_month' => $supplierStats->new_this_month,
            'preferred_suppliers' => $supplierStats->preferred_suppliers,
            'total_purchases' => $totalPurchases,
            'average_purchase' => $averagePurchase,
            'top_suppliers' => $topSuppliers,
        ];
    }

    public function createSupplier(array $data): Supplier
    {
        // Limpiar y formatear RUT
        $cleanRut = RutValidator::clean($data['rut']);
        
        // Verificar RUT único dentro del tenant
        $exists = Supplier::where('tenant_id', auth()->user()->tenant_id)
            ->where('rut', $cleanRut)
            ->exists();

        if ($exists) {
            throw new \Exception('El RUT ya existe en tu empresa.');
        }

        // Configurar categoría por defecto
        if (empty($data['category'])) {
            $data['category'] = 'standard';
        }

        // Reemplazar RUT con versión limpia
        $data['rut'] = $cleanRut;
        
        return Supplier::create([
            'tenant_id' => auth()->user()->tenant_id,
            ...$data,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => auth()->id(),
        ]);
    }

    public function updateSupplier(Supplier $supplier, array $data): Supplier
    {
        // Limpiar y formatear RUT
        $cleanRut = RutValidator::clean($data['rut']);
        
        // Verificar RUT único dentro del tenant
        $exists = Supplier::where('tenant_id', auth()->user()->tenant_id)
            ->where('rut', $cleanRut)
            ->where('id', '!=', $supplier->id)
            ->exists();

        if ($exists) {
            throw new \Exception('El RUT ya existe en tu empresa.');
        }
        
        // Reemplazar RUT con versión limpia
        $data['rut'] = $cleanRut;
        $data['updated_by'] = auth()->id();

        $supplier->update($data);
        
        return $supplier->fresh();
    }

    public function deleteSupplier(Supplier $supplier): array
    {
        // Verificar si tiene gastos asociados
        if ($supplier->expenses()->exists()) {
            return [
                'success' => false,
                'message' => 'No se puede eliminar el proveedor porque tiene gastos asociados.'
            ];
        }

        // Verificar si tiene órdenes de compra asociadas
        if ($supplier->purchaseOrders()->exists()) {
            return [
                'success' => false,
                'message' => 'No se puede eliminar el proveedor porque tiene órdenes de compra asociadas.'
            ];
        }

        $supplier->delete();

        return [
            'success' => true,
            'message' => 'Proveedor eliminado exitosamente.'
        ];
    }

    public function getSupplierDetails(Supplier $supplier): array
    {
        // Cargar gastos recientes
        $supplier->load(['expenses' => function ($query) {
            $query->with('category')
                  ->orderBy('expense_date', 'desc')
                  ->limit(10);
        }]);

        // Cargar órdenes de compra recientes
        $supplier->load(['purchaseOrders' => function ($query) {
            $query->with('items.product')
                  ->orderBy('order_date', 'desc')
                  ->limit(5);
        }]);

        // Estadísticas del proveedor
        $stats = [
            'total_expenses' => $supplier->expenses()->count(),
            'total_amount' => $supplier->expenses()->sum('total'),
            'total_purchase_orders' => $supplier->purchaseOrders()->count(),
            'pending_orders' => $supplier->purchaseOrders()
                ->whereIn('status', ['pending', 'partial'])
                ->count(),
            'average_expense' => $supplier->expenses()->avg('total'),
            'last_purchase' => $supplier->expenses()
                ->latest('expense_date')
                ->value('expense_date'),
            'payment_performance' => $this->calculatePaymentPerformance($supplier),
        ];

        // Categorías de gastos más frecuentes
        $topCategories = DB::table('expenses')
            ->join('categories', 'expenses.category_id', '=', 'categories.id')
            ->where('expenses.supplier_id', $supplier->id)
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('COUNT(*) as expense_count'),
                DB::raw('SUM(expenses.total) as total_amount')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        // Análisis de tendencias mensuales
        $monthlyTrends = $this->getMonthlyTrends($supplier);

        return [
            'supplier' => $supplier,
            'stats' => $stats,
            'topCategories' => $topCategories,
            'monthlyTrends' => $monthlyTrends,
        ];
    }

    public function generateSupplierStatement(Supplier $supplier, array $filters = []): array
    {
        $query = $supplier->expenses()
            ->with('category');

        // Filtros de fecha
        if (!empty($filters['date_from'])) {
            $query->where('expense_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('expense_date', '<=', $filters['date_to']);
        }

        $expenses = $query->orderBy('expense_date', 'desc')->get();

        $totalAmount = $expenses->sum('total');
        $paidAmount = $expenses->where('payment_status', 'paid')->sum('total');
        $pendingAmount = $expenses->where('payment_status', 'pending')->sum('total');

        return [
            'supplier' => $supplier,
            'expenses' => $expenses,
            'summary' => [
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'pending_amount' => $pendingAmount,
                'expense_count' => $expenses->count(),
            ],
            'filters' => $filters,
        ];
    }

    public function updatePaymentTerms(Supplier $supplier, int $paymentTermDays, string $reason): Supplier
    {
        $supplier->update([
            'payment_term_days' => $paymentTermDays,
            'payment_terms_updated_at' => now(),
            'payment_terms_updated_by' => auth()->id(),
            'payment_terms_reason' => $reason,
        ]);

        return $supplier->fresh();
    }

    public function updateCreditLimit(Supplier $supplier, float $creditLimit, string $reason): Supplier
    {
        $supplier->update([
            'credit_limit' => $creditLimit,
            'credit_limit_updated_at' => now(),
            'credit_limit_updated_by' => auth()->id(),
            'credit_limit_reason' => $reason,
        ]);

        return $supplier->fresh();
    }

    public function addSupplierNote(Supplier $supplier, string $note, string $type = 'general'): array
    {
        // Por ahora retornar un array simple
        // En una implementación completa, esto sería un modelo separado
        return [
            'id' => uniqid(),
            'supplier_id' => $supplier->id,
            'note' => $note,
            'type' => $type,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ];
    }

    public function getSupplierPerformance(Supplier $supplier): array
    {
        $orders = $supplier->purchaseOrders()
            ->where('status', 'completed')
            ->get();

        if ($orders->isEmpty()) {
            return [
                'delivery_performance' => 0,
                'quality_score' => 0,
                'price_competitiveness' => 0,
                'overall_rating' => 0,
            ];
        }

        $onTimeDeliveries = $orders->where('delivered_at', '<=', 'expected_delivery_date')->count();
        $deliveryPerformance = ($onTimeDeliveries / $orders->count()) * 100;

        // Calcular puntuación de calidad basada en devoluciones/reclamos
        $qualityScore = 85; // Valor por defecto, en una implementación real se calcularía

        // Calcular competitividad de precios
        $priceCompetitiveness = 75; // Valor por defecto

        $overallRating = ($deliveryPerformance + $qualityScore + $priceCompetitiveness) / 3;

        return [
            'delivery_performance' => round($deliveryPerformance, 2),
            'quality_score' => $qualityScore,
            'price_competitiveness' => $priceCompetitiveness,
            'overall_rating' => round($overallRating, 2),
            'total_orders' => $orders->count(),
            'on_time_deliveries' => $onTimeDeliveries,
        ];
    }

    public function getSupplierComparison(array $supplierIds): array
    {
        $suppliers = Supplier::where('tenant_id', auth()->user()->tenant_id)
            ->whereIn('id', $supplierIds)
            ->withSum('expenses', 'total')
            ->withCount('expenses')
            ->get();

        $comparison = [];

        foreach ($suppliers as $supplier) {
            $performance = $this->getSupplierPerformance($supplier);
            
            $comparison[] = [
                'supplier' => $supplier,
                'total_spent' => $supplier->expenses_sum_total ?? 0,
                'expense_count' => $supplier->expenses_count,
                'average_expense' => $supplier->expenses_count > 0 
                    ? ($supplier->expenses_sum_total / $supplier->expenses_count) 
                    : 0,
                'performance' => $performance,
            ];
        }

        return $comparison;
    }

    public function markAsPreferred(Supplier $supplier, string $reason): Supplier
    {
        $supplier->update([
            'category' => 'preferred',
            'preferred_since' => now(),
            'preferred_reason' => $reason,
            'updated_by' => auth()->id(),
        ]);

        return $supplier->fresh();
    }

    public function removeFromPreferred(Supplier $supplier, string $reason): Supplier
    {
        $supplier->update([
            'category' => 'standard',
            'preferred_since' => null,
            'preferred_reason' => null,
            'removal_reason' => $reason,
            'updated_by' => auth()->id(),
        ]);

        return $supplier->fresh();
    }

    private function calculatePaymentPerformance(Supplier $supplier): float
    {
        $totalExpenses = $supplier->expenses()->count();
        
        if ($totalExpenses === 0) {
            return 0;
        }

        $paidOnTime = $supplier->expenses()
            ->where('payment_status', 'paid')
            ->whereRaw('paid_at <= DATE_ADD(expense_date, INTERVAL payment_term_days DAY)')
            ->count();

        return ($paidOnTime / $totalExpenses) * 100;
    }

    private function getMonthlyTrends(Supplier $supplier): array
    {
        $trends = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            
            $monthlyTotal = $supplier->expenses()
                ->whereYear('expense_date', $date->year)
                ->whereMonth('expense_date', $date->month)
                ->sum('total');

            $trends[] = [
                'month' => $date->format('M Y'),
                'total' => $monthlyTotal,
            ];
        }

        return $trends;
    }
}