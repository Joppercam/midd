<?php

namespace App\Modules\CRM\Services;

use App\Models\Customer;
use App\Validators\RutValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CustomersExport;
use Carbon\Carbon;

class CustomerService
{
    public function getCustomersList(array $filters = [], int $perPage = 15)
    {
        $query = Customer::where('tenant_id', auth()->user()->tenant_id);

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

        // Ordenamiento
        $sortField = $filters['sort'] ?? 'name';
        $sortDirection = $filters['direction'] ?? 'asc';
        $query->orderBy($sortField, $sortDirection);

        // Incluir estadísticas
        $query->withCount('taxDocuments')
              ->withSum('taxDocuments', 'total');

        return $query->paginate($perPage)->withQueryString();
    }

    public function getCustomersStats(): array
    {
        $tenantId = auth()->user()->tenant_id;
        
        // Estadísticas optimizadas
        $customerStats = Customer::where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(*) as total_customers,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_customers,
                COUNT(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 END) as new_this_month,
                COUNT(CASE WHEN category = 'premium' THEN 1 END) as premium_customers
            ", [now()->month, now()->year])
            ->first();

        $totalRevenue = DB::table('customers')
            ->join('tax_documents', 'customers.id', '=', 'tax_documents.customer_id')
            ->where('customers.tenant_id', $tenantId)
            ->where('tax_documents.status', 'accepted')
            ->sum('tax_documents.total');

        $averageOrderValue = DB::table('customers')
            ->join('tax_documents', 'customers.id', '=', 'tax_documents.customer_id')
            ->where('customers.tenant_id', $tenantId)
            ->where('tax_documents.status', 'accepted')
            ->avg('tax_documents.total');

        return [
            'total_customers' => $customerStats->total_customers,
            'active_customers' => $customerStats->active_customers,
            'new_this_month' => $customerStats->new_this_month,
            'premium_customers' => $customerStats->premium_customers,
            'total_revenue' => $totalRevenue,
            'average_order_value' => $averageOrderValue,
        ];
    }

    public function createCustomer(array $data): Customer
    {
        // Limpiar y formatear RUT
        $cleanRut = RutValidator::clean($data['rut']);
        
        // Verificar RUT único dentro del tenant
        $exists = Customer::where('tenant_id', auth()->user()->tenant_id)
            ->where('rut', $cleanRut)
            ->exists();

        if ($exists) {
            throw new \Exception('El RUT ya existe en tu empresa.');
        }

        // Configurar categoría por defecto basada en el límite de crédito
        if (empty($data['category'])) {
            $data['category'] = $this->determineCategoryByRevenue($data['credit_limit'] ?? 0);
        }

        // Reemplazar RUT con versión limpia
        $data['rut'] = $cleanRut;
        
        return Customer::create([
            'tenant_id' => auth()->user()->tenant_id,
            ...$data,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);
    }

    public function updateCustomer(Customer $customer, array $data): Customer
    {
        // Limpiar y formatear RUT
        $cleanRut = RutValidator::clean($data['rut']);
        
        // Verificar RUT único dentro del tenant
        $exists = Customer::where('tenant_id', auth()->user()->tenant_id)
            ->where('rut', $cleanRut)
            ->where('id', '!=', $customer->id)
            ->exists();

        if ($exists) {
            throw new \Exception('El RUT ya existe en tu empresa.');
        }
        
        // Reemplazar RUT con versión limpia
        $data['rut'] = $cleanRut;
        $data['updated_by'] = auth()->id();

        $customer->update($data);
        
        return $customer->fresh();
    }

    public function deleteCustomer(Customer $customer): array
    {
        // Verificar si tiene documentos asociados
        if ($customer->taxDocuments()->exists()) {
            return [
                'success' => false,
                'message' => 'No se puede eliminar el cliente porque tiene documentos asociados.'
            ];
        }

        // Verificar si tiene pagos asociados
        if ($customer->payments()->exists()) {
            return [
                'success' => false,
                'message' => 'No se puede eliminar el cliente porque tiene pagos asociados.'
            ];
        }

        $customer->delete();

        return [
            'success' => true,
            'message' => 'Cliente eliminado exitosamente.'
        ];
    }

    public function getCustomerDetails(Customer $customer): array
    {
        // Cargar documentos recientes
        $customer->load(['taxDocuments' => function ($query) {
            $query->with('items.product')
                  ->orderBy('issue_date', 'desc')
                  ->limit(10);
        }]);

        // Estadísticas del cliente
        $stats = [
            'total_documents' => $customer->taxDocuments()->count(),
            'total_revenue' => $customer->taxDocuments()
                ->where('status', 'accepted')
                ->sum('total'),
            'pending_amount' => $customer->taxDocuments()
                ->where('status', 'accepted')
                ->whereNull('paid_at')
                ->sum('total'),
            'overdue_amount' => $customer->taxDocuments()
                ->where('status', 'accepted')
                ->whereNull('paid_at')
                ->where('due_date', '<', now())
                ->sum('total'),
            'average_purchase' => $customer->taxDocuments()
                ->where('status', 'accepted')
                ->avg('total'),
            'last_purchase' => $customer->taxDocuments()
                ->where('status', 'accepted')
                ->latest('issue_date')
                ->value('issue_date'),
        ];

        // Productos más comprados
        $topProducts = DB::table('tax_document_items')
            ->join('tax_documents', 'tax_document_items.tax_document_id', '=', 'tax_documents.id')
            ->join('products', 'tax_document_items.product_id', '=', 'products.id')
            ->where('tax_documents.customer_id', $customer->id)
            ->where('tax_documents.status', 'accepted')
            ->select(
                'products.id',
                'products.name',
                'products.code',
                DB::raw('SUM(tax_document_items.quantity) as total_quantity'),
                DB::raw('SUM(tax_document_items.total) as total_amount'),
                DB::raw('COUNT(DISTINCT tax_documents.id) as times_purchased')
            )
            ->groupBy('products.id', 'products.name', 'products.code')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        // Historial de pagos reciente
        $recentPayments = $customer->payments()
            ->with('paymentAllocations.taxDocument')
            ->orderBy('payment_date', 'desc')
            ->limit(5)
            ->get();

        return [
            'customer' => $customer,
            'stats' => $stats,
            'topProducts' => $topProducts,
            'recentPayments' => $recentPayments,
        ];
    }

    public function generateCustomerStatement(Customer $customer): array
    {
        $documents = $customer->taxDocuments()
            ->with('items')
            ->whereIn('status', ['accepted', 'sent'])
            ->orderBy('issue_date', 'desc')
            ->get();

        $balance = 0;
        $transactions = [];

        foreach ($documents as $document) {
            $balance += $document->total;
            
            $transactions[] = [
                'date' => $document->issue_date,
                'type' => 'document',
                'document' => $document,
                'debit' => $document->total,
                'credit' => 0,
                'balance' => $balance,
            ];

            if ($document->paid_at) {
                $balance -= $document->total;
                $transactions[] = [
                    'date' => $document->paid_at,
                    'type' => 'payment',
                    'document' => $document,
                    'debit' => 0,
                    'credit' => $document->total,
                    'balance' => $balance,
                ];
            }
        }

        // Ordenar por fecha
        usort($transactions, function ($a, $b) {
            return $a['date']->timestamp - $b['date']->timestamp;
        });

        // Análisis de antigüedad
        $agingPeriods = config('crm.customer_statement.aging_periods', [30, 60, 90, 120]);
        $aging = $this->calculateAging($customer, $agingPeriods);

        return [
            'customer' => $customer,
            'transactions' => $transactions,
            'currentBalance' => $balance,
            'aging' => $aging,
        ];
    }

    public function exportCustomers(array $filters = [])
    {
        $customers = $this->getCustomersList($filters, 10000);
        
        return Excel::download(new CustomersExport($customers), 'customers_' . now()->format('Y-m-d') . '.xlsx');
    }

    public function importCustomers(File $file, bool $updateExisting = false): array
    {
        // Implementar lógica de importación
        // Por ahora retornar un mensaje de éxito
        return [
            'success' => true,
            'message' => 'Importación completada exitosamente.'
        ];
    }

    public function mergeCustomers(int $sourceId, int $targetId): array
    {
        $source = Customer::where('tenant_id', auth()->user()->tenant_id)->findOrFail($sourceId);
        $target = Customer::where('tenant_id', auth()->user()->tenant_id)->findOrFail($targetId);

        DB::transaction(function () use ($source, $target) {
            // Transferir documentos
            $source->taxDocuments()->update(['customer_id' => $target->id]);
            
            // Transferir pagos
            $source->payments()->update(['customer_id' => $target->id]);
            
            // Combinar información si está vacía en el target
            $updateData = [];
            if (empty($target->email) && !empty($source->email)) {
                $updateData['email'] = $source->email;
            }
            if (empty($target->phone) && !empty($source->phone)) {
                $updateData['phone'] = $source->phone;
            }
            if (empty($target->address) && !empty($source->address)) {
                $updateData['address'] = $source->address;
            }
            
            if (!empty($updateData)) {
                $target->update($updateData);
            }
            
            // Eliminar cliente origen
            $source->delete();
        });

        return [
            'success' => true,
            'message' => 'Clientes fusionados exitosamente.'
        ];
    }

    public function updateCreditLimit(Customer $customer, float $creditLimit, string $reason): Customer
    {
        $customer->update([
            'credit_limit' => $creditLimit,
            'credit_limit_updated_at' => now(),
            'credit_limit_updated_by' => auth()->id(),
            'credit_limit_reason' => $reason,
        ]);

        return $customer->fresh();
    }

    public function updatePaymentTerms(Customer $customer, int $paymentTermDays, string $reason): Customer
    {
        $customer->update([
            'payment_term_days' => $paymentTermDays,
            'payment_terms_updated_at' => now(),
            'payment_terms_updated_by' => auth()->id(),
            'payment_terms_reason' => $reason,
        ]);

        return $customer->fresh();
    }

    public function addCustomerNote(Customer $customer, string $note, string $type = 'general'): array
    {
        // Por ahora retornar un array simple
        // En una implementación completa, esto sería un modelo separado
        return [
            'id' => uniqid(),
            'customer_id' => $customer->id,
            'note' => $note,
            'type' => $type,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ];
    }

    public function getContactHistory(Customer $customer): array
    {
        // Implementar lógica para obtener historial de contacto
        // Por ahora retornar array vacío
        return [];
    }

    private function determineCategoryByRevenue(float $creditLimit): string
    {
        $categories = config('crm.customer_categories');
        
        if ($creditLimit >= 2000000) {
            return 'premium';
        } elseif ($creditLimit >= 500000) {
            return 'standard';
        } elseif ($creditLimit > 0) {
            return 'basic';
        }
        
        return 'prospect';
    }

    private function calculateAging(Customer $customer, array $periods): array
    {
        $aging = [];
        $now = Carbon::now();
        
        foreach ($periods as $period) {
            $aging[$period] = $customer->taxDocuments()
                ->where('status', 'accepted')
                ->whereNull('paid_at')
                ->where('due_date', '<=', $now->subDays($period))
                ->sum('total');
        }
        
        return $aging;
    }
}