<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Info(
 *     title="CrecePyme API",
 *     version="1.0.0",
 *     description="API REST para el sistema de gestión empresarial CrecePyme",
 *     @OA\Contact(
 *         name="Soporte CrecePyme",
 *         email="api@crecepyme.cl",
 *         url="https://crecepyme.cl/soporte"
 *     ),
 *     @OA\License(
 *         name="Propietaria",
 *         url="https://crecepyme.cl/terminos"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="https://api.crecepyme.cl",
 *     description="Servidor de Producción"
 * )
 * 
 * @OA\Server(
 *     url="https://staging-api.crecepyme.cl",
 *     description="Servidor de Staging"
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Servidor de Desarrollo Local"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     description="Ingrese el token en el formato: Bearer {token}"
 * )
 * 
 * @OA\Tag(name="Autenticación", description="Endpoints para autenticación y gestión de tokens")
 * @OA\Tag(name="Clientes", description="Gestión de clientes")
 * @OA\Tag(name="Productos", description="Gestión de productos e inventario")
 * @OA\Tag(name="Facturas", description="Gestión de documentos tributarios")
 * @OA\Tag(name="Pagos", description="Gestión de pagos y cobros")
 * @OA\Tag(name="Gastos", description="Gestión de gastos")
 * @OA\Tag(name="Proveedores", description="Gestión de proveedores")
 * @OA\Tag(name="Conciliación Bancaria", description="Gestión de conciliación bancaria")
 * @OA\Tag(name="Auditoría", description="Logs y configuración de auditoría")
 * @OA\Tag(name="Reportes", description="Generación de reportes")
 * @OA\Tag(name="Sistema", description="Endpoints del sistema")
 */
class ApiDocumentationController extends Controller
{
    public function index()
    {
        return view('l5-swagger::index');
    }

    public function openapi()
    {
        $swagger = \OpenApi\scan([
            app_path('Http/Controllers/Api'),
        ]);

        return response()->json($swagger);
    }

    public function apiInfo(): JsonResponse
    {
        return response()->json([
            'name' => 'CrecePyme API',
            'version' => 'v1',
            'description' => 'API REST para integración con CrecePyme - Sistema de Facturación Electrónica',
            'base_url' => config('app.url') . '/api/v1',
            'authentication' => [
                'type' => 'Bearer Token',
                'description' => 'Incluir el token en el header Authorization: Bearer {token}',
                'alternative_headers' => [
                    'X-API-Key: {token}'
                ],
                'alternative_query' => 'api_token={token}'
            ],
            'rate_limiting' => [
                'default' => '60 requests per minute',
                'description' => 'El límite se puede personalizar por token'
            ],
            'endpoints' => $this->getEndpoints(),
            'webhooks' => $this->getWebhooks(),
            'errors' => $this->getErrorCodes()
        ]);
    }

    protected function getEndpoints(): array
    {
        return [
            'customers' => [
                'list' => [
                    'method' => 'GET',
                    'endpoint' => '/customers',
                    'description' => 'Listar clientes',
                    'parameters' => [
                        'search' => 'string (opcional) - Buscar por nombre o RUT',
                        'per_page' => 'integer (opcional) - Resultados por página (default: 15)',
                        'page' => 'integer (opcional) - Número de página',
                        'sort_by' => 'string (opcional) - Campo para ordenar',
                        'sort_direction' => 'string (opcional) - asc o desc'
                    ]
                ],
                'show' => [
                    'method' => 'GET',
                    'endpoint' => '/customers/{id}',
                    'description' => 'Obtener detalle de un cliente'
                ],
                'create' => [
                    'method' => 'POST',
                    'endpoint' => '/customers',
                    'description' => 'Crear nuevo cliente',
                    'body' => [
                        'rut' => 'string (requerido) - RUT del cliente',
                        'name' => 'string (requerido) - Nombre del cliente',
                        'business_name' => 'string (opcional) - Razón social',
                        'email' => 'email (opcional)',
                        'phone' => 'string (opcional)',
                        'address' => 'string (opcional)',
                        'city' => 'string (opcional)',
                        'notes' => 'string (opcional)',
                        'credit_limit' => 'numeric (opcional)',
                        'payment_terms' => 'integer (opcional) - Días de plazo'
                    ]
                ],
                'update' => [
                    'method' => 'PUT',
                    'endpoint' => '/customers/{id}',
                    'description' => 'Actualizar cliente',
                    'body' => 'Mismos campos que create (todos opcionales excepto id)'
                ],
                'delete' => [
                    'method' => 'DELETE',
                    'endpoint' => '/customers/{id}',
                    'description' => 'Eliminar cliente (sin facturas asociadas)'
                ],
                'balance' => [
                    'method' => 'GET',
                    'endpoint' => '/customers/{id}/balance',
                    'description' => 'Obtener balance del cliente'
                ],
                'transactions' => [
                    'method' => 'GET',
                    'endpoint' => '/customers/{id}/transactions',
                    'description' => 'Listar transacciones del cliente'
                ]
            ],
            'products' => [
                'list' => [
                    'method' => 'GET',
                    'endpoint' => '/products',
                    'description' => 'Listar productos',
                    'parameters' => [
                        'category_id' => 'integer (opcional)',
                        'is_active' => 'boolean (opcional)',
                        'track_inventory' => 'boolean (opcional)',
                        'low_stock' => 'boolean (opcional) - Solo productos con stock bajo'
                    ]
                ],
                'show' => [
                    'method' => 'GET',
                    'endpoint' => '/products/{id}',
                    'description' => 'Obtener detalle de un producto'
                ],
                'create' => [
                    'method' => 'POST',
                    'endpoint' => '/products',
                    'description' => 'Crear nuevo producto',
                    'body' => [
                        'sku' => 'string (requerido) - Código único',
                        'name' => 'string (requerido)',
                        'description' => 'string (opcional)',
                        'category_id' => 'integer (opcional)',
                        'unit_price' => 'numeric (requerido)',
                        'cost_price' => 'numeric (opcional)',
                        'tax_rate' => 'numeric (opcional) - Porcentaje IVA',
                        'track_inventory' => 'boolean (opcional)',
                        'current_stock' => 'numeric (requerido si track_inventory=true)',
                        'minimum_stock' => 'numeric (opcional)',
                        'is_active' => 'boolean (opcional)'
                    ]
                ],
                'update_stock' => [
                    'method' => 'POST',
                    'endpoint' => '/products/{id}/stock',
                    'description' => 'Actualizar stock de producto',
                    'body' => [
                        'quantity' => 'numeric (requerido) - Positivo para entrada, negativo para salida',
                        'type' => 'string (requerido) - adjustment|purchase|return|damage|other',
                        'reason' => 'string (requerido)',
                        'reference' => 'string (opcional)'
                    ]
                ],
                'stock_movements' => [
                    'method' => 'GET',
                    'endpoint' => '/products/{id}/movements',
                    'description' => 'Historial de movimientos de inventario'
                ],
                'low_stock' => [
                    'method' => 'GET',
                    'endpoint' => '/products/low-stock',
                    'description' => 'Productos con stock bajo'
                ]
            ],
            'invoices' => [
                'list' => [
                    'method' => 'GET',
                    'endpoint' => '/invoices',
                    'description' => 'Listar facturas',
                    'parameters' => [
                        'document_type' => 'integer (opcional) - 33|34|61|56',
                        'status' => 'string (opcional) - draft|sent|accepted|rejected',
                        'payment_status' => 'string (opcional) - pending|partial|paid',
                        'customer_id' => 'integer (opcional)',
                        'date_from' => 'date (opcional)',
                        'date_to' => 'date (opcional)'
                    ]
                ],
                'show' => [
                    'method' => 'GET',
                    'endpoint' => '/invoices/{id}',
                    'description' => 'Obtener detalle de factura con items'
                ],
                'create' => [
                    'method' => 'POST',
                    'endpoint' => '/invoices',
                    'description' => 'Crear nueva factura',
                    'body' => [
                        'customer_id' => 'integer (requerido)',
                        'document_type' => 'integer (requerido) - 33|34|61|56',
                        'issue_date' => 'date (requerido)',
                        'due_date' => 'date (opcional)',
                        'currency' => 'string (opcional) - Default: CLP',
                        'exchange_rate' => 'numeric (opcional)',
                        'notes' => 'string (opcional)',
                        'items' => [
                            [
                                'product_id' => 'integer (opcional)',
                                'description' => 'string (requerido)',
                                'quantity' => 'numeric (requerido)',
                                'unit_price' => 'numeric (requerido)',
                                'discount_percentage' => 'numeric (opcional)',
                                'tax_rate' => 'numeric (opcional) - Default: 19'
                            ]
                        ]
                    ]
                ],
                'send' => [
                    'method' => 'POST',
                    'endpoint' => '/invoices/{id}/send',
                    'description' => 'Enviar factura al SII'
                ],
                'download' => [
                    'method' => 'GET',
                    'endpoint' => '/invoices/{id}/download',
                    'description' => 'Obtener URL de descarga del PDF'
                ],
                'summary' => [
                    'method' => 'GET',
                    'endpoint' => '/invoices/summary',
                    'description' => 'Resumen de facturación'
                ]
            ]
        ];
    }

    protected function getWebhooks(): array
    {
        return [
            'events' => [
                'invoice.created' => 'Factura creada',
                'invoice.sent' => 'Factura enviada al SII',
                'invoice.accepted' => 'Factura aceptada por SII',
                'invoice.rejected' => 'Factura rechazada por SII',
                'invoice.paid' => 'Factura pagada',
                'customer.created' => 'Cliente creado',
                'customer.updated' => 'Cliente actualizado',
                'product.created' => 'Producto creado',
                'product.updated' => 'Producto actualizado',
                'product.low_stock' => 'Producto con stock bajo',
                'payment.received' => 'Pago recibido'
            ],
            'payload_example' => [
                'event' => 'invoice.created',
                'timestamp' => '2025-05-25T12:00:00Z',
                'data' => [
                    'id' => 123,
                    'document_number' => 'F001-00000123',
                    'customer_id' => 45,
                    'total_amount' => 119000
                ]
            ],
            'security' => 'Las peticiones incluyen header X-Webhook-Signature con HMAC-SHA256'
        ];
    }

    protected function getErrorCodes(): array
    {
        return [
            '400' => 'Bad Request - Petición inválida',
            '401' => 'Unauthorized - Token inválido o faltante',
            '403' => 'Forbidden - Sin permisos para esta acción',
            '404' => 'Not Found - Recurso no encontrado',
            '409' => 'Conflict - Conflicto con el estado actual',
            '422' => 'Unprocessable Entity - Error de validación',
            '429' => 'Too Many Requests - Límite de rate excedido',
            '500' => 'Internal Server Error - Error del servidor'
        ];
    }
}