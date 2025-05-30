<?php

namespace App\Modules\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Traits\ChecksPermissions;
use App\Modules\CRM\Services\CustomerService;
use App\Modules\CRM\Requests\CustomerRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Services\WebhookService;

class CustomerController extends Controller
{
    use ChecksPermissions;

    protected $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:crm']);
        $this->customerService = $customerService;
    }

    public function index(Request $request)
    {
        $this->checkPermission('customers.view');
        
        $filters = $request->only(['search', 'type', 'category', 'sort', 'direction']);
        $customers = $this->customerService->getCustomersList($filters);
        $stats = $this->customerService->getCustomersStats();

        return Inertia::render('CRM/Customers/Index', [
            'customers' => $customers,
            'filters' => $filters,
            'stats' => $stats,
            'categories' => config('crm.customer_categories'),
        ]);
    }

    public function create()
    {
        $this->checkPermission('customers.create');
        
        return Inertia::render('CRM/Customers/Create', [
            'categories' => config('crm.customer_categories'),
            'communicationPreferences' => config('crm.communication_preferences'),
        ]);
    }

    public function store(CustomerRequest $request)
    {
        $this->checkPermission('customers.create');
        
        $customer = $this->customerService->createCustomer($request->validated());

        // Dispatch webhook
        app(WebhookService::class)->dispatch('customer.created', [
            'customer' => $customer->toArray(),
        ], auth()->user()->tenant_id);

        return redirect()->route('crm.customers.show', $customer)
            ->with('success', 'Cliente creado exitosamente.');
    }

    public function show(Customer $customer)
    {
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $customerData = $this->customerService->getCustomerDetails($customer);
        
        return Inertia::render('CRM/Customers/Show', $customerData);
    }

    public function edit(Customer $customer)
    {
        $this->checkPermission('customers.edit');
        
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        return Inertia::render('CRM/Customers/Edit', [
            'customer' => $customer,
            'categories' => config('crm.customer_categories'),
            'communicationPreferences' => config('crm.communication_preferences'),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        $this->checkPermission('customers.edit');
        
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $customer = $this->customerService->updateCustomer($customer, $request->validated());

        // Dispatch webhook
        app(WebhookService::class)->dispatch('customer.updated', [
            'customer' => $customer->toArray(),
        ], auth()->user()->tenant_id);

        return redirect()->route('crm.customers.show', $customer)
            ->with('success', 'Cliente actualizado exitosamente.');
    }

    public function destroy(Customer $customer)
    {
        $this->checkPermission('customers.delete');
        
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $result = $this->customerService->deleteCustomer($customer);
        
        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('crm.customers.index')
            ->with('success', 'Cliente eliminado exitosamente.');
    }

    public function statement(Customer $customer)
    {
        $this->checkPermission('customers.statements');
        
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $statementData = $this->customerService->generateCustomerStatement($customer);

        return Inertia::render('CRM/Customers/Statement', $statementData);
    }

    public function export(Request $request)
    {
        $this->checkPermission('customers.export');
        
        $filters = $request->only(['search', 'type', 'category']);
        
        return $this->customerService->exportCustomers($filters);
    }

    public function import(Request $request)
    {
        $this->checkPermission('customers.import');
        
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx',
            'update_existing' => 'boolean'
        ]);

        $result = $this->customerService->importCustomers(
            $request->file('file'),
            $request->boolean('update_existing', false)
        );

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function merge(Request $request)
    {
        $this->checkPermission('customers.merge');
        
        $request->validate([
            'source_id' => 'required|exists:customers,id',
            'target_id' => 'required|exists:customers,id|different:source_id'
        ]);

        $result = $this->customerService->mergeCustomers(
            $request->integer('source_id'),
            $request->integer('target_id')
        );

        return response()->json($result);
    }

    public function updateCreditLimit(Request $request, Customer $customer)
    {
        $this->checkPermission('customers.credit_limits');
        
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'credit_limit' => 'required|numeric|min:0',
            'reason' => 'required|string|max:500'
        ]);

        $customer = $this->customerService->updateCreditLimit(
            $customer,
            $request->get('credit_limit'),
            $request->get('reason')
        );

        return response()->json([
            'success' => true,
            'customer' => $customer,
            'message' => 'Límite de crédito actualizado exitosamente.'
        ]);
    }

    public function updatePaymentTerms(Request $request, Customer $customer)
    {
        $this->checkPermission('customers.payment_terms');
        
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'payment_term_days' => 'required|integer|min:0|max:365',
            'reason' => 'required|string|max:500'
        ]);

        $customer = $this->customerService->updatePaymentTerms(
            $customer,
            $request->get('payment_term_days'),
            $request->get('reason')
        );

        return response()->json([
            'success' => true,
            'customer' => $customer,
            'message' => 'Condiciones de pago actualizadas exitosamente.'
        ]);
    }

    public function addNote(Request $request, Customer $customer)
    {
        $this->checkPermission('customers.notes');
        
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'note' => 'required|string|max:1000',
            'type' => 'required|in:general,sales,support,finance'
        ]);

        $note = $this->customerService->addCustomerNote(
            $customer,
            $request->get('note'),
            $request->get('type')
        );

        return response()->json([
            'success' => true,
            'note' => $note,
            'message' => 'Nota agregada exitosamente.'
        ]);
    }

    public function getContactHistory(Customer $customer)
    {
        $this->checkPermission('customers.history');
        
        if ($customer->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $history = $this->customerService->getContactHistory($customer);

        return response()->json([
            'success' => true,
            'history' => $history
        ]);
    }
}