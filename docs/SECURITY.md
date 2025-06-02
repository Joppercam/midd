# Documentación de Seguridad - MIDD

## Resumen

MIDD implementa múltiples capas de seguridad para proteger la aplicación y los datos de los usuarios contra amenazas comunes. Esta documentación describe las medidas de seguridad implementadas y cómo funcionan.

## Arquitectura de Seguridad

### 1. Rate Limiting

**Ubicación**: `app/Http/Middleware/RateLimiting.php`

**Propósito**: Prevenir ataques de fuerza bruta y DoS limitando el número de requests por minuto.

**Configuración**:
- **API**: 60 requests/minuto
- **Autenticación**: 5 intentos/5 minutos
- **Reset de contraseña**: 3 intentos/15 minutos
- **SII**: 10 requests/minuto
- **Exportaciones**: 5 requests/5 minutos
- **Webhooks**: 100 requests/minuto

**Headers de respuesta**:
- `X-RateLimit-Limit`: Límite máximo
- `X-RateLimit-Remaining`: Requests restantes
- `Retry-After`: Tiempo de espera en segundos

### 2. Web Application Firewall (WAF)

**Ubicación**: `app/Http/Middleware/WAFProtection.php`

**Protecciones implementadas**:

#### SQL Injection
- Detección de patrones comunes: `UNION`, `SELECT`, `DROP`, `INSERT`
- Bloqueo de caracteres especiales: `;`, `|`, `--`, `/*`

#### Cross-Site Scripting (XSS)
- Filtrado de tags peligrosos: `<script>`, `<iframe>`, `javascript:`
- Detección de event handlers: `onclick`, `onload`, `onerror`

#### Path Traversal
- Bloqueo de secuencias: `../`, `..\\`
- Protección de archivos sensibles: `/etc/passwd`, `/windows/system32`

#### Command Injection
- Detección de caracteres de escape: `|`, `;`, `$()`, `$()`
- Bloqueo de comandos: `wget`, `curl`, `bash`, `powershell`

#### File Upload Security
- Extensiones bloqueadas: `.php`, `.exe`, `.sh`, `.bat`, `.dll`
- Límite de tamaño: 10MB por request

### 3. Security Headers

**Ubicación**: `app/Http/Middleware/SecurityHeaders.php`

**Headers implementados**:
- `X-XSS-Protection`: `1; mode=block`
- `X-Frame-Options`: `SAMEORIGIN`
- `X-Content-Type-Options`: `nosniff`
- `Referrer-Policy`: `strict-origin-when-cross-origin`
- `Permissions-Policy`: Restricciones de APIs del navegador
- `Content-Security-Policy`: Control de recursos cargados
- `Strict-Transport-Security`: Solo para HTTPS

### 4. Sistema de Detección de Intrusiones (IDS)

**Ubicación**: `app/Services/Security/IntrusionDetectionService.php`

**Características**:
- **Análisis de patrones sospechosos**
- **Detección de herramientas automatizadas**
- **Análisis de anomalías en requests**
- **Sistema de puntuación de amenazas**
- **Bloqueo automático de IPs maliciosas**

**Thresholds**:
- Failed logins: 5 intentos
- WAF blocks: 5 bloqueos
- 404 errors: 20 errores
- Suspicious requests: 3 requests

### 5. Autenticación de Dos Factores (2FA)

**Ubicación**: `app/Services/Security/TwoFactorAuthService.php`

**Implementación**:
- **Google Authenticator** compatible
- **Códigos de recuperación** (8 códigos únicos)
- **Dispositivos de confianza** (30 días por defecto)
- **Prevención de replay attacks**
- **QR codes** para configuración

**Flujo de activación**:
1. Usuario genera secret key
2. Escanea QR code en app authenticator
3. Verifica token para activar
4. Recibe códigos de recuperación

### 6. IP Blacklist/Whitelist

**Ubicación**: `app/Http/Middleware/IPBlacklist.php`

**Configuración**: `config/security.php`
- **Blacklist**: IPs bloqueadas permanentemente
- **Whitelist**: IPs que bypasean checks de seguridad
- **Soporte de wildcards**: `192.168.*`

## Configuración

### Variables de Entorno

```env
# Rate Limiting
RATE_LIMIT_GLOBAL=60
RATE_LIMIT_API=60
RATE_LIMIT_AUTH=5
RATE_LIMIT_PASSWORD_RESET=3

# WAF
WAF_ENABLED=true

# Content Security Policy
CSP_ENABLED=true
CSP_REPORT_ONLY=false
CSP_REPORT_URI=https://csp-report.crecepyme.cl

# HSTS
HSTS_ENABLED=true

# 2FA
2FA_ENABLED=true
2FA_ISSUER=CrecePyme

# Security
SECURITY_IP_BLACKLIST=192.168.1.100,malicious-ip.com
SECURITY_IP_WHITELIST=127.0.0.1,localhost
```

### Archivo de Configuración

**Ubicación**: `config/security.php`

Contiene todas las configuraciones de seguridad centralizadas:
- Límites de rate limiting
- Configuraciones de 2FA
- Settings de WAF
- Políticas de archivos
- Configuración de headers

## Logging y Auditoría

### Canal de Seguridad

**Configuración**: `config/logging.php`
- **Canal**: `security`
- **Archivo**: `storage/logs/security.log`
- **Retención**: 90 días

### Eventos Registrados

- Intentos de login fallidos
- Requests bloqueados por WAF
- Activaciones/desactivaciones de 2FA
- Detecciones del IDS
- Bloqueos de IP
- Cambios en configuración de seguridad

### Formato de Logs

```json
{
  "level": "warning",
  "message": "WAF blocked request",
  "context": {
    "reason": "SQL injection attempt detected",
    "ip": "192.168.1.100",
    "user_agent": "sqlmap/1.0",
    "uri": "/api/v1/users",
    "method": "POST",
    "user_id": 123,
    "tenant_id": "uuid-here"
  },
  "datetime": "2025-05-29T21:30:00+00:00"
}
```

## Middleware Stack

### Global Middleware
1. **IPBlacklist**: Bloqueo de IPs
2. **SecurityHeaders**: Headers de seguridad

### Web Middleware
1. **HandleInertiaRequests**
2. **AddLinkHeadersForPreloadedAssets**
3. **WAFProtection**: Filtros WAF

### API Middleware
1. **HandleApiErrors**
2. **WAFProtection**: Filtros WAF

### Named Middleware
- `rate_limit`: Rate limiting configurable
- `waf`: WAF protection
- `security_headers`: Security headers
- `ip_blacklist`: IP filtering

## Tests de Seguridad

### Cobertura de Tests

**Ubicación**: `tests/Unit/Security/`

- **RateLimitingTest**: Tests de rate limiting
- **WAFProtectionTest**: Tests de WAF
- **SecurityHeadersTest**: Tests de headers
- **TwoFactorAuthTest**: Tests de 2FA

### Ejecución de Tests

```bash
# Todos los tests de seguridad
php artisan test tests/Unit/Security/

# Test específico
php artisan test tests/Unit/Security/WAFProtectionTest
```

## Mejores Prácticas

### Para Desarrolladores

1. **Validación de Input**:
   - Siempre validar datos de entrada
   - Usar Form Requests de Laravel
   - Sanitizar datos antes de almacenar

2. **Autenticación**:
   - Implementar 2FA para usuarios admin
   - Rotar tokens API regularmente
   - Usar contraseñas fuertes

3. **Autorización**:
   - Verificar permisos en cada endpoint
   - Usar middleware de autorización
   - Principio de menor privilegio

4. **Logging**:
   - Registrar eventos de seguridad
   - No logear información sensible
   - Implementar alertas automáticas

### Para Administradores

1. **Monitoreo**:
   - Revisar logs de seguridad diariamente
   - Configurar alertas para eventos críticos
   - Monitorear métricas de rate limiting

2. **Mantenimiento**:
   - Actualizar blacklists regularmente
   - Rotar secrets de 2FA si es necesario
   - Revisar configuración de CSP

3. **Respuesta a Incidentes**:
   - Protocolo para bloquear IPs maliciosas
   - Proceso de investigación de breaches
   - Plan de comunicación con usuarios

## Alertas y Notificaciones

### Eventos Críticos

Los siguientes eventos generan alertas automáticas:
- Múltiples intentos de SQL injection
- Detección de herramientas de hacking
- Ataques de fuerza bruta
- Acceso con credenciales comprometidas

### Configuración de Alertas

```php
// En EventServiceProvider
SecurityAlertDetected::class => [
    SendSecurityAlert::class,
    NotifyAdministrators::class,
],
```

## Compliance y Estándares

### Estándares Implementados

- **OWASP Top 10**: Protecciones contra las 10 vulnerabilidades más críticas
- **PCI DSS**: Cumplimiento para manejo de datos de tarjetas
- **ISO 27001**: Prácticas de seguridad de información

### Auditorías

- **Auditorías internas**: Mensuales
- **Penetration testing**: Trimestrales
- **Code reviews**: En cada PR

## Contacto y Soporte

Para reportar vulnerabilidades de seguridad:
- **Email**: security@crecepyme.cl
- **Bug Bounty**: https://crecepyme.cl/security/bug-bounty

Para soporte técnico:
- **Email**: tech@crecepyme.cl
- **Documentación**: https://docs.crecepyme.cl

---

**Última actualización**: 29 de Mayo, 2025  
**Versión**: 1.0.0  
**Mantenido por**: Equipo de Seguridad CrecePyme