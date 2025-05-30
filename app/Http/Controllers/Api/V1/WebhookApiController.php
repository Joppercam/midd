<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Webhook;
use App\Models\WebhookCall;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WebhookApiController extends BaseApiController
{
    protected $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/webhooks",
     *     summary="Listar webhooks",
     *     description="Obtiene la lista de webhooks configurados",
     *     operationId="getWebhooks",
     *     tags={"Webhooks"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de webhooks"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('webhooks.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $webhooks = Webhook::with(['calls' => function ($query) {
                $query->latest()->limit(5);
            }])->paginate($request->get('per_page', 15));

            $this->logApiActivity('webhooks.index', $request);

            return response()->json([
                'data' => $webhooks->items(),
                'meta' => [
                    'current_page' => $webhooks->currentPage(),
                    'last_page' => $webhooks->lastPage(),
                    'per_page' => $webhooks->perPage(),
                    'total' => $webhooks->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving webhooks');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/webhooks",
     *     summary="Crear webhook",
     *     description="Configura un nuevo webhook",
     *     operationId="createWebhook",
     *     tags={"Webhooks"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "url", "events"},
     *             @OA\Property(property="name", type="string", example="Mi Webhook"),
     *             @OA\Property(property="url", type="string", format="url", example="https://mi-sistema.com/webhook"),
     *             @OA\Property(property="events", type="array", @OA\Items(type="string"), example={"invoice.created", "payment.received"}),
     *             @OA\Property(property="headers", type="object", example={"X-Custom-Header": "value"}),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="max_retries", type="integer", example=3),
     *             @OA\Property(property="retry_delay", type="integer", example=60),
     *             @OA\Property(property="timeout", type="integer", example=30)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Webhook creado exitosamente"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->checkApiPermission('webhooks.create')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:' . implode(',', array_keys(WebhookService::getAvailableEvents())),
            'headers' => 'nullable|array',
            'active' => 'boolean',
            'max_retries' => 'nullable|integer|min:0|max:10',
            'retry_delay' => 'nullable|integer|min:10|max:3600',
            'timeout' => 'nullable|integer|min:5|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();
            $data['secret'] = Str::random(32);

            $webhook = Webhook::create($data);

            $this->logApiActivity('webhooks.store', $request, $webhook->id);

            return response()->json([
                'message' => 'Webhook created successfully',
                'data' => $webhook
            ], 201);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error creating webhook');
        }
    }

    public function show(Webhook $webhook): JsonResponse
    {
        if (!$this->checkApiPermission('webhooks.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$this->verifyTenantAccess($webhook)) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        try {
            $webhook->load(['calls' => function ($query) {
                $query->latest()->limit(20);
            }]);

            $this->logApiActivity('webhooks.show', request(), $webhook->id);

            return response()->json(['data' => $webhook]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving webhook');
        }
    }

    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        if (!$this->checkApiPermission('webhooks.edit')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$this->verifyTenantAccess($webhook)) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:' . implode(',', array_keys(WebhookService::getAvailableEvents())),
            'headers' => 'nullable|array',
            'active' => 'boolean',
            'max_retries' => 'nullable|integer|min:0|max:10',
            'retry_delay' => 'nullable|integer|min:10|max:3600',
            'timeout' => 'nullable|integer|min:5|max:120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $webhook->update($validator->validated());

            $this->logApiActivity('webhooks.update', $request, $webhook->id);

            return response()->json([
                'message' => 'Webhook updated successfully',
                'data' => $webhook
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error updating webhook');
        }
    }

    public function destroy(Webhook $webhook): JsonResponse
    {
        if (!$this->checkApiPermission('webhooks.delete')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$this->verifyTenantAccess($webhook)) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        try {
            $webhookId = $webhook->id;
            $webhook->delete();

            $this->logApiActivity('webhooks.destroy', request(), $webhookId);

            return response()->json(['message' => 'Webhook deleted successfully']);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error deleting webhook');
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/webhooks/{id}/test",
     *     summary="Probar webhook",
     *     description="Envía un evento de prueba al webhook",
     *     operationId="testWebhook",
     *     tags={"Webhooks"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Test enviado exitosamente"
     *     )
     * )
     */
    public function test(Webhook $webhook): JsonResponse
    {
        if (!$this->checkApiPermission('webhooks.test')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$this->verifyTenantAccess($webhook)) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        try {
            $testPayload = [
                'test' => true,
                'webhook_id' => $webhook->id,
                'timestamp' => now()->toIso8601String(),
                'message' => 'This is a test webhook call from CrecePyme API',
            ];

            $this->webhookService->dispatch('webhook.test', $testPayload, $webhook->tenant_id);

            $this->logApiActivity('webhooks.test', request(), $webhook->id);

            return response()->json([
                'message' => 'Test webhook dispatched successfully',
                'data' => [
                    'webhook_id' => $webhook->id,
                    'url' => $webhook->url,
                    'test_payload' => $testPayload
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error testing webhook');
        }
    }

    public function calls(Request $request, Webhook $webhook): JsonResponse
    {
        if (!$this->checkApiPermission('webhooks.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$this->verifyTenantAccess($webhook)) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        try {
            $query = $webhook->calls();

            if ($request->filled('status')) {
                switch ($request->get('status')) {
                    case 'completed':
                        $query->whereNotNull('completed_at');
                        break;
                    case 'failed':
                        $query->whereNotNull('failed_at');
                        break;
                    case 'pending':
                        $query->whereNull('completed_at')->whereNull('failed_at');
                        break;
                }
            }

            if ($request->filled('event')) {
                $query->where('event', $request->get('event'));
            }

            $calls = $query->latest()->paginate($request->get('per_page', 15));

            $this->logApiActivity('webhooks.calls', $request, $webhook->id);

            return response()->json([
                'data' => $calls->items(),
                'meta' => [
                    'current_page' => $calls->currentPage(),
                    'last_page' => $calls->lastPage(),
                    'per_page' => $calls->perPage(),
                    'total' => $calls->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving webhook calls');
        }
    }

    public function events(): JsonResponse
    {
        if (!$this->checkApiPermission('webhooks.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $events = WebhookService::getAvailableEvents();

            $this->logApiActivity('webhooks.events', request());

            return response()->json(['data' => $events]);
        } catch (\Exception $e) {
            return $this->handleApiException($e, 'Error retrieving webhook events');
        }
    }
}