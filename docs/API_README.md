# CrecePyme API Documentation

## Descripción General

La API REST de CrecePyme permite la integración con sistemas externos para gestionar clientes, productos, facturación electrónica y más.

## Base URL

- **Producción**: `https://api.crecepyme.cl/api/v1`
- **Staging**: `https://staging-api.crecepyme.cl/api/v1`
- **Desarrollo**: `http://localhost:8000/api/v1`

## Autenticación

La API utiliza tokens Bearer mediante Laravel Sanctum. Todos los requests deben incluir el token en el header:

```
Authorization: Bearer {tu-token-aqui}
```

### Obtener un Token

```bash
POST /api/v1/auth/token
Content-Type: application/json

{
    "email": "usuario@ejemplo.com",
    "password": "contraseña",
    "device_name": "Mi Aplicación v1.0"
}
```

Respuesta exitosa:
```json
{
    "message": "Token created successfully",
    "data": {
        "access_token": "1|abcdef123456...",
        "token_type": "Bearer",
        "expires_at": null,
        "abilities": ["*"],
        "user": {
            "id": 1,
            "name": "Usuario Ejemplo",
            "email": "usuario@ejemplo.com",
            "tenant_id": 1
        }
    }
}
```

## Rate Limiting

- **Default**: 1000 requests por hora por usuario
- Los límites pueden personalizarse por token
- Headers de respuesta incluyen:
  - `X-RateLimit-Limit`: Límite total
  - `X-RateLimit-Remaining`: Requests restantes
  - `X-RateLimit-Reset`: Timestamp de reset

## Endpoints Principales

### Clientes

#### Listar Clientes
```
GET /api/v1/customers
```

Parámetros de query:
- `search`: Buscar por nombre, RUT o email
- `active`: Filtrar por estado (boolean)
- `page`: Número de página
- `per_page`: Resultados por página (default: 15)

#### Crear Cliente
```
POST /api/v1/customers
```

Body requerido:
```json
{
    "rut": "76.123.456-7",
    "name": "Empresa Ejemplo S.A.",
    "email": "contacto@empresa.cl",
    "phone": "+56912345678",
    "address": "Av. Principal 123",
    "city": "Santiago",
    "credit_limit": 5000000,
    "payment_terms": 30
}
```

### Productos

#### Listar Productos
```
GET /api/v1/products
```

Parámetros de query:
- `search`: Buscar por nombre, SKU o descripción
- `category_id`: Filtrar por categoría
- `is_active`: Estado activo (boolean)
- `track_inventory`: Con control de inventario (boolean)
- `low_stock`: Solo productos con stock bajo (boolean)

#### Actualizar Stock
```
POST /api/v1/products/{id}/stock
```

Body:
```json
{
    "quantity": 50,
    "type": "adjustment",
    "reason": "Ajuste por inventario físico",
    "reference": "INV-2025-001"
}
```

### Facturas

#### Crear Factura
```
POST /api/v1/invoices
```

Body:
```json
{
    "customer_id": 123,
    "document_type": 33,
    "issue_date": "2025-05-26",
    "due_date": "2025-06-26",
    "items": [
        {
            "product_id": 45,
            "description": "Producto ejemplo",
            "quantity": 2,
            "unit_price": 15000,
            "tax_rate": 19
        }
    ]
}
```

#### Enviar al SII
```
POST /api/v1/invoices/{id}/send
```

### Pagos

#### Registrar Pago
```
POST /api/v1/payments
```

Body:
```json
{
    "customer_id": 123,
    "amount": 119000,
    "payment_date": "2025-05-26",
    "payment_method": "transfer",
    "reference": "TRANS-12345",
    "allocations": [
        {
            "invoice_id": 456,
            "amount": 119000
        }
    ]
}
```

## Códigos de Error

| Código | Descripción |
|--------|-------------|
| 400 | Bad Request - Petición inválida |
| 401 | Unauthorized - Token inválido o faltante |
| 403 | Forbidden - Sin permisos para esta acción |
| 404 | Not Found - Recurso no encontrado |
| 422 | Unprocessable Entity - Error de validación |
| 429 | Too Many Requests - Límite excedido |
| 500 | Internal Server Error - Error del servidor |

## Webhooks

CrecePyme puede enviar notificaciones a tu sistema cuando ocurren eventos importantes.

### Eventos Disponibles

- `invoice.created`: Factura creada
- `invoice.sent`: Factura enviada al SII
- `invoice.accepted`: Factura aceptada por SII
- `invoice.rejected`: Factura rechazada por SII
- `invoice.paid`: Factura pagada
- `payment.received`: Pago recibido
- `product.low_stock`: Stock bajo en producto

### Configuración

Configura tu endpoint webhook desde el panel de control o via API:

```
POST /api/v1/webhooks
```

```json
{
    "url": "https://tu-sistema.com/webhook",
    "events": ["invoice.created", "payment.received"],
    "active": true
}
```

### Seguridad

Todos los webhooks incluyen una firma HMAC-SHA256 en el header `X-Webhook-Signature` para verificar autenticidad.

## Swagger/OpenAPI

La documentación interactiva completa está disponible en:

- **Producción**: https://api.crecepyme.cl/api/docs
- **Local**: http://localhost:8000/api/docs

## SDK y Librerías

Próximamente estarán disponibles SDK oficiales para:
- PHP
- Python
- Node.js
- Ruby

## Soporte

- Email: api@crecepyme.cl
- Documentación: https://docs.crecepyme.cl
- Estado del servicio: https://status.crecepyme.cl

## Changelog

### v1.0.0 (2025-05-26)
- Lanzamiento inicial de la API
- Endpoints para clientes, productos, facturas y pagos
- Autenticación con Laravel Sanctum
- Documentación Swagger/OpenAPI