# Guía de Configuración de Seguridad - CrecePyme

## Configuración Inicial

### 1. Variables de Entorno

Agregar al archivo `.env`:

```env
# Configuración de Seguridad
WAF_ENABLED=true
CSP_ENABLED=true
HSTS_ENABLED=true
2FA_ENABLED=true

# Rate Limiting
RATE_LIMIT_GLOBAL=60
RATE_LIMIT_API=60
RATE_LIMIT_AUTH=5
RATE_LIMIT_PASSWORD_RESET=3

# Content Security Policy
CSP_REPORT_ONLY=false
CSP_REPORT_URI=https://csp-reports.crecepyme.cl/report

# Two Factor Authentication
2FA_ISSUER=CrecePyme

# IP Security
SECURITY_IP_BLACKLIST=
SECURITY_IP_WHITELIST=127.0.0.1,::1

# API Security
API_REQUIRE_HTTPS=true

# Session Security
SESSION_TIMEOUT=120
SESSION_SINGLE_DEVICE=true

# Password Security
CHECK_PWNED_PASSWORDS=true

# File Upload Security
SCAN_UPLOADS=false
```

### 2. Ejecutar Migraciones

```bash
# Migración para campos 2FA en usuarios
php artisan migrate
```

### 3. Configurar Middleware

El middleware ya está registrado en `bootstrap/app.php`. Para aplicar protecciones específicas:

```php
// En rutas específicas
Route::group(['middleware' => ['rate_limit:api']], function () {
    // Rutas de API con rate limiting específico
});

Route::group(['middleware' => ['waf']], function () {
    // Rutas con protección WAF adicional
});
```

## Configuración Avanzada

### 1. Content Security Policy (CSP)

Personalizar CSP en `app/Http/Middleware/SecurityHeaders.php`:

```php
protected function getCSP(Request $request): string
{
    $nonce = $this->generateNonce();
    
    $policies = [
        "default-src 'self'",
        "script-src 'self' 'nonce-{$nonce}' https://cdn.tudominio.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data: https:",
        "connect-src 'self' wss://tu-websocket.com",
        // Agregar dominios específicos según necesidades
    ];

    return implode('; ', $policies);
}
```

### 2. WAF Personalizado

Agregar patrones específicos en `app/Http/Middleware/WAFProtection.php`:

```php
protected array $customPatterns = [
    // Patrones específicos de tu aplicación
    '/pattern1/i',
    '/pattern2/i',
];

protected array $businessSpecificBlocks = [
    // Rutas o patrones específicos del negocio
    '/admin-legacy',
    '/old-api',
];
```

### 3. Rate Limiting por Tenant

```php
// En RateLimiting middleware
protected function resolveRequestKey(Request $request, string $limiterName): string
{
    if ($user = $request->user()) {
        $tenantId = $user->tenant_id;
        return "{$limiterName}:tenant:{$tenantId}:user:{$user->id}";
    }

    return "{$limiterName}:ip:" . $request->ip();
}
```

## Configuración de Producción

### 1. HTTPS y SSL

```nginx
# Configuración Nginx
server {
    listen 443 ssl http2;
    server_name crecepyme.cl;

    ssl_certificate /path/to/certificate.pem;
    ssl_certificate_key /path/to/private-key.pem;
    
    # SSL Security
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    
    # Headers de Seguridad adicionales
    add_header X-Frame-Options SAMEORIGIN always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Host $host;
    }
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name crecepyme.cl;
    return 301 https://$server_name$request_uri;
}
```

### 2. Configuración de Base de Datos

```env
# Usar conexiones SSL para base de datos
DB_SSLMODE=require
DB_SSLCERT=/path/to/client-cert.pem
DB_SSLKEY=/path/to/client-key.pem
DB_SSLROOTCERT=/path/to/ca-cert.pem
```

### 3. Redis Seguro

```env
# Redis con autenticación
REDIS_PASSWORD=tu-password-super-seguro
REDIS_CLIENT=predis

# Para rate limiting y caché
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

## Configuración de Logging

### 1. Configurar Canal de Seguridad

Ya configurado en `config/logging.php`. Para personalizar:

```php
'security' => [
    'driver' => 'stack',
    'channels' => ['security_file', 'security_slack'],
],

'security_file' => [
    'driver' => 'daily',
    'path' => storage_path('logs/security/security.log'),
    'level' => 'info',
    'days' => 90,
],

'security_slack' => [
    'driver' => 'slack',
    'url' => env('SECURITY_SLACK_WEBHOOK'),
    'username' => 'Security Bot',
    'emoji' => ':warning:',
    'level' => 'error',
],
```

### 2. Alertas Automáticas

```php
// En EventServiceProvider
protected $listen = [
    SecurityAlertDetected::class => [
        SendSecurityAlert::class,
        LogSecurityEvent::class,
        NotifyAdministrators::class,
    ],
];
```

## Configuración de 2FA

### 1. Para Usuarios Nuevos

```php
// Forzar 2FA para ciertos roles
protected function boot()
{
    User::created(function ($user) {
        if ($user->hasRole(['admin', 'super-admin'])) {
            $user->update(['force_two_factor' => true]);
        }
    });
}
```

### 2. Configuración de QR Codes

```php
// En TwoFactorAuthService
public function generateQRCode(User $user): string
{
    $secret = $user->two_factor_secret;
    $company = config('app.name');
    $email = $user->email;
    
    $qrCodeUrl = $this->google2fa->getQRCodeUrl(
        $company,
        $email,
        $secret
    );

    return $this->generateQRCodeImage($qrCodeUrl);
}
```

## Monitoreo y Alertas

### 1. Configurar Monitoreo

```php
// Comando artisan para monitoreo
php artisan make:command SecurityMonitor

// En el comando
public function handle()
{
    $threats = $this->analyzeSecurityLogs();
    $suspiciousIPs = $this->findSuspiciousActivity();
    
    if ($threats->isNotEmpty()) {
        $this->sendAlert($threats);
    }
}
```

### 2. Dashboard de Seguridad

```php
// Métricas para dashboard
public function getSecurityMetrics()
{
    return [
        'blocked_requests_today' => Cache::get('waf_blocks_today', 0),
        'failed_logins_today' => Cache::get('failed_logins_today', 0),
        'banned_ips_count' => Cache::get('banned_ips_count', 0),
        'active_2fa_users' => User::where('two_factor_enabled', true)->count(),
    ];
}
```

## Testing de Seguridad

### 1. Tests Automatizados

```bash
# Ejecutar todos los tests de seguridad
php artisan test tests/Unit/Security/ --parallel

# Test específico con cobertura
php artisan test tests/Unit/Security/WAFProtectionTest --coverage
```

### 2. Penetration Testing

```bash
# Herramientas recomendadas
# 1. OWASP ZAP
# 2. Burp Suite
# 3. SQLMap (para testing autorizado)

# Script básico de testing
#!/bin/bash
echo "Testing Rate Limiting..."
for i in {1..10}; do
    curl -X POST https://crecepyme.cl/login
done

echo "Testing WAF..."
curl "https://crecepyme.cl/search?q=1' OR '1'='1"
```

## Respuesta a Incidentes

### 1. Procedimientos Automáticos

```php
// Bloqueo automático de IP
public function handleSecurityIncident($threat)
{
    if ($threat->severity === 'critical') {
        $this->blockIP($threat->ip, '24 hours');
        $this->notifySecurityTeam($threat);
        $this->escalateToManagement($threat);
    }
}
```

### 2. Recuperación

```bash
# Comandos de emergencia
php artisan security:unban-ip 192.168.1.100
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

## Mantenimiento

### 1. Tareas Diarias

```bash
# Limpiar logs antiguos
php artisan log:clear --days=90

# Verificar IPs bloqueadas
php artisan security:review-blocked-ips

# Generar reporte de seguridad
php artisan security:daily-report
```

### 2. Tareas Semanales

```bash
# Actualizar blacklists
php artisan security:update-blacklists

# Revisar configuración CSP
php artisan security:validate-csp

# Auditoría de permisos
php artisan security:audit-permissions
```

## Troubleshooting

### Problemas Comunes

1. **CSP bloqueando recursos**:
```php
// Verificar en DevTools Console
// Ajustar políticas en SecurityHeaders middleware
```

2. **Rate limiting muy estricto**:
```php
// Ajustar límites en config/security.php
// Verificar keys de caché
Cache::flush(); // Si es necesario
```

3. **WAF bloqueando requests legítimos**:
```php
// Revisar logs de seguridad
tail -f storage/logs/security.log

// Agregar excepciones si es necesario
```

## Contacto de Emergencia

- **Incidentes críticos**: security@crecepyme.cl
- **Soporte técnico**: tech@crecepyme.cl
- **Escalación**: management@crecepyme.cl

---

**Importante**: Esta configuración debe ser revisada y ajustada según las necesidades específicas del entorno de producción.