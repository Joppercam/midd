# Tests - MIDD

Esta documentación describe la estructura y estrategia de testing del proyecto MIDD.

## Estructura de Tests

### Tipos de Tests

1. **Tests Unitarios (`tests/Unit/`)**: 
   - Prueban componentes individuales de forma aislada
   - Modelos, servicios, helpers
   - Rápidos de ejecutar

2. **Tests de Integración (`tests/Feature/Controllers/`)**:
   - Prueban la interacción entre componentes
   - Controladores con middleware, permisos, base de datos
   - Respuestas HTTP y componentes Inertia

3. **Tests End-to-End (`tests/Feature/Workflows/`)**:
   - Prueban flujos completos de negocio
   - Simulan casos de uso reales
   - Verifican la integración completa del sistema

### Suites de Tests

```bash
# Ejecutar todos los tests
php artisan test

# Ejecutar solo tests unitarios
php artisan test --testsuite=Unit

# Ejecutar solo tests de modelos
php artisan test --testsuite=Models

# Ejecutar solo tests de servicios
php artisan test --testsuite=Services

# Ejecutar solo tests de controladores
php artisan test --testsuite=Controllers

# Ejecutar solo tests de workflows
php artisan test --testsuite=Workflows

# Ejecutar tests con coverage
php artisan test --coverage
```

## Configuración de Tests

### TestCase Base

La clase `TestCase` base proporciona:

- **Multi-tenancy**: Automáticamente crea un tenant y usuario de prueba
- **Permisos**: Helpers para crear usuarios con roles específicos
- **Base de datos**: RefreshDatabase para tests aislados
- **Autenticación**: Métodos para actuar como diferentes usuarios

### Helpers Disponibles

```php
// Crear usuario con rol específico
$admin = $this->createUserWithRole('admin');

// Crear permisos
$this->createPermission('invoices.view');

// Actuar como usuario autenticado
$this->actingAsUser($admin);

// Actuar como admin
$this->actingAsAdmin();
```

## Tests Implementados

### 1. Tests de Modelos (`tests/Unit/Models/`)

#### TaxDocumentTest
- ✅ Creación de documentos tributarios
- ✅ Relaciones (tenant, customer, items)
- ✅ Cálculo de tasa de IVA
- ✅ Determinación de estados (vencido, pagado)
- ✅ Formateo de montos
- ✅ Scopes (por estado, vencidos)

#### CustomerTest
- ✅ Creación de clientes
- ✅ Relaciones con documentos
- ✅ Cálculo de deuda total y vencida
- ✅ Formateo de RUT
- ✅ Scopes (con deuda, vencidos)
- ✅ Búsqueda por nombre y RUT

#### ProductTest
- ✅ Creación de productos
- ✅ Relaciones con inventario
- ✅ Cálculo de margen de ganancia
- ✅ Determinación de stock bajo/agotado
- ✅ Diferenciación producto/servicio
- ✅ Scopes (activos, stock bajo)

### 2. Tests de Controladores (`tests/Feature/Controllers/`)

#### InvoiceControllerTest
- ✅ Visualización de facturas con permisos
- ✅ Creación y validación de facturas
- ✅ Edición limitada por estado
- ✅ Eliminación con restricciones
- ✅ Envío de facturas
- ✅ Descarga de PDFs
- ✅ Filtros y búsquedas
- ✅ Aislamiento multi-tenant

#### TaxBookControllerTest
- ✅ Visualización del dashboard
- ✅ Generación de libros de compras y ventas
- ✅ Validación de datos de entrada
- ✅ Finalización de libros
- ✅ Exportación a Excel y PDF
- ✅ Filtrado por período
- ✅ Cálculo de resumen de IVA

### 3. Tests de Servicios (`tests/Unit/Services/`)

#### TaxBookServiceTest
- ✅ Generación de libros de ventas y compras
- ✅ Creación de entradas automáticas
- ✅ Finalización de libros
- ✅ Validaciones de estado
- ✅ Cálculo de resúmenes de IVA
- ✅ Filtrado por período y estado
- ✅ Regeneración de libros en borrador

#### SIIServiceTest
- ✅ Mapeo de códigos de documentos SII
- ✅ Validación de configuración del tenant
- ✅ Generación de XML para documentos
- ✅ Estructura correcta de DTEs
- ✅ Formateo de montos para SII
- ✅ Manejo de diferentes tipos de clientes
- ✅ Validación de RUT

### 4. Tests de Workflows (`tests/Feature/Workflows/`)

#### InvoicingWorkflowTest
- ✅ **Flujo completo de facturación**:
  1. Crear cliente
  2. Crear producto
  3. Crear factura
  4. Enviar factura (reduce stock)
  5. Descargar PDF
  6. Registrar pago
  7. Generar libro de ventas
  8. Verificar inclusión en libro

- ✅ **Flujo de estado de cuenta**:
  - Múltiples facturas por cliente
  - Pagos parciales
  - Cálculo de balances
  - Visualización de estado

- ✅ **Flujo de inventario**:
  - Reducción automática de stock
  - Movimientos de inventario
  - Ajustes manuales
  - Tracking completo

- ✅ **Aislamiento multi-tenant**:
  - Datos por tenant separados
  - Acceso restringido
  - Validaciones de seguridad

## Estrategia de Testing

### 1. Cobertura
- **Modelos**: 100% de métodos públicos
- **Controladores**: Todas las acciones CRUD y especiales
- **Servicios**: Lógica de negocio crítica
- **Workflows**: Casos de uso principales

### 2. Datos de Prueba
- **Factories**: Para generar datos consistentes
- **Seeders**: Para estados específicos de prueba
- **Mocks**: Para servicios externos (SII, emails)

### 3. Aislamiento
- **Base de datos**: RefreshDatabase en cada test
- **Multi-tenancy**: Tenant específico por test
- **Permisos**: Configuración limpia por test

### 4. Rendimiento
- **Tests rápidos**: Unitarios < 100ms
- **Tests de integración**: < 500ms
- **Tests E2E**: < 2s por workflow

## Comandos Útiles

```bash
# Ejecutar tests específicos
php artisan test --filter=TaxDocumentTest

# Ejecutar tests con coverage HTML
php artisan test --coverage-html coverage

# Ejecutar tests en paralelo
php artisan test --parallel

# Ejecutar solo tests que fallaron
php artisan test --group=failing

# Generar reporte de coverage
php artisan test --coverage-text
```

## Mejores Prácticas

### 1. Nomenclatura
```php
/** @test */
public function it_can_create_a_tax_document()
{
    // Arrange, Act, Assert
}
```

### 2. Estructura AAA
```php
// Arrange: Preparar datos
$customer = Customer::factory()->create();

// Act: Ejecutar acción
$response = $this->post(route('invoices.store'), $data);

// Assert: Verificar resultado
$response->assertRedirect();
$this->assertDatabaseHas('tax_documents', $data);
```

### 3. Tests Descriptivos
- Nombres que describen el comportamiento
- Un concepto por test
- Assertions claras y específicas

### 4. Mantenimiento
- Tests independientes
- Datos mínimos necesarios
- Limpieza automática entre tests

## Próximos Pasos

1. **Ampliar cobertura**:
   - Tests para más servicios (BackupService, EmailService)
   - Tests para middleware personalizados
   - Tests para jobs y eventos

2. **Tests de rendimiento**:
   - Benchmarks para consultas complejas
   - Tests de carga para endpoints críticos

3. **Tests de seguridad**:
   - Validación de inyección SQL
   - Tests de autorización exhaustivos
   - Validación de inputs maliciosos

4. **Automatización**:
   - CI/CD con GitHub Actions
   - Tests automáticos en pull requests
   - Reportes de coverage automáticos