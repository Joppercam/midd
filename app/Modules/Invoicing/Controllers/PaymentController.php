<?php

namespace App\Modules\Invoicing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Customer;
use App\Models\TaxDocument;
use App\Traits\ChecksPermissions;
use App\Modules\Invoicing\Services\PaymentService;
use App\Modules\Invoicing\Requests\PaymentRequest;
use App\Modules\Invoicing\Requests\PaymentAllocationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class PaymentController extends Controller
{
    use ChecksPermissions;

    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:invoicing']);
        $this->paymentService = $paymentService;
    }

    public function index(Request $request)
    {
        $this->checkPermission('payments.view');
        
        $filters = $request->only([
            'search', 'customer_id', 'payment_method', 'status', 
            'date_from', 'date_to', 'amount_from', 'amount_to'
        ]);

        $result = $this->paymentService->getFilteredPayments($filters, $request->get('per_page', 20));

        $customers = Customer::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        return Inertia::render('Invoicing/Payments/Index', [
            'payments' => $result['payments'],
            'statistics' => $result['statistics'],
            'customers' => $customers,
            'filters' => $filters,
            'paymentMethods' => config('invoicing.payment_methods'),
        ]);
    }

    public function create()
    {
        $this->checkPermission('payments.create');
        
        $customers = Customer::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        return Inertia::render('Invoicing/Payments/Create', [
            'customers' => $customers,
            'paymentMethods' => config('invoicing.payment_methods'),
        ]);
    }

    public function store(PaymentRequest $request)
    {
        $this->checkPermission('payments.create');
        
        try {
            $payment = $this->paymentService->createPayment(
                $request->validated(),
                auth()->user()->tenant_id
            );

            return redirect()->route('invoicing.payments.index')
                ->with('success', 'Pago registrado exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(Payment $payment)
    {
        $this->checkPermission('payments.view');
        $this->authorize('view', $payment);
        
        $payment->load([
            'customer',
            'bankTransaction',
            'allocations.taxDocument',
            'allocations' => function ($query) {
                $query->orderBy('created_at');
            }
        ]);

        $availableDocuments = $this->paymentService->getAvailableDocumentsForPayment($payment);

        return Inertia::render('Invoicing/Payments/Show', [
            'payment' => $payment,
            'availableDocuments' => $availableDocuments,
        ]);
    }

    public function edit(Payment $payment)
    {
        $this->checkPermission('payments.edit');
        $this->authorize('update', $payment);
        
        if ($payment->status === 'reconciled') {
            return back()->withErrors(['error' => 'No se puede editar un pago ya conciliado.']);
        }
        
        $customers = Customer::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rut']);

        $payment->load(['allocations.taxDocument']);

        return Inertia::render('Invoicing/Payments/Edit', [
            'payment' => $payment,
            'customers' => $customers,
            'paymentMethods' => config('invoicing.payment_methods'),
        ]);
    }

    public function update(PaymentRequest $request, Payment $payment)
    {
        $this->checkPermission('payments.edit');
        $this->authorize('update', $payment);
        
        if ($payment->status === 'reconciled') {
            return back()->withErrors(['error' => 'No se puede modificar un pago ya conciliado.']);
        }

        try {
            $this->paymentService->updatePayment($payment, $request->validated());

            return redirect()->route('invoicing.payments.index')
                ->with('success', 'Pago actualizado exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function destroy(Payment $payment)
    {
        $this->checkPermission('payments.delete');
        $this->authorize('delete', $payment);
        
        if ($payment->status === 'reconciled') {
            return back()->withErrors(['error' => 'No se puede eliminar un pago ya conciliado.']);
        }

        try {
            $this->paymentService->deletePayment($payment);

            return redirect()->route('invoicing.payments.index')
                ->with('success', 'Pago eliminado exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function getUnpaidDocuments(Customer $customer)
    {
        $this->checkPermission('payments.view');
        $this->authorize('view', $customer);
        
        $documents = $this->paymentService->getUnpaidDocuments($customer);
        
        return response()->json($documents);
    }

    public function allocate(PaymentAllocationRequest $request, Payment $payment)
    {
        $this->checkPermission('payments.edit');
        $this->authorize('update', $payment);
        
        if ($payment->status === 'reconciled') {
            return back()->withErrors(['error' => 'No se puede modificar un pago ya conciliado.']);
        }

        try {
            $this->paymentService->allocatePayment(
                $payment,
                $request->validated()['allocations']
            );

            return redirect()->route('invoicing.payments.show', $payment)
                ->with('success', 'Asignaciones creadas exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function removeAllocation(Payment $payment, PaymentAllocation $allocation)
    {
        $this->checkPermission('payments.edit');
        $this->authorize('update', $payment);
        
        if ($payment->id !== $allocation->payment_id) {
            abort(404);
        }

        if ($payment->status === 'reconciled') {
            return back()->withErrors(['error' => 'No se puede modificar un pago ya conciliado.']);
        }

        try {
            $this->paymentService->removeAllocation($allocation);

            return redirect()->route('invoicing.payments.show', $payment)
                ->with('success', 'Asignación eliminada exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function void(Payment $payment)
    {
        $this->checkPermission('payments.edit');
        $this->authorize('update', $payment);
        
        if ($payment->status === 'reconciled') {
            return back()->withErrors(['error' => 'No se puede anular un pago ya conciliado.']);
        }

        try {
            $this->paymentService->voidPayment($payment);

            return redirect()->route('invoicing.payments.index')
                ->with('success', 'Pago anulado exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function export(Request $request)
    {
        $this->checkPermission('payments.export');
        
        $request->validate([
            'format' => 'required|in:excel,csv,pdf',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'customer_id' => 'nullable|exists:customers,id',
            'status' => 'nullable|in:pending,confirmed,cancelled,voided',
        ]);

        try {
            $export = $this->paymentService->exportPayments(
                $request->only(['date_from', 'date_to', 'customer_id', 'status']),
                $request->format
            );

            return $export;

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al generar la exportación: ' . $e->getMessage()]);
        }
    }

    public function bulkActions(Request $request)
    {
        $this->checkPermission('payments.edit');
        
        $request->validate([
            'action' => 'required|in:confirm,cancel,void',
            'payment_ids' => 'required|array|min:1',
            'payment_ids.*' => 'exists:payments,id',
        ]);

        try {
            $result = $this->paymentService->bulkActions(
                $request->payment_ids,
                $request->action,
                auth()->user()->tenant_id
            );

            return redirect()->route('invoicing.payments.index')
                ->with('success', "Acción '{$request->action}' aplicada a {$result['processed']} pagos. {$result['errors']} errores.");

        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reconciliationPreview(Request $request)
    {
        $this->checkPermission('payments.view');
        
        $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        try {
            $preview = $this->paymentService->getReconciliationPreview(
                $request->bank_account_id,
                $request->date_from,
                $request->date_to
            );

            return response()->json($preview);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}