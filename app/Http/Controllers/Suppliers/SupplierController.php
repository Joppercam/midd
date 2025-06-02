<?php

namespace App\Http\Controllers\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    use ChecksPermissions;
    public function index(Request $request)
    {
        $this->checkPermission('suppliers.view');
        $tenantId = auth()->user()->tenant_id;
        
        $query = Supplier::where('suppliers.tenant_id', $tenantId)
            ->withSum(['expenses as total_expenses' => function ($query) use ($tenantId) {
                $query->where('expenses.tenant_id', $tenantId)
                      ->whereNotIn('status', ['cancelled', 'draft']);
            }], 'total_amount')
            ->withSum(['expenses as pending_balance' => function ($query) use ($tenantId) {
                $query->where('expenses.tenant_id', $tenantId)
                      ->where('status', 'pending');
            }], 'balance');

        // Filtros
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $suppliers = $query->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Estadísticas
        $statistics = [
            'total_suppliers' => Supplier::where('tenant_id', $tenantId)->count(),
            'active_suppliers' => Supplier::where('tenant_id', $tenantId)->active()->count(),
            'total_debt' => Supplier::withoutGlobalScope('tenant')
                ->where('suppliers.tenant_id', $tenantId)
                ->join('expenses', 'suppliers.id', '=', 'expenses.supplier_id')
                ->where('expenses.status', 'pending')
                ->where('expenses.tenant_id', $tenantId)
                ->sum('expenses.balance'),
            'this_month_expenses' => Supplier::withoutGlobalScope('tenant')
                ->where('suppliers.tenant_id', $tenantId)
                ->join('expenses', 'suppliers.id', '=', 'expenses.supplier_id')
                ->where('expenses.tenant_id', $tenantId)
                ->whereMonth('expenses.issue_date', now()->month)
                ->whereYear('expenses.issue_date', now()->year)
                ->whereNotIn('expenses.status', ['cancelled', 'draft'])
                ->sum('expenses.total_amount')
        ];

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'statistics' => $statistics,
            'filters' => $request->all(['search', 'type', 'status'])
        ]);
    }

    public function create()
    {
        $this->checkPermission('suppliers.create');
        return Inertia::render('Suppliers/Create');
    }

    public function store(Request $request)
    {
        $this->checkPermission('suppliers.create');
        $request->validate([
            'rut' => 'required|string|unique:suppliers,rut',
            'name' => 'required|string|max:255',
            'type' => 'required|in:person,company',
            'business_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'commune' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'payment_terms' => 'required|in:immediate,15_days,30_days,60_days,90_days',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean'
        ]);

        // Validar RUT
        if (!Supplier::validateRut($request->rut)) {
            return back()->withErrors(['rut' => 'El RUT ingresado no es válido.']);
        }

        $tenantId = auth()->user()->tenant_id;

        Supplier::create([
            'tenant_id' => $tenantId,
            'rut' => $request->rut,
            'name' => $request->name,
            'type' => $request->type,
            'business_name' => $request->business_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
            'commune' => $request->commune,
            'region' => $request->region,
            'payment_terms' => $request->payment_terms,
            'notes' => $request->notes,
            'is_active' => $request->boolean('is_active', true)
        ]);

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedor creado exitosamente.');
    }

    public function show(Supplier $supplier)
    {
        $this->checkPermission('suppliers.view');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $supplier->load([
            'expenses' => function ($query) {
                $query->orderBy('issue_date', 'desc')->limit(10);
            }
        ]);

        // Estadísticas del proveedor
        $statistics = [
            'total_expenses' => $supplier->expenses()->whereNotIn('status', ['cancelled', 'draft'])->sum('total_amount'),
            'pending_balance' => $supplier->expenses()->where('status', 'pending')->sum('balance'),
            'paid_amount' => $supplier->expenses()->where('status', 'paid')->sum('total_amount'),
            'expense_count' => $supplier->expenses()->whereNotIn('status', ['cancelled', 'draft'])->count(),
            'overdue_amount' => $supplier->expenses()->overdue()->sum('balance')
        ];

        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
            'statistics' => $statistics
        ]);
    }

    public function edit(Supplier $supplier)
    {
        $this->checkPermission('suppliers.edit');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $this->checkPermission('suppliers.edit');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $request->validate([
            'rut' => 'required|string|unique:suppliers,rut,' . $supplier->id,
            'name' => 'required|string|max:255',
            'type' => 'required|in:person,company',
            'business_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'commune' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'payment_terms' => 'required|in:immediate,15_days,30_days,60_days,90_days',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean'
        ]);

        // Validar RUT
        if (!Supplier::validateRut($request->rut)) {
            return back()->withErrors(['rut' => 'El RUT ingresado no es válido.']);
        }

        $supplier->update([
            'rut' => $request->rut,
            'name' => $request->name,
            'type' => $request->type,
            'business_name' => $request->business_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'city' => $request->city,
            'commune' => $request->commune,
            'region' => $request->region,
            'payment_terms' => $request->payment_terms,
            'notes' => $request->notes,
            'is_active' => $request->boolean('is_active', true)
        ]);

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedor actualizado exitosamente.');
    }

    public function destroy(Supplier $supplier)
    {
        $this->checkPermission('suppliers.delete');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        // Verificar si tiene gastos asociados
        if ($supplier->expenses()->count() > 0) {
            return back()->withErrors(['supplier' => 'No se puede eliminar el proveedor porque tiene gastos asociados.']);
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedor eliminado exitosamente.');
    }
}