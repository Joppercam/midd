<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Customer;
use App\Models\TaxDocument;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class PaymentController extends Controller
{
    use ChecksPermissions;
    public function index(Request $request)
    {
        $this->checkPermission('payments.view');
        $tenantId = auth()->user()->tenant_id;
        
        $query = Payment::where('tenant_id', $tenantId)
            ->with(['customer', 'allocations.taxDocument']);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('rut', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        $payments = $query->orderBy('payment_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Estadísticas
        $statistics = [
            'total_amount' => Payment::where('tenant_id', $tenantId)
                ->where('status', 'confirmed')
                ->sum('amount'),
            'pending_amount' => Payment::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->sum('amount'),
            'this_month' => Payment::where('tenant_id', $tenantId)
                ->where('status', 'confirmed')
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount'),
            'unallocated_amount' => Payment::where('tenant_id', $tenantId)
                ->where('status', 'confirmed')
                ->where('remaining_amount', '>', 0)
                ->sum('remaining_amount')
        ];

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'statistics' => $statistics,
            'customers' => $customers,
            'filters' => $request->all(['search', 'customer_id', 'payment_method', 'status', 'date_from', 'date_to'])
        ]);
    }

    public function create()
    {
        $this->checkPermission('payments.create');
        $tenantId = auth()->user()->tenant_id;
        
        $customers = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        return Inertia::render('Payments/Create', [
            'customers' => $customers
        ]);
    }

    public function store(Request $request)
    {
        $this->checkPermission('payments.create');
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,bank_transfer,check,credit_card,debit_card,electronic,other',
            'reference' => 'nullable|string|max:255',
            'bank' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:pending,confirmed,rejected,cancelled',
            'allocations' => 'nullable|array',
            'allocations.*.tax_document_id' => 'required|exists:tax_documents,id',
            'allocations.*.amount' => 'required|numeric|min:0.01'
        ]);

        $tenantId = auth()->user()->tenant_id;

        // Verificar que el cliente pertenece al tenant
        $customer = Customer::where('tenant_id', $tenantId)
            ->findOrFail($request->customer_id);

        // Validar que la suma de asignaciones no exceda el monto del pago
        $totalAllocations = collect($request->allocations ?? [])->sum('amount');
        if ($totalAllocations > $request->amount) {
            return back()->withErrors(['allocations' => 'La suma de asignaciones no puede exceder el monto del pago.']);
        }

        DB::transaction(function () use ($request, $tenantId, $totalAllocations) {
            // Crear el pago
            $payment = Payment::create([
                'number' => Payment::generateNumber($tenantId),
                'tenant_id' => $tenantId,
                'customer_id' => $request->customer_id,
                'payment_date' => $request->payment_date,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'reference' => $request->reference,
                'bank' => $request->bank,
                'description' => $request->description,
                'status' => $request->status,
                'remaining_amount' => $request->amount - $totalAllocations
            ]);

            // Crear las asignaciones
            if ($request->allocations) {
                foreach ($request->allocations as $allocation) {
                    // Verificar que el documento pertenece al mismo cliente y tenant
                    $document = TaxDocument::where('tenant_id', $tenantId)
                        ->where('customer_id', $request->customer_id)
                        ->where('balance', '>', 0)
                        ->findOrFail($allocation['tax_document_id']);

                    PaymentAllocation::create([
                        'payment_id' => $payment->id,
                        'tax_document_id' => $allocation['tax_document_id'],
                        'amount' => $allocation['amount'],
                        'notes' => $allocation['notes'] ?? null
                    ]);
                }
            }
        });

        return redirect()->route('payments.index')
            ->with('success', 'Pago registrado exitosamente.');
    }

    public function show(Payment $payment)
    {
        $this->checkPermission('payments.view');
        
        if ($payment->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $payment->load([
            'customer',
            'allocations.taxDocument',
            'allocations' => function ($query) {
                $query->orderBy('created_at');
            }
        ]);

        return Inertia::render('Payments/Show', [
            'payment' => $payment
        ]);
    }

    public function edit(Payment $payment)
    {
        $this->checkPermission('payments.edit');
        
        if ($payment->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $tenantId = auth()->user()->tenant_id;
        
        $customers = Customer::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        $payment->load(['allocations.taxDocument']);

        return Inertia::render('Payments/Edit', [
            'payment' => $payment,
            'customers' => $customers
        ]);
    }

    public function update(Request $request, Payment $payment)
    {
        $this->checkPermission('payments.edit');
        
        if ($payment->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:cash,bank_transfer,check,credit_card,debit_card,electronic,other',
            'reference' => 'nullable|string|max:255',
            'bank' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'status' => 'required|in:pending,confirmed,rejected,cancelled'
        ]);

        $tenantId = auth()->user()->tenant_id;

        // Verificar que el cliente pertenece al tenant
        $customer = Customer::where('tenant_id', $tenantId)
            ->findOrFail($request->customer_id);

        $payment->update([
            'customer_id' => $request->customer_id,
            'payment_date' => $request->payment_date,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'reference' => $request->reference,
            'bank' => $request->bank,
            'description' => $request->description,
            'status' => $request->status
        ]);

        // Recalcular monto restante
        $payment->updateRemainingAmount();

        return redirect()->route('payments.index')
            ->with('success', 'Pago actualizado exitosamente.');
    }

    public function destroy(Payment $payment)
    {
        $this->checkPermission('payments.delete');
        
        if ($payment->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        DB::transaction(function () use ($payment) {
            // Eliminar asignaciones (esto actualizará automáticamente los saldos)
            $payment->allocations()->delete();
            
            // Eliminar el pago
            $payment->delete();
        });

        return redirect()->route('payments.index')
            ->with('success', 'Pago eliminado exitosamente.');
    }

    public function getUnpaidDocuments(Customer $customer)
    {
        $this->checkPermission('payments.view');
        $tenantId = auth()->user()->tenant_id;
        
        $documents = TaxDocument::where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->whereIn('document_type', ['invoice', 'debit_note'])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->where('balance', '>', 0)
            ->orderBy('issue_date')
            ->get(['id', 'formatted_number', 'document_type', 'issue_date', 'due_date', 'total_amount', 'balance']);

        return response()->json($documents);
    }

    public function allocate(Request $request, Payment $payment)
    {
        $this->checkPermission('payments.edit');
        
        if ($payment->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $request->validate([
            'allocations' => 'required|array|min:1',
            'allocations.*.tax_document_id' => 'required|exists:tax_documents,id',
            'allocations.*.amount' => 'required|numeric|min:0.01'
        ]);

        $tenantId = auth()->user()->tenant_id;
        
        // Validar que la suma de asignaciones no exceda el monto disponible
        $totalAllocations = collect($request->allocations)->sum('amount');
        if ($totalAllocations > $payment->remaining_amount) {
            return back()->withErrors(['allocations' => 'La suma de asignaciones no puede exceder el monto disponible del pago.']);
        }

        DB::transaction(function () use ($request, $payment, $tenantId) {
            foreach ($request->allocations as $allocation) {
                // Verificar que el documento pertenece al mismo cliente y tenant
                $document = TaxDocument::where('tenant_id', $tenantId)
                    ->where('customer_id', $payment->customer_id)
                    ->where('balance', '>', 0)
                    ->findOrFail($allocation['tax_document_id']);

                // Verificar que no existe ya una asignación para este documento
                $existingAllocation = PaymentAllocation::where('payment_id', $payment->id)
                    ->where('tax_document_id', $allocation['tax_document_id'])
                    ->first();

                if ($existingAllocation) {
                    continue; // Saltar si ya existe
                }

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'tax_document_id' => $allocation['tax_document_id'],
                    'amount' => $allocation['amount'],
                    'notes' => $allocation['notes'] ?? null
                ]);
            }
        });

        return redirect()->route('payments.show', $payment)
            ->with('success', 'Asignaciones creadas exitosamente.');
    }
}