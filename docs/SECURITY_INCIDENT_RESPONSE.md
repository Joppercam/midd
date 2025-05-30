# Plan de Respuesta a Incidentes de Seguridad - CrecePyme

## Clasificación de Incidentes

### Nivel 1 - Crítico
- Acceso no autorizado a datos sensibles
- Compromiso de cuentas administrativas
- Ataques de ransomware o malware
- Caída del sistema por ataque DDoS
- Filtración de datos de clientes

### Nivel 2 - Alto
- Múltiples intentos de fuerza bruta exitosos
- Detección de herramientas de hacking
- Acceso no autorizado a sistemas internos
- Modificación no autorizada de datos

### Nivel 3 - Medio
- Ataques de fuerza bruta bloqueados
- Intentos de SQL injection detectados
- Escaneo de vulnerabilidades detectado
- Acceso desde ubicaciones sospechosas

### Nivel 4 - Bajo
- Rate limiting activado frecuentemente
- Intentos menores de acceso no autorizado
- Alertas de WAF por contenido sospechoso

## Procedimientos de Respuesta

### Respuesta Inmediata (0-15 minutos)

#### Para Incidentes Nivel 1 (Crítico)
1. **Aislamiento inmediato**:
```bash
# Bloquear IP atacante
php artisan security:ban-ip [IP_ADDRESS] --permanent

# Revocar todas las sesiones activas
php artisan auth:clear-sessions

# Activar modo mantenimiento
php artisan down --secret=emergency-access-2025
```

2. **Notificación de emergencia**:
   - Contactar al CISO inmediatamente
   - Notificar al equipo de desarrollo
   - Activar equipo de respuesta a incidentes

#### Para Incidentes Nivel 2-3
1. **Evaluación inicial**:
```bash
# Revisar logs de seguridad
tail -f storage/logs/security.log

# Verificar métricas del sistema
php artisan security:status

# Analizar tráfico sospechoso
php artisan security:analyze-traffic --last-hour
```

2. **Contención básica**:
```bash
# Bloquear IP sospechosa temporalmente
php artisan security:ban-ip [IP_ADDRESS] --hours=24

# Aumentar nivel de logging
php artisan security:increase-logging
```

### Investigación (15-60 minutos)

#### 1. Recolección de Evidencia
```bash
# Exportar logs relevantes
php artisan security:export-logs --incident=[INCIDENT_ID]

# Capturar estado del sistema
php artisan system:snapshot --security-incident

# Revisar accesos recientes
php artisan audit:recent-access --hours=24
```

#### 2. Análisis de Impacto
- Identificar sistemas afectados
- Evaluar datos comprometidos
- Determinar vectores de ataque
- Estimar tiempo de exposición

#### 3. Análisis Forense
```bash
# Generar reporte forense
php artisan security:forensic-report [INCIDENT_ID]

# Analizar patrones de acceso
php artisan security:analyze-patterns --incident=[INCIDENT_ID]

# Verificar integridad de datos
php artisan security:verify-integrity
```

### Contención y Erradicación (1-4 horas)

#### 1. Contención Completa
```bash
# Activar todas las protecciones
php artisan security:lockdown-mode

# Forzar re-autenticación
php artisan auth:force-reauth --all-users

# Activar 2FA obligatorio temporalmente
php artisan security:force-2fa --emergency
```

#### 2. Erradicación de Amenazas
```bash
# Limpiar archivos maliciosos
php artisan security:clean-malicious-files

# Actualizar reglas de WAF
php artisan security:update-waf-rules

# Parchear vulnerabilidades identificadas
php artisan security:apply-emergency-patches
```

#### 3. Fortalecimiento
```bash
# Actualizar blacklists
php artisan security:update-blacklists --emergency

# Aumentar restricciones temporalmente
php artisan security:tighten-restrictions

# Activar monitoreo adicional
php artisan security:enhanced-monitoring
```

### Recuperación (4-24 horas)

#### 1. Restauración de Servicios
```bash
# Verificar integridad del sistema
php artisan security:system-integrity-check

# Restaurar desde backup limpio si es necesario
php artisan backup:restore --verified-clean

# Reactivar servicios gradualmente
php artisan services:gradual-restore
```

#### 2. Validación de Seguridad
```bash
# Ejecutar tests de seguridad completos
php artisan test tests/Unit/Security/ --complete

# Verificar configuraciones
php artisan security:validate-config

# Comprobar logs limpios
php artisan security:verify-clean-logs
```

#### 3. Comunicación
- Notificar a usuarios afectados (si aplica)
- Informar a autoridades regulatorias (si es requerido)
- Actualizar stakeholders internos

## Scripts de Emergencia

### Script de Bloqueo de Emergencia
```bash
#!/bin/bash
# emergency-lockdown.sh

echo "🚨 INICIANDO BLOQUEO DE EMERGENCIA"

# Activar modo mantenimiento
php artisan down --secret=emergency-2025

# Bloquear IP atacante
if [ ! -z "$1" ]; then
    php artisan security:ban-ip $1 --permanent
    echo "✅ IP $1 bloqueada permanentemente"
fi

# Revocar todas las sesiones
php artisan auth:clear-sessions
echo "✅ Sesiones limpiadas"

# Activar máximo nivel de seguridad
php artisan security:maximum-protection
echo "✅ Protección máxima activada"

# Notificar equipo
php artisan notify:security-team "EMERGENCIA: Sistema en lockdown"
echo "✅ Equipo notificado"

echo "🔒 SISTEMA ASEGURADO"
```

### Script de Análisis Rápido
```bash
#!/bin/bash
# quick-analysis.sh

echo "🔍 ANÁLISIS RÁPIDO DE SEGURIDAD"

# Verificar ataques recientes
echo "📊 Ataques bloqueados (última hora):"
grep -c "blocked" storage/logs/security.log | tail -10

# IPs más activas
echo "🌐 IPs más activas:"
php artisan security:top-ips --last-hour

# Eventos críticos
echo "⚠️ Eventos críticos:"
php artisan security:critical-events --today

# Estado del sistema
echo "💻 Estado del sistema:"
php artisan security:status
```

## Comunicación de Crisis

### Plantillas de Comunicación

#### Para Usuarios (Incidente Crítico)
```
Asunto: Importante: Actualización de Seguridad - CrecePyme

Estimado usuario,

Hemos detectado actividad sospechosa en nuestros sistemas y hemos tomado medidas preventivas inmediatas para proteger su información.

ACCIONES TOMADAS:
- Sistema temporalmente en mantenimiento
- Todas las sesiones han sido cerradas por seguridad
- Investigación en curso

ACCIONES REQUERIDAS:
- Cambie su contraseña cuando el servicio se restablezca
- Active autenticación de dos factores
- Revise su actividad reciente

Tiempo estimado de resolución: [TIEMPO]

Equipo de Seguridad CrecePyme
```

#### Para Autoridades Regulatorias
```
Reporte de Incidente de Seguridad - CrecePyme
Fecha: [FECHA]
Número de Incidente: [ID]

RESUMEN:
- Tipo de incidente: [TIPO]
- Hora de detección: [HORA]
- Sistemas afectados: [SISTEMAS]
- Datos comprometidos: [DATOS]

ACCIONES TOMADAS:
- [LISTA DE ACCIONES]

ESTADO ACTUAL:
- [ESTADO]

PRÓXIMOS PASOS:
- [PASOS]

Contacto: security@crecepyme.cl
```

## Herramientas de Monitoreo

### Dashboard de Incidentes
```php
// Métricas en tiempo real
Route::get('/security/dashboard', function () {
    return [
        'active_threats' => SecurityThreat::active()->count(),
        'blocked_ips' => Cache::get('blocked_ips_count', 0),
        'failed_logins_hour' => Cache::get('failed_logins_hour', 0),
        'waf_blocks_hour' => Cache::get('waf_blocks_hour', 0),
        'system_status' => 'normal', // critical, warning, normal
    ];
});
```

### Alertas Automatizadas
```php
// En EventServiceProvider
SecurityIncidentDetected::class => [
    NotifySecurityTeam::class,
    LogIncident::class,
    TriggerAutomaticResponse::class,
],

CriticalSecurityAlert::class => [
    SendSMSAlert::class,
    CallSecurityTeam::class,
    EscalateToManagement::class,
],
```

## Escalación

### Nivel 1: Equipo Técnico
- **Timeframe**: 0-15 minutos
- **Personal**: DevOps, Desarrolladores Senior
- **Autoridad**: Contención técnica inmediata

### Nivel 2: Gerencia Técnica
- **Timeframe**: 15-30 minutos
- **Personal**: CTO, Tech Lead, CISO
- **Autoridad**: Decisiones de sistema y comunicación

### Nivel 3: Dirección Ejecutiva
- **Timeframe**: 30-60 minutos
- **Personal**: CEO, Legal, PR
- **Autoridad**: Decisiones de negocio y comunicación externa

## Post-Incidente

### Análisis Post-Mortem (24-72 horas)
1. **Reunión de revisión**:
   - ¿Qué funcionó bien?
   - ¿Qué se puede mejorar?
   - ¿Qué lecciones aprendimos?

2. **Documentación**:
   - Timeline completo del incidente
   - Acciones tomadas y resultados
   - Impacto en el negocio

3. **Mejoras implementadas**:
   - Actualizaciones de código
   - Nuevas reglas de seguridad
   - Procedimientos mejorados

### Reporte Final
```
REPORTE POST-INCIDENTE #[ID]

RESUMEN EJECUTIVO:
[Descripción breve del incidente y su resolución]

TIMELINE:
[Cronología detallada de eventos]

IMPACTO:
- Usuarios afectados: [NÚMERO]
- Tiempo de inactividad: [TIEMPO]
- Datos comprometidos: [SÍ/NO + detalles]

CAUSA RAÍZ:
[Análisis de la causa fundamental]

LECCIONES APRENDIDAS:
[Lista de aprendizajes clave]

ACCIONES CORRECTIVAS:
[Medidas implementadas para prevenir recurrencia]

SIGUIENTE REVISIÓN: [FECHA]
```

## Contactos de Emergencia

### Equipo Interno
- **CISO**: +56 9 1234 5678 (ciso@crecepyme.cl)
- **CTO**: +56 9 8765 4321 (cto@crecepyme.cl)
- **DevOps Lead**: +56 9 5555 1234 (devops@crecepyme.cl)

### Proveedores Externos
- **Hosting**: [CONTACTO_HOSTING]
- **CDN**: [CONTACTO_CDN]
- **Seguridad Externa**: [CONTACTO_SECURITY]

### Autoridades
- **CSIRT Chile**: csirt@csirt.gov.cl
- **PDI Cibercrimen**: cibercrimen@investigaciones.cl

---

**Versión**: 1.0  
**Última actualización**: 29 de Mayo, 2025  
**Próxima revisión**: 29 de Agosto, 2025