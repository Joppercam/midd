<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/**
 * @OA\Schema(
 *     schema="Customer",
 *     type="object",
 *     required={"id", "rut", "name", "tenant_id"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="tenant_id", type="integer", example=1),
 *     @OA\Property(property="rut", type="string", example="76.123.456-7"),
 *     @OA\Property(property="name", type="string", example="Empresa Ejemplo S.A."),
 *     @OA\Property(property="business_name", type="string", example="Empresa Ejemplo S.A."),
 *     @OA\Property(property="email", type="string", format="email", example="contacto@empresa.cl"),
 *     @OA\Property(property="phone", type="string", example="+56912345678"),
 *     @OA\Property(property="address", type="string", example="Av. Principal 123"),
 *     @OA\Property(property="city", type="string", example="Santiago"),
 *     @OA\Property(property="region", type="string", example="Metropolitana"),
 *     @OA\Property(property="postal_code", type="string", example="7500000"),
 *     @OA\Property(property="tax_id", type="string", example="76123456-7"),
 *     @OA\Property(property="credit_limit", type="number", format="float", example=5000000),
 *     @OA\Property(property="payment_terms", type="integer", example=30),
 *     @OA\Property(property="active", type="boolean", example=true),
 *     @OA\Property(property="balance", type="number", format="float", example=1500000),
 *     @OA\Property(property="notes", type="string", example="Cliente VIP"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class CustomerApiController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/customers",
     *     summary="Listar clientes",
     *     description="Obtiene una lista paginada de clientes",
     *     operationId="getCustomers",
     *     tags={"Clientes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Buscar por nombre, RUT o email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Filtrar por estado activo",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número de página",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Resultados por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de clientes obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Customer")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos para ver clientes"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Check permissions
        $permissionCheck = $this->checkApiPermission('customers.view');
        if ($permissionCheck) return $permissionCheck;
        
        $this->logApiActivity('customers.index');
        
        $query = Customer::where('tenant_id', $this->getTenantId($request));
        
        $query = $this->applyFilters($request, $query);
        
        $customers = $query->paginate($request->get('per_page', 15));
        
        return $this->paginated($customers);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/customers/{id}",
     *     summary="Obtener cliente",
     *     description="Obtiene los detalles de un cliente específico",
     *     operationId="getCustomer",
     *     tags={"Clientes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del cliente",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cliente obtenido exitosamente",
     *         @OA\JsonContent(ref="#/components/schemas/Customer")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cliente no encontrado"
     *     )
     * )
     */
    public function show(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('customers.view');
        if ($permissionCheck) return $permissionCheck;
        
        $customer = Customer::where('tenant_id', $this->getTenantId($request))
            ->find($id);
            
        if (!$customer) {
            return $this->notFound('Cliente no encontrado');
        }
        
        return $this->success($customer);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/customers",
     *     summary="Crear cliente",
     *     description="Crea un nuevo cliente",
     *     operationId="createCustomer",
     *     tags={"Clientes"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rut", "name"},
     *             @OA\Property(property="rut", type="string", example="76.123.456-7"),
     *             @OA\Property(property="name", type="string", example="Empresa Ejemplo S.A."),
     *             @OA\Property(property="business_name", type="string", example="Empresa Ejemplo S.A."),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="region", type="string"),
     *             @OA\Property(property="postal_code", type="string"),
     *             @OA\Property(property="credit_limit", type="number"),
     *             @OA\Property(property="payment_terms", type="integer"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Cliente creado exitosamente",
     *         @OA\JsonContent(ref="#/components/schemas/Customer")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('customers.create');
        if ($permissionCheck) return $permissionCheck;
        
        try {
        $validated = $request->validate([
            'rut' => [
                'required',
                'string',
                Rule::unique('customers')->where(function ($query) use ($request) {
                    return $query->where('tenant_id', $this->getTenantId($request));
                })
            ],
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|integer|min:0'
        ]);
        
        $validated['tenant_id'] = $this->getTenantId($request);
        
        $customer = Customer::create($validated);
        
        $this->logApiActivity('customers.create', ['customer_id' => $customer->id]);
        
        return $this->created($customer, 'Cliente creado exitosamente');
        } catch (\Exception $e) {
            return $this->handleApiException($e);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('customers.edit');
        if ($permissionCheck) return $permissionCheck;
        
        try {
        $customer = Customer::where('tenant_id', $this->getTenantId($request))
            ->find($id);
            
        if (!$customer) {
            return $this->notFound('Cliente no encontrado');
        }
        
        $validated = $request->validate([
            'rut' => [
                'sometimes',
                'string',
                Rule::unique('customers')->where(function ($query) use ($request) {
                    return $query->where('tenant_id', $this->getTenantId($request));
                })->ignore($customer->id)
            ],
            'name' => 'sometimes|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|integer|min:0'
        ]);
        
        $customer->update($validated);
        
        $this->logApiActivity('customers.update', ['customer_id' => $customer->id]);
        
        return $this->updated($customer, 'Cliente actualizado exitosamente');
        } catch (\Exception $e) {
            return $this->handleApiException($e);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('customers.delete');
        if ($permissionCheck) return $permissionCheck;
        
        try {
        $customer = Customer::where('tenant_id', $this->getTenantId($request))
            ->find($id);
            
        if (!$customer) {
            return $this->notFound('Cliente no encontrado');
        }
        
        // Check if customer has invoices
        if ($customer->taxDocuments()->exists()) {
            return $this->error('No se puede eliminar un cliente con facturas asociadas', 409);
        }
        
        $customer->delete();
        
        $this->logApiActivity('customers.delete', ['customer_id' => $id]);
        
        return $this->deleted('Cliente eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->handleApiException($e);
        }
    }

    public function balance(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('customers.view');
        if ($permissionCheck) return $permissionCheck;
        
        $customer = Customer::where('tenant_id', $this->getTenantId($request))
            ->find($id);
            
        if (!$customer) {
            return $this->notFound('Cliente no encontrado');
        }
        
        return $this->success([
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'total_invoiced' => $customer->getTotalInvoiced(),
            'total_paid' => $customer->getTotalPaid(),
            'balance' => $customer->getBalance(),
            'overdue_amount' => $customer->getOverdueAmount(),
            'credit_limit' => $customer->credit_limit,
            'available_credit' => $customer->getAvailableCredit()
        ]);
    }

    public function transactions(Request $request, $id): JsonResponse
    {
        $permissionCheck = $this->checkApiPermission('customers.view');
        if ($permissionCheck) return $permissionCheck;
        
        $customer = Customer::where('tenant_id', $this->getTenantId($request))
            ->find($id);
            
        if (!$customer) {
            return $this->notFound('Cliente no encontrado');
        }
        
        $query = $customer->taxDocuments()
            ->with('payments')
            ->orderBy('issue_date', 'desc');
            
        if ($request->has('status')) {
            $query->where('payment_status', $request->status);
        }
        
        $documents = $query->paginate($request->get('per_page', 15));
        
        return $this->paginated($documents);
    }
}