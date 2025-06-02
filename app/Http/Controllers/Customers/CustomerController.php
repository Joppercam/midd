<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Services\WebhookService;
use App\Rules\ValidRut;
use App\Validators\RutValidator;

class CustomerController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request)
    {
        $this->checkPermission('customers.view');
        
        // Debug: Log para verificar que se ejecuta
        \Log::info('CustomerController@index ejecutado', [
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()->tenant_id,
            'request' => $request->all()
        ]);
        $query = Customer::where('tenant_id', auth()->user()->tenant_id);

        // Búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('rut', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filtro por tipo
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filtro por estado (removido ya que no existe columna active)

        // Ordenamiento
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Incluir estadísticas
        $query->withCount('taxDocuments')
              ->withSum('taxDocuments', 'total');

        $customers = $query->paginate(15)->withQueryString();

        // Estadísticas generales optimizadas (SQLite compatible)
        try {
            $currentMonth = now()->month;
            $currentYear = now()->year;
            $customerStats = Customer::where('tenant_id', auth()->user()->tenant_id)
                ->selectRaw("
                    COUNT(*) as total_customers,
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_customers,
                    COUNT(CASE WHEN strftime('%m', created_at) = ? AND strftime('%Y', created_at) = ? THEN 1 END) as new_this_month
                ", [sprintf('%02d', $currentMonth), $currentYear])
                ->first();
        } catch (\Exception $e) {
            \Log::error('Error en estadísticas de customers', [
                'error' => $e->getMessage(),
                'tenant_id' => auth()->user()->tenant_id
            ]);
            
            // Fallback con estadísticas básicas
            $customerStats = (object) [
                'total_customers' => 0,
                'active_customers' => 0,
                'new_this_month' => 0
            ];
        }

        try {
            $totalRevenue = DB::table('customers')
                ->join('tax_documents', 'customers.id', '=', 'tax_documents.customer_id')
                ->where('customers.tenant_id', auth()->user()->tenant_id)
                ->where('tax_documents.status', 'accepted')
                ->sum('tax_documents.total');
        } catch (\Exception $e) {
            \Log::error('Error en revenue de customers', [
                'error' => $e->getMessage(),
                'tenant_id' => auth()->user()->tenant_id
            ]);
            $totalRevenue = 0;
        }

        $stats = [
            'total_customers' => $customerStats->total_customers,
            'active_customers' => $customerStats->active_customers,
            'total_revenue' => $totalRevenue,
            'new_this_month' => $customerStats->new_this_month,
        ];

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search', 'type', 'is_active', 'sort', 'direction']),
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        $this->checkPermission('customers.create');
        return Inertia::render('Customers/Create');
    }

    public function store(Request $request)
    {
        $this->checkPermission('customers.create');
        $validated = $request->validate([
            'rut' => ['required', 'string', 'max:20', new ValidRut()],
            'name' => 'required|string|max:255',
            'type' => 'required|in:person,company',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'commune' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'business_activity' => 'nullable|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_term_days' => 'nullable|integer|min:0|max:365',
        ]);

        // Limpiar y formatear RUT
        $cleanRut = RutValidator::clean($validated['rut']);
        
        // Verificar RUT único dentro del tenant
        $exists = Customer::where('tenant_id', auth()->user()->tenant_id)
            ->where('rut', $cleanRut)
            ->exists();

        if ($exists) {
            return back()->withErrors(['rut' => 'El RUT ya existe en tu empresa.']);
        }

        // Reemplazar RUT con versión limpia
        $validated['rut'] = $cleanRut;
        
        $customer = Customer::create([
            'tenant_id' => auth()->user()->tenant_id,
            ...$validated,
            'is_active' => true,
        ]);

        // Dispatch webhook
        app(WebhookService::class)->dispatch('customer.created', [
            'customer' => $customer->toArray(),
        ], auth()->user()->tenant_id);

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Cliente creado exitosamente.');
    }

    public function show(Customer $customer)
    {
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

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

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'stats' => $stats,
            'topProducts' => $topProducts,
        ]);
    }

    public function edit(Customer $customer)
    {
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'rut' => ['required', 'string', 'max:20', new ValidRut()],
            'name' => 'required|string|max:255',
            'type' => 'required|in:person,company',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'commune' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'business_activity' => 'nullable|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_term_days' => 'nullable|integer|min:0|max:365',
            'is_active' => 'required|boolean',
        ]);

        // Limpiar y formatear RUT
        $cleanRut = RutValidator::clean($validated['rut']);
        
        // Verificar RUT único dentro del tenant
        $exists = Customer::where('tenant_id', auth()->user()->tenant_id)
            ->where('rut', $cleanRut)
            ->where('id', '!=', $customer->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['rut' => 'El RUT ya existe en tu empresa.']);
        }
        
        // Reemplazar RUT con versión limpia
        $validated['rut'] = $cleanRut;

        $customer->update($validated);

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Cliente actualizado exitosamente.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        // Verificar si tiene documentos asociados
        if ($customer->taxDocuments()->exists()) {
            return back()->with('error', 'No se puede eliminar el cliente porque tiene documentos asociados.');
        }

        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Cliente eliminado exitosamente.');
    }

    public function statement(Customer $customer)
    {
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

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

        return Inertia::render('Customers/Statement', [
            'customer' => $customer,
            'transactions' => $transactions,
            'currentBalance' => $balance,
        ]);
    }
}