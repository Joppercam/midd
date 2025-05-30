<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SupplierApiController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('suppliers.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $query = Supplier::query();

            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('rut', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->filled('active')) {
                $query->where('active', $request->boolean('active'));
            }

            $suppliers = $query->orderBy('name')
                              ->paginate($request->get('per_page', 15));

            $this->logApiActivity('suppliers.index', $request);

            return response()->json([
                'data' => $suppliers->items(),
                'meta' => [
                    'current_page' => $suppliers->currentPage(),
                    'last_page' => $suppliers->lastPage(),
                    'per_page' => $suppliers->perPage(),
                    'total' => $suppliers->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving suppliers');
        }
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('suppliers.create')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'rut' => 'required|string|max:12|unique:suppliers,rut',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'contact_person' => 'nullable|string|max:255',
            'payment_terms' => 'nullable|integer|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $supplier = Supplier::create($validator->validated());

            $this->logApiActivity('suppliers.store', $request, $supplier->id);

            return response()->json([
                'message' => 'Supplier created successfully',
                'data' => $supplier
            ], 201);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error creating supplier');
        }
    }

    public function show(Supplier $supplier): JsonResponse
    {
        if (!$this->checkApiPermission('suppliers.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$this->verifyTenantAccess($supplier)) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        try {
            $supplier->load(['expenses' => function ($query) {
                $query->with('category')->latest()->take(10);
            }]);

            $supplier->expenses_total = $supplier->expenses()->sum('amount');
            $supplier->expenses_count = $supplier->expenses()->count();

            $this->logApiActivity('suppliers.show', request(), $supplier->id);

            return response()->json(['data' => $supplier]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving supplier');
        }
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        if (!$this->checkApiPermission('suppliers.edit')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$this->verifyTenantAccess($supplier)) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'rut' => 'required|string|max:12|unique:suppliers,rut,' . $supplier->id,
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'contact_person' => 'nullable|string|max:255',
            'payment_terms' => 'nullable|integer|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $supplier->update($validator->validated());

            $this->logApiActivity('suppliers.update', $request, $supplier->id);

            return response()->json([
                'message' => 'Supplier updated successfully',
                'data' => $supplier
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error updating supplier');
        }
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        if (!$this->checkApiPermission('suppliers.delete')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$this->verifyTenantAccess($supplier)) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        try {
            $supplierId = $supplier->id;
            $supplier->delete();

            $this->logApiActivity('suppliers.destroy', request(), $supplierId);

            return response()->json(['message' => 'Supplier deleted successfully']);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error deleting supplier');
        }
    }

    public function stats(): JsonResponse
    {
        if (!$this->checkApiPermission('suppliers.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $stats = [
                'total_suppliers' => Supplier::count(),
                'active_suppliers' => Supplier::where('active', true)->count(),
                'inactive_suppliers' => Supplier::where('active', false)->count(),
                'total_expenses' => Supplier::join('expenses', 'suppliers.id', '=', 'expenses.supplier_id')
                    ->sum('expenses.amount'),
                'top_suppliers' => Supplier::withSum('expenses', 'amount')
                    ->orderBy('expenses_sum_amount', 'desc')
                    ->take(5)
                    ->get(['id', 'name', 'expenses_sum_amount'])
            ];

            $this->logApiActivity('suppliers.stats', request());

            return response()->json(['data' => $stats]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving supplier statistics');
        }
    }
}