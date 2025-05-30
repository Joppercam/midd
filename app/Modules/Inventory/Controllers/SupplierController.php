<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Traits\ChecksPermissions;
use App\Modules\Inventory\Services\SupplierService;
use App\Modules\Inventory\Requests\SupplierRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    use ChecksPermissions;
    
    protected $supplierService;
    
    public function __construct(SupplierService $supplierService)
    {
        $this->middleware(['auth', 'verified', 'check.subscription', 'check.module:inventory']);
        $this->supplierService = $supplierService;
    }
    
    public function index(Request $request)
    {
        $this->checkPermission('suppliers.view');
        
        $filters = $request->only(['search', 'type', 'status', 'payment_terms', 'rating']);
        $suppliers = $this->supplierService->getSuppliersList($filters);
        $statistics = $this->supplierService->getSuppliersStatistics();

        return Inertia::render('Inventory/Suppliers/Index', [
            'suppliers' => $suppliers,
            'statistics' => $statistics,
            'filters' => $filters,
            'paymentTermsOptions' => config('inventory.purchase_order_settings.payment_terms_options', []),
            'evaluationCriteria' => config('inventory.supplier_settings.evaluation_criteria', [])
        ]);
    }

    public function create()
    {
        $this->checkPermission('suppliers.create');
        
        return Inertia::render('Inventory/Suppliers/Create', [
            'paymentTermsOptions' => $this->supplierService->getPaymentTermsOptions(),
            'supplierCategories' => $this->supplierService->getSupplierCategories()
        ]);
    }

    public function store(SupplierRequest $request)
    {
        $this->checkPermission('suppliers.create');
        
        $supplier = $this->supplierService->createSupplier($request->validated());

        return redirect()->route('inventory.suppliers.show', $supplier)
            ->with('success', 'Proveedor creado exitosamente.');
    }

    public function show(Supplier $supplier)
    {
        $this->checkPermission('suppliers.view');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $supplierData = $this->supplierService->getSupplierDetails($supplier);
        
        return Inertia::render('Inventory/Suppliers/Show', $supplierData);
    }

    public function edit(Supplier $supplier)
    {
        $this->checkPermission('suppliers.edit');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        return Inertia::render('Inventory/Suppliers/Edit', [
            'supplier' => $supplier,
            'paymentTermsOptions' => $this->supplierService->getPaymentTermsOptions(),
            'supplierCategories' => $this->supplierService->getSupplierCategories()
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier)
    {
        $this->checkPermission('suppliers.edit');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $supplier = $this->supplierService->updateSupplier($supplier, $request->validated());

        return redirect()->route('inventory.suppliers.show', $supplier)
            ->with('success', 'Proveedor actualizado exitosamente.');
    }

    public function destroy(Supplier $supplier)
    {
        $this->checkPermission('suppliers.delete');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }
        
        $result = $this->supplierService->deleteSupplier($supplier);
        
        if (!$result['success']) {
            return back()->withErrors(['supplier' => $result['message']]);
        }

        return redirect()->route('inventory.suppliers.index')
            ->with('success', 'Proveedor eliminado exitosamente.');
    }

    public function statement(Supplier $supplier)
    {
        $this->checkPermission('suppliers.statements');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $statementData = $this->supplierService->generateSupplierStatement($supplier);

        return Inertia::render('Inventory/Suppliers/Statement', $statementData);
    }

    public function evaluate(Request $request, Supplier $supplier)
    {
        $this->checkPermission('suppliers.evaluations');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'price_rating' => 'required|numeric|min:1|max:5',
            'quality_rating' => 'required|numeric|min:1|max:5',
            'delivery_rating' => 'required|numeric|min:1|max:5',
            'service_rating' => 'required|numeric|min:1|max:5',
            'payment_terms_rating' => 'required|numeric|min:1|max:5',
            'comments' => 'nullable|string|max:1000',
            'evaluation_period' => 'required|string'
        ]);

        $evaluation = $this->supplierService->evaluateSupplier(
            $supplier,
            $request->all()
        );

        return response()->json([
            'success' => true,
            'evaluation' => $evaluation,
            'message' => 'Evaluación registrada exitosamente.'
        ]);
    }

    public function updatePaymentTerms(Request $request, Supplier $supplier)
    {
        $this->checkPermission('suppliers.payment_terms');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'payment_terms' => 'required|in:immediate,15_days,30_days,60_days,90_days,custom',
            'custom_payment_days' => 'required_if:payment_terms,custom|nullable|integer|min:1|max:365',
            'credit_limit' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'reason' => 'required|string|max:500'
        ]);

        $supplier = $this->supplierService->updatePaymentTerms(
            $supplier,
            $request->all()
        );

        return response()->json([
            'success' => true,
            'supplier' => $supplier,
            'message' => 'Condiciones de pago actualizadas exitosamente.'
        ]);
    }

    public function priceLists(Supplier $supplier)
    {
        $this->checkPermission('suppliers.price_lists');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $priceLists = $this->supplierService->getSupplierPriceLists($supplier);

        return Inertia::render('Inventory/Suppliers/PriceLists', [
            'supplier' => $supplier,
            'priceLists' => $priceLists
        ]);
    }

    public function uploadPriceList(Request $request, Supplier $supplier)
    {
        $this->checkPermission('suppliers.price_lists');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'effective_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:effective_date',
            'currency' => 'required|string|size:3',
            'notes' => 'nullable|string|max:500'
        ]);

        $result = $this->supplierService->uploadPriceList(
            $supplier,
            $request->file('file'),
            $request->except('file')
        );

        if (!$result['success']) {
            return back()->withErrors(['file' => $result['message']]);
        }

        return back()->with('success', 'Lista de precios cargada exitosamente.');
    }

    public function comparePrices(Request $request)
    {
        $this->checkPermission('suppliers.view');
        
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:1'
        ]);

        $comparison = $this->supplierService->comparePricesForProduct(
            $request->get('product_id'),
            $request->get('quantity')
        );

        return response()->json([
            'success' => true,
            'comparison' => $comparison
        ]);
    }

    public function getPerformanceMetrics(Supplier $supplier)
    {
        $this->checkPermission('suppliers.evaluations');
        
        if ($supplier->tenant_id !== auth()->user()->tenant_id) {
            abort(403);
        }

        $metrics = $this->supplierService->getSupplierPerformanceMetrics($supplier);

        return response()->json([
            'success' => true,
            'metrics' => $metrics
        ]);
    }

    public function export(Request $request)
    {
        $this->checkPermission('suppliers.view');
        
        $filters = $request->only(['search', 'type', 'status', 'payment_terms', 'rating']);
        
        return $this->supplierService->exportSuppliers($filters);
    }
}