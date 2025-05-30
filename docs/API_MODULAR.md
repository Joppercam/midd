# 🔌 API del Sistema Modular CrecePyme

## 📋 Índice
- [Autenticación](#autenticación)
- [Endpoints de Administración](#endpoints-de-administración)
- [Endpoints de Tenant](#endpoints-de-tenant)
- [Webhooks](#webhooks)
- [Códigos de Error](#códigos-de-error)
- [Ejemplos de Uso](#ejemplos-de-uso)

## 🔐 Autenticación

Todos los endpoints requieren autenticación Bearer Token (Laravel Sanctum):

```http
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

### Obtener Token

```http
POST /api/v1/auth/token
```

```json
{
    "email": "admin@empresa.cl",
    "password": "password123"
}
```

**Respuesta:**
```json
{
    "token": "1|abcd1234...",
    "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@empresa.cl",
        "tenant_id": "uuid-tenant"
    },
    "expires_at": "2025-06-27T22:30:00Z"
}
```

## 👑 Endpoints de Administración

> **Nota**: Requieren rol `super-admin` o `admin`

### Listar Todos los Módulos

```http
GET /api/admin/modules
```

**Respuesta:**
```json
{
    "data": [
        {
            "id": 1,
            "code": "invoicing",
            "name": "Facturación Electrónica",
            "description": "Facturación, boletas, notas con integración SII",
            "version": "1.0.0",
            "category": "finance",
            "base_price": 15000,
            "is_core": false,
            "is_active": true,
            "dependencies": ["core", "tenancy"],
            "features": [
                "Facturación electrónica SII",
                "Múltiples tipos de documento",
                "Generación automática de PDF"
            ],
            "active_tenants_count": 45,
            "monthly_revenue": 675000
        }
    ],
    "stats": {
        "active_modules": 15,
        "total_tenants": 120,
        "monthly_revenue": 8500000,
        "pending_requests": 5
    }
}
```

### Obtener Detalles de un Módulo

```http
GET /api/admin/modules/{moduleId}
```

**Respuesta:**
```json
{
    "data": {
        "id": 1,
        "code": "invoicing",
        "name": "Facturación Electrónica",
        "description": "...",
        "tenants": [
            {
                "id": "uuid-1",
                "name": "Empresa Demo S.A.",
                "enabled_at": "2025-01-15T10:30:00Z",
                "status": "active",
                "custom_price": null,
                "usage_stats": {
                    "documents_created": 156,
                    "last_used": "2025-05-27T15:20:00Z"
                }
            }
        ],
        "usage_analytics": {
            "total_actions": 15234,
            "unique_users": 89,
            "avg_daily_usage": 45.2,
            "growth_rate": 12.5
        }
    }
}
```

### Gestionar Módulos de un Tenant

```http
GET /api/admin/tenants/{tenantId}/modules
```

**Respuesta:**
```json
{
    "tenant": {
        "id": "uuid-tenant",
        "name": "Empresa Demo S.A.",
        "subscription": {
            "plan": "professional",
            "status": "active",
            "trial_ends_at": null
        }
    },
    "available_modules": [
        {
            "id": 1,
            "code": "invoicing",
            "name": "Facturación Electrónica",
            "base_price": 15000,
            "included_in_plan": true
        }
    ],
    "active_modules": [
        {
            "id": 1,
            "module_id": 1,
            "is_enabled": true,
            "enabled_at": "2025-01-15T10:30:00Z",
            "expires_at": null,
            "custom_price": null,
            "status": "active"
        }
    ]
}
```

### Actualizar Módulos de un Tenant

```http
POST /api/admin/tenants/{tenantId}/modules
```

**Payload:**
```json
{
    "modules": [
        {
            "module_id": 1,
            "is_enabled": true,
            "custom_price": 12000,
            "expires_at": null
        },
        {
            "module_id": 5,
            "is_enabled": false
        }
    ]
}
```

**Respuesta:**
```json
{
    "message": "Módulos actualizados exitosamente",
    "updated_modules": [
        {
            "module_code": "invoicing",
            "action": "enabled",
            "price": 12000
        },
        {
            "module_code": "crm", 
            "action": "disabled"
        }
    ]
}
```

### Aprobar/Rechazar Solicitudes

```http
POST /api/admin/module-requests/{requestId}/approve
```

**Payload:**
```json
{
    "notes": "Aprobado para período de prueba de 30 días",
    "trial_days": 30
}
```

```http
POST /api/admin/module-requests/{requestId}/reject
```

**Payload:**
```json
{
    "reason": "El tenant no cumple con los requisitos mínimos"
}
```

### Estadísticas de Uso

```http
GET /api/admin/modules/usage-stats?period=month
```

**Respuesta:**
```json
{
    "period": "month",
    "data": [
        {
            "module_name": "Facturación Electrónica",
            "module_code": "invoicing",
            "total_actions": 25634,
            "unique_users": 156,
            "active_tenants": 45,
            "revenue": 675000,
            "growth_rate": 8.5
        }
    ],
    "summary": {
        "total_revenue": 8500000,
        "total_actions": 156789,
        "active_tenants": 120,
        "avg_modules_per_tenant": 4.2
    }
}
```

## 🏢 Endpoints de Tenant

> **Nota**: Automáticamente filtrados por tenant del usuario autenticado

### Obtener Módulos Activos

```http
GET /api/v1/tenant/modules
```

**Respuesta:**
```json
{
    "data": [
        {
            "code": "invoicing",
            "name": "Facturación Electrónica",
            "status": "active",
            "enabled_at": "2025-01-15T10:30:00Z",
            "expires_at": null,
            "usage_this_month": {
                "documents_created": 45,
                "actions_performed": 156,
                "last_used": "2025-05-27T15:20:00Z"
            },
            "features_used": [
                "PDF generation",
                "Email sending",
                "SII integration"
            ]
        }
    ],
    "subscription": {
        "plan": "professional",
        "status": "active",
        "current_period_end": "2025-06-15T00:00:00Z",
        "monthly_cost": 89000,
        "included_modules": ["core", "invoicing", "payments"],
        "additional_modules_cost": 20000
    },
    "usage_limits": {
        "users": {"current": 7, "limit": 10},
        "documents": {"current": 156, "limit": 2000},
        "storage": {"current": "2.5GB", "limit": "20GB"}
    }
}
```

### Solicitar Nuevo Módulo

```http
POST /api/v1/tenant/module-requests
```

**Payload:**
```json
{
    "module_code": "crm",
    "reason": "Necesitamos gestionar mejor nuestros leads y oportunidades de venta",
    "expected_usage": "50 leads mensuales",
    "budget_approved": true
}
```

**Respuesta:**
```json
{
    "message": "Solicitud enviada exitosamente",
    "request_id": 123,
    "module": {
        "code": "crm",
        "name": "CRM Avanzado",
        "price": 20000,
        "estimated_approval_time": "24-48 horas"
    },
    "status": "pending"
}
```

### Obtener Recomendaciones

```http
GET /api/v1/tenant/module-recommendations
```

**Respuesta:**
```json
{
    "business_profile": {
        "type": "services",
        "size": "small",
        "industry": "consulting"
    },
    "recommendations": [
        {
            "module": {
                "code": "crm",
                "name": "CRM Avanzado",
                "price": 20000,
                "roi_projection": "250% en 6 meses"
            },
            "reason": "Tu perfil de servicios profesionales se beneficiaría de un CRM para gestionar clientes",
            "priority": "high",
            "estimated_setup_time": "2-4 horas"
        }
    ],
    "usage_optimization": [
        {
            "current_module": "invoicing",
            "suggestion": "Puedes automatizar el envío de recordatorios de pago",
            "potential_savings": "4 horas/semana"
        }
    ]
}
```

### Configuración de Módulo

```http
GET /api/v1/tenant/modules/{moduleCode}/settings
```

```http
PUT /api/v1/tenant/modules/{moduleCode}/settings
```

**Payload:**
```json
{
    "settings": {
        "auto_send_invoices": true,
        "payment_reminder_days": [7, 3, 1],
        "default_payment_terms": 30,
        "email_template": "professional"
    }
}
```

## 🔔 Webhooks

El sistema puede enviar webhooks para eventos importantes:

### Configurar Webhook

```http
POST /api/v1/webhooks
```

**Payload:**
```json
{
    "url": "https://tu-app.com/webhooks/crecepyme",
    "events": [
        "module.enabled",
        "module.disabled", 
        "subscription.upgraded",
        "usage.limit_reached"
    ],
    "secret": "tu-secreto-opcional"
}
```

### Eventos Disponibles

| Evento | Descripción | Payload |
|--------|-------------|---------|
| `module.enabled` | Módulo habilitado | `{module_code, tenant_id, enabled_at}` |
| `module.disabled` | Módulo deshabilitado | `{module_code, tenant_id, reason}` |
| `subscription.upgraded` | Plan mejorado | `{tenant_id, from_plan, to_plan, effective_date}` |
| `usage.limit_reached` | Límite alcanzado | `{tenant_id, limit_type, current_value, limit}` |
| `payment.overdue` | Pago vencido | `{tenant_id, amount, days_overdue}` |

### Verificación de Webhooks

Los webhooks incluyen header de verificación HMAC:

```http
X-CrecePyme-Signature: sha256=abc123...
X-CrecePyme-Timestamp: 1653750000
```

**Verificación en PHP:**
```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_CRECEPYME_SIGNATURE'];
$timestamp = $_SERVER['HTTP_X_CRECEPYME_TIMESTAMP'];

$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $payload, $webhook_secret);

if (!hash_equals($signature, $expected)) {
    http_response_code(401);
    exit('Invalid signature');
}
```

## ❌ Códigos de Error

### Errores de Módulos

| Código | HTTP | Descripción |
|--------|------|-------------|
| `MODULE_NOT_AVAILABLE` | 403 | Módulo no está activo para el tenant |
| `MODULE_DEPENDENCY_MISSING` | 422 | Faltan dependencias requeridas |
| `MODULE_LIMIT_REACHED` | 422 | Límite de módulos del plan alcanzado |
| `MODULE_NOT_FOUND` | 404 | Módulo no existe |
| `MODULE_ALREADY_ENABLED` | 409 | Módulo ya está activo |

### Errores de Suscripción

| Código | HTTP | Descripción |
|--------|------|-------------|
| `SUBSCRIPTION_EXPIRED` | 402 | Suscripción vencida |
| `SUBSCRIPTION_SUSPENDED` | 403 | Cuenta suspendida |
| `USAGE_LIMIT_EXCEEDED` | 429 | Límite de uso excedido |
| `PAYMENT_REQUIRED` | 402 | Pago pendiente |

### Formato de Error

```json
{
    "error": {
        "code": "MODULE_NOT_AVAILABLE",
        "message": "El módulo 'crm' no está disponible en tu plan actual",
        "details": {
            "module_code": "crm",
            "current_plan": "starter",
            "required_plan": "professional",
            "upgrade_url": "/subscription/upgrade"
        }
    },
    "request_id": "req_abc123"
}
```

## 💡 Ejemplos de Uso

### Verificar Acceso a Módulo (JavaScript)

```javascript
async function checkModuleAccess(moduleCode) {
    try {
        const response = await fetch(`/api/v1/tenant/modules`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        const hasAccess = data.data.some(m => m.code === moduleCode && m.status === 'active');
        
        return hasAccess;
    } catch (error) {
        console.error('Error checking module access:', error);
        return false;
    }
}

// Uso
if (await checkModuleAccess('invoicing')) {
    // Mostrar funcionalidades de facturación
} else {
    // Mostrar mensaje de upgrade
}
```

### Monitorear Uso de Módulos (Python)

```python
import requests
import json

class CrecePymeAPI:
    def __init__(self, token):
        self.token = token
        self.base_url = 'https://api.crecepyme.cl/api'
        self.headers = {
            'Authorization': f'Bearer {token}',
            'Content-Type': 'application/json'
        }
    
    def get_usage_stats(self, period='month'):
        response = requests.get(
            f'{self.base_url}/admin/modules/usage-stats',
            headers=self.headers,
            params={'period': period}
        )
        return response.json()
    
    def get_tenant_modules(self, tenant_id):
        response = requests.get(
            f'{self.base_url}/admin/tenants/{tenant_id}/modules',
            headers=self.headers
        )
        return response.json()

# Uso
api = CrecePymeAPI('your-token-here')
stats = api.get_usage_stats('month')

for module in stats['data']:
    print(f"{module['module_name']}: {module['total_actions']} acciones")
```

### Webhook Handler (Node.js)

```javascript
const express = require('express');
const crypto = require('crypto');
const app = express();

app.use(express.raw({type: 'application/json'}));

app.post('/webhooks/crecepyme', (req, res) => {
    const signature = req.headers['x-crecepyme-signature'];
    const timestamp = req.headers['x-crecepyme-timestamp'];
    const payload = req.body;
    
    // Verificar firma
    const expected = 'sha256=' + crypto
        .createHmac('sha256', process.env.WEBHOOK_SECRET)
        .update(timestamp + '.' + payload)
        .digest('hex');
    
    if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) {
        return res.status(401).send('Invalid signature');
    }
    
    const event = JSON.parse(payload);
    
    switch (event.type) {
        case 'module.enabled':
            console.log(`Módulo ${event.data.module_code} habilitado para ${event.data.tenant_id}`);
            // Activar funcionalidades en tu sistema
            break;
            
        case 'usage.limit_reached':
            console.log(`Límite alcanzado: ${event.data.limit_type} para ${event.data.tenant_id}`);
            // Enviar notificación al tenant
            break;
    }
    
    res.status(200).send('OK');
});
```

## 🔄 Rate Limiting

### Límites por Endpoint

| Endpoint | Límite | Ventana |
|----------|--------|---------|
| Admin APIs | 1000 req/min | Por token |
| Tenant APIs | 500 req/min | Por tenant |
| Webhooks | 100 req/min | Por webhook |
| Public APIs | 60 req/min | Por IP |

### Headers de Rate Limit

```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1653750000
```

## 📚 SDKs Disponibles

- **PHP**: `composer require crecepyme/php-sdk`
- **JavaScript**: `npm install @crecepyme/js-sdk`
- **Python**: `pip install crecepyme-sdk`

---

*Documentación API actualizada: 27/05/2025*