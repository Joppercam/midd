# 🔄 Guía de Migración al Sistema Modular

## 📋 Índice
1. [Visión General](#visión-general)
2. [Plan de Migración](#plan-de-migración)
3. [Migración por Módulos](#migración-por-módulos)
4. [Refactoring de Código](#refactoring-de-código)
5. [Testing de Migración](#testing-de-migración)
6. [Checklist de Validación](#checklist-de-validación)

## 🎯 Visión General

Esta guía detalla cómo migrar el código existente de CrecePyme desde una arquitectura monolítica hacia el nuevo sistema modular, manteniendo la funcionalidad existente mientras se prepara para futuras expansiones.

### Objetivos de la Migración

- ✅ **Preservar funcionalidad**: Sin pérdida de características existentes
- ✅ **Mejorar organización**: Código más modular y mantenible
- ✅ **Habilitar escalabilidad**: Base para nuevos módulos
- ✅ **Optimizar performance**: Carga selectiva de funcionalidades
- ✅ **Facilitar testing**: Tests específicos por módulo

## 📅 Plan de Migración

### Fase 1: Preparación (Completada ✅)
- [x] Sistema de base de datos modular
- [x] ModuleManager service
- [x] Middleware de verificación
- [x] Panel de administración
- [x] Helpers globales

### Fase 2: Migración de Módulos Core (En Progreso 🔄)
- [ ] Módulo Core (usuarios, dashboard, configuración)
- [ ] Módulo Invoicing (facturación existente)
- [ ] Módulo Customers (gestión de clientes)
- [ ] Módulo Inventory (productos y stock)
- [ ] Módulo Payments (pagos y cobranza)

### Fase 3: Módulos de Expansión (Pendiente 📅)
- [ ] Módulo Banking (conciliación)
- [ ] Módulo Suppliers (proveedores y gastos)
- [ ] Módulo Quotes (cotizaciones)
- [ ] Módulo Analytics (reportes)

### Fase 4: Nuevos Módulos (Futuro 🔮)
- [ ] Módulo HRM (recursos humanos)
- [ ] Módulo CRM (gestión comercial avanzada)
- [ ] Módulo E-commerce (tienda online)
- [ ] Módulo POS (punto de venta)

## 🔧 Migración por Módulos

### 1. Módulo Core

#### Estado Actual
```
app/Http/Controllers/
├── Dashboard/DashboardController.php
├── Users/UserController.php
├── Admin/UserController.php
├── Admin/RoleController.php
└── Settings/CompanySettingsController.php
```

#### Estado Objetivo
```
app/Modules/Core/
├── Module.php
├── config.php
├── Controllers/
│   ├── DashboardController.php
│   ├── UserController.php
│   ├── RoleController.php
│   └── SettingsController.php
├── Services/
│   ├── UserService.php
│   └── DashboardService.php
└── resources/js/
    ├── Pages/
    │   ├── Dashboard.vue
    │   ├── Users/
    │   └── Settings/
    └── Components/
```

#### Pasos de Migración

1. **Crear estructura del módulo**
   ```bash
   mkdir -p app/Modules/Core/{Controllers,Services,Views}
   mkdir -p app/Modules/Core/resources/js/{Pages,Components}
   ```

2. **Mover controladores**
   ```bash
   # Mover y actualizar namespace
   mv app/Http/Controllers/Dashboard/DashboardController.php app/Modules/Core/Controllers/
   # Actualizar namespace: App\Http\Controllers\Dashboard -> App\Modules\Core\Controllers
   ```

3. **Actualizar rutas**
   ```php
   // app/Modules/Core/routes.php
   use App\Modules\Core\Controllers\DashboardController;
   
   Route::middleware(['module:core'])->group(function () {
       Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
       // ... más rutas
   });
   ```

4. **Mover vistas Vue**
   ```bash
   mv resources/js/Pages/Dashboard.vue app/Modules/Core/resources/js/Pages/
   mv resources/js/Pages/Users/ app/Modules/Core/resources/js/Pages/
   ```

### 2. Módulo Invoicing

#### Estado Actual
```
app/Http/Controllers/Billing/InvoiceController.php
app/Models/TaxDocument.php
resources/js/Pages/Billing/
```

#### Refactoring Requerido

1. **Crear módulo**
   ```php
   // app/Modules/Invoicing/Module.php
   class Module extends BaseModule
   {
       public function getCode(): string { return 'invoicing'; }
       public function getName(): string { return 'Facturación Electrónica'; }
       public function getDependencies(): array { return ['core', 'tenancy']; }
   }
   ```

2. **Mover controlador**
   ```php
   // app/Modules/Invoicing/Controllers/InvoiceController.php
   namespace App\Modules\Invoicing\Controllers;
   
   use App\Http\Controllers\Controller;
   use App\Modules\Invoicing\Services\InvoiceService;
   
   class InvoiceController extends Controller
   {
       protected InvoiceService $invoiceService;
       
       public function __construct(InvoiceService $invoiceService)
       {
           $this->invoiceService = $invoiceService;
           $this->middleware('module:invoicing');
       }
   }
   ```

3. **Crear servicio específico**
   ```php
   // app/Modules/Invoicing/Services/InvoiceService.php
   namespace App\Modules\Invoicing\Services;
   
   class InvoiceService
   {
       public function createInvoice(array $data): TaxDocument
       {
           // Lógica de creación de factura
           logModuleUsage('invoicing', 'create_invoice', ['amount' => $data['total']]);
           
           return TaxDocument::create($data);
       }
   }
   ```

### 3. Migración de Rutas

#### Antes (Monolítico)
```php
// routes/web.php
Route::resource('invoices', App\Http\Controllers\Billing\InvoiceController::class);
Route::resource('customers', App\Http\Controllers\Customers\CustomerController::class);
```

#### Después (Modular)
```php
// routes/web.php
// Las rutas se cargan automáticamente desde cada módulo

// app/Modules/Invoicing/routes.php
Route::middleware(['module:invoicing'])->group(function () {
    Route::resource('invoices', InvoiceController::class);
});

// app/Modules/Customers/routes.php  
Route::middleware(['module:customers'])->group(function () {
    Route::resource('customers', CustomerController::class);
});
```

## 🔨 Refactoring de Código

### 1. Controladores

#### Antes
```php
class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = TaxDocument::where('tenant_id', auth()->user()->tenant_id)
            ->paginate(15);
            
        return Inertia::render('Billing/Invoices/Index', [
            'invoices' => $invoices
        ]);
    }
}
```

#### Después
```php
class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;
    
    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
        $this->middleware('module:invoicing'); // Verificación automática
    }
    
    public function index()
    {
        // El middleware ya verificó acceso al módulo
        $invoices = $this->invoiceService->getInvoicesForTenant(tenant());
        
        // Registrar uso automáticamente
        logModuleUsage('invoicing', 'view_list');
        
        return Inertia::render('Invoicing/Index', [
            'invoices' => $invoices
        ]);
    }
}
```

### 2. Vistas Vue

#### Antes
```vue
<!-- resources/js/Pages/Billing/Invoices/Index.vue -->
<template>
    <AuthenticatedLayout>
        <h1>Facturas</h1>
        <Link :href="route('invoices.create')">Nueva Factura</Link>
    </AuthenticatedLayout>
</template>
```

#### Después
```vue
<!-- app/Modules/Invoicing/resources/js/Pages/Index.vue -->
<template>
    <AuthenticatedLayout>
        <div v-if="hasModuleAccess('invoicing')">
            <h1>Facturas</h1>
            <Link :href="moduleRoute('invoicing', 'create')">Nueva Factura</Link>
        </div>
        <ModuleUpgradePrompt v-else module="invoicing" />
    </AuthenticatedLayout>
</template>

<script setup>
import { hasModuleAccess, moduleRoute } from '@/utils/modules';
import ModuleUpgradePrompt from '@/Components/ModuleUpgradePrompt.vue';
</script>
```

### 3. Servicios

#### Crear servicios específicos por módulo
```php
// app/Modules/Invoicing/Services/InvoiceService.php
namespace App\Modules\Invoicing\Services;

use App\Models\TaxDocument;
use App\Modules\Invoicing\Events\InvoiceCreated;

class InvoiceService
{
    public function createInvoice(array $data): TaxDocument
    {
        $invoice = TaxDocument::create([
            ...$data,
            'tenant_id' => tenant()->id,
            'created_from' => 'invoicing_module'
        ]);
        
        // Registrar uso del módulo
        logModuleUsage('invoicing', 'create_invoice', [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total
        ]);
        
        // Disparar evento
        event(new InvoiceCreated($invoice));
        
        return $invoice;
    }
    
    public function getInvoicesForTenant(Tenant $tenant)
    {
        return TaxDocument::where('tenant_id', $tenant->id)
            ->with(['customer', 'items'])
            ->orderByDesc('created_at')
            ->paginate(15);
    }
}
```

### 4. Menús Dinámicos

#### Antes (Estático)
```php
// En algún helper o provider
$menuItems = [
    ['label' => 'Dashboard', 'route' => 'dashboard'],
    ['label' => 'Facturas', 'route' => 'invoices.index'],
    ['label' => 'Clientes', 'route' => 'customers.index'],
];
```

#### Después (Dinámico por Módulos)
```php
// app/Services/MenuService.php
class MenuService
{
    protected ModuleManager $moduleManager;
    
    public function getMenuForUser(User $user): array
    {
        $tenant = $user->tenant;
        $activeModules = $this->moduleManager->getTenantModules($tenant);
        
        $menu = [];
        
        foreach ($activeModules as $tenantModule) {
            $moduleClass = $tenantModule->systemModule->getModuleClass();
            if ($moduleClass) {
                $moduleInstance = new $moduleClass();
                $menuItems = $moduleInstance->getMenuItems();
                
                foreach ($menuItems as $item) {
                    if ($user->can($item['permission'] ?? 'view')) {
                        $menu[] = $item;
                    }
                }
            }
        }
        
        return collect($menu)->sortBy('order')->values()->toArray();
    }
}
```

## 🧪 Testing de Migración

### Tests de Migración

```php
// tests/Feature/Migration/ModuleMigrationTest.php
class ModuleMigrationTest extends TestCase
{
    public function test_invoicing_module_routes_work()
    {
        $user = User::factory()->create();
        $tenant = $user->tenant;
        
        // Habilitar módulo de facturación
        $moduleManager = app(ModuleManager::class);
        $invoicingModule = SystemModule::where('code', 'invoicing')->first();
        $moduleManager->enableModule($tenant, $invoicingModule);
        
        // Verificar que las rutas funcionan
        $response = $this->actingAs($user)->get('/invoicing');
        $response->assertOk();
    }
    
    public function test_module_access_is_denied_when_disabled()
    {
        $user = User::factory()->create();
        
        // No habilitar el módulo
        $response = $this->actingAs($user)->get('/invoicing');
        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error');
    }
    
    public function test_existing_functionality_preserved()
    {
        $user = User::factory()->create();
        $tenant = $user->tenant;
        
        // Habilitar módulos necesarios
        $this->enableModulesForTenant($tenant, ['core', 'invoicing', 'customers']);
        
        // Crear factura usando el flujo migrado
        $response = $this->actingAs($user)->post('/invoicing', [
            'customer_id' => Customer::factory()->create(['tenant_id' => $tenant->id])->id,
            'type' => 'invoice',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'price' => 1000]
            ]
        ]);
        
        $response->assertRedirect();
        $this->assertDatabaseHas('tax_documents', [
            'tenant_id' => $tenant->id,
            'type' => 'invoice'
        ]);
    }
}
```

### Tests de Compatibilidad

```php
// tests/Feature/Migration/BackwardCompatibilityTest.php
class BackwardCompatibilityTest extends TestCase
{
    public function test_old_routes_still_work()
    {
        // Verificar que las rutas antiguas siguen funcionando
        // mediante redirects o aliases
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->get('/invoices'); // Ruta antigua
        $response->assertRedirect('/invoicing'); // Nueva ruta modular
    }
    
    public function test_existing_data_accessible()
    {
        $tenant = Tenant::factory()->create();
        $invoice = TaxDocument::factory()->create(['tenant_id' => $tenant->id]);
        
        // Verificar que los datos existentes son accesibles
        // a través del nuevo sistema modular
        $moduleManager = app(ModuleManager::class);
        $this->assertTrue($moduleManager->hasAccess($tenant, 'invoicing'));
        
        $invoiceService = app(\App\Modules\Invoicing\Services\InvoiceService::class);
        $invoices = $invoiceService->getInvoicesForTenant($tenant);
        
        $this->assertTrue($invoices->contains('id', $invoice->id));
    }
}
```

## ✅ Checklist de Validación

### Antes de Migrar un Módulo

- [ ] **Identificar dependencias**: Qué otros módulos/servicios necesita
- [ ] **Mapear controladores**: Qué controladores van al módulo
- [ ] **Identificar modelos**: Qué modelos son específicos del módulo
- [ ] **Revisar rutas**: Qué rutas deben protegerse con middleware
- [ ] **Identificar vistas**: Qué componentes Vue van al módulo
- [ ] **Revisar permisos**: Qué permisos otorga el módulo

### Durante la Migración

- [ ] **Crear estructura de carpetas** según estándar modular
- [ ] **Mover archivos** y actualizar namespaces
- [ ] **Implementar clase Module** con configuración completa
- [ ] **Agregar middleware** de verificación a rutas
- [ ] **Crear servicios específicos** para lógica de negocio
- [ ] **Actualizar imports** en todos los archivos afectados

### Después de Migrar

- [ ] **Ejecutar tests** específicos del módulo
- [ ] **Verificar funcionalidad** en desarrollo
- [ ] **Probar middleware** de verificación de acceso
- [ ] **Validar logging** de uso del módulo
- [ ] **Revisar performance** vs versión anterior
- [ ] **Documentar cambios** y nuevas funcionalidades

### Validación de Integración

- [ ] **Sistema de permisos** funciona correctamente
- [ ] **Menús dinámicos** se generan bien
- [ ] **Cache de módulos** se invalida apropiadamente
- [ ] **API endpoints** responden correctamente
- [ ] **Webhooks** se disparan para eventos del módulo
- [ ] **Logs de auditoría** registran acciones correctamente

## 🚨 Problemas Comunes y Soluciones

### 1. Imports Rotos

**Problema**: Después de mover archivos, los imports no funcionan.

**Solución**:
```bash
# Buscar y reemplazar imports
find . -name "*.php" -exec sed -i 's/App\\Http\\Controllers\\Billing/App\\Modules\\Invoicing\\Controllers/g' {} \;

# Regenerar autoload
composer dump-autoload
```

### 2. Rutas No Encontradas

**Problema**: Las rutas del módulo no se cargan.

**Solución**:
```php
// Verificar que el módulo esté registrado en el provider
// app/Providers/AppServiceProvider.php
public function boot()
{
    // Cargar módulos automáticamente
    $this->loadModules();
}

private function loadModules()
{
    $modulesPath = app_path('Modules');
    $modules = glob($modulesPath . '/*/Module.php');
    
    foreach ($modules as $moduleFile) {
        $moduleClass = $this->getModuleClassFromFile($moduleFile);
        if (class_exists($moduleClass)) {
            $this->app->register($moduleClass);
        }
    }
}
```

### 3. Middleware No Aplicado

**Problema**: El middleware de verificación no se ejecuta.

**Solución**:
```php
// Verificar registro en bootstrap/app.php
$middleware->alias([
    'module' => \App\Http\Middleware\CheckModuleAccess::class,
]);

// Verificar aplicación en rutas
Route::middleware(['module:invoicing'])->group(function () {
    // rutas protegidas
});
```

### 4. Vistas Vue No Cargan

**Problema**: Los componentes Vue del módulo no se encuentran.

**Solución**:
```bash
# Configurar Vite para resolver módulos
# vite.config.js
export default defineConfig({
    resolve: {
        alias: {
            '@': '/resources/js',
            '@modules': '/app/Modules'
        }
    }
});

# Actualizar imports en componentes
// Antes
import InvoiceForm from '@/Pages/Billing/InvoiceForm.vue';

// Después  
import InvoiceForm from '@modules/Invoicing/resources/js/Components/InvoiceForm.vue';
```

## 📈 Métricas de Éxito

### Indicadores de Migración Exitosa

- ✅ **Funcionalidad preservada**: 100% de features existentes funcionando
- ✅ **Performance mantenido**: Tiempo de respuesta similar o mejor
- ✅ **Tests pasando**: Todos los tests existentes + nuevos tests modulares
- ✅ **Usuarios satisfechos**: Sin reportes de bugs críticos
- ✅ **Escalabilidad**: Facilidad para agregar nuevos módulos

### Métricas a Monitorear

- **Tiempo de carga**: Por módulo vs monolito
- **Uso de memoria**: Carga selectiva vs carga completa
- **Errores 403**: Accesos denegados por módulo
- **Activación de módulos**: Cuántos tenants activan cada módulo
- **Satisfacción desarrollador**: Facilidad de mantenimiento

---

**La migración al sistema modular es un proceso gradual que debe hacerse con cuidado, preservando la funcionalidad existente mientras se prepara para el futuro escalable de CrecePyme.**

---

*Guía de migración actualizada: 27/05/2025*