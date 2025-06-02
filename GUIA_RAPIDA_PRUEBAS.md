# 🚀 GUÍA RÁPIDA DE PRUEBAS - MIDD

## 📌 INICIO RÁPIDO (5 MINUTOS)

### 1️⃣ INSTALACIÓN EXPRESS
```bash
# Clonar y entrar al proyecto
cd midd

# Instalar todo
composer install
npm install

# Configurar
cp .env.example .env
php artisan key:generate

# Base de datos
# Crear BD: crecepyme
php artisan migrate:fresh --seed

# Storage
php artisan storage:link

# Iniciar
npm run dev
php artisan serve
```

### 2️⃣ ACCESOS DIRECTOS

#### 🔴 SUPER ADMIN
```
URL: http://localhost:8000/super-admin/login
Email: superadmin@crecepyme.cl
Pass: SuperAdmin123!
```

#### 🔵 ADMIN TENANT
```
URL: http://localhost:8000/login
Email: admin@demo.cl
Pass: password
```

---

## 🎯 SISTEMA DE DEMO - FLUJO COMPLETO ✅

### 📋 **RESUMEN DEL SISTEMA PROBADO**

El sistema de demo permite a prospectos solicitar y acceder a demostraciones personalizadas con datos específicos según su tipo de negocio.

### 🔄 **FLUJO PASO A PASO (PROBADO)**

#### 1. **Solicitud de Demo** 
```bash
# Estado inicial: pending
# El prospecto llena formulario en landing page
```

#### 2. **Gestión Admin - Contacto**
```bash
# Cambiar a: contacted
php artisan tinker
$demoRequest = App\Models\DemoRequest::find(ID);
$demoRequest->update(['status' => 'contacted', 'contacted_at' => now()]);
```

#### 3. **Generación de Credenciales** ✅
```bash
# Crear sesión personalizada
$demoService = new App\Services\DemoService();
$demoSession = $demoService->createDemoSession($demoRequest->id);

# ✅ Resultado exitoso:
# Session ID: 56f19d56-be05-4568-9323-2602d47dc4b5
# Demo URL: https://demo.crecepyme.local/demo/{session-id}
# Usuario temporal creado: demo+{session-id}@crecepyme.cl
# Datos específicos cargados por tipo de negocio
```

#### 4. **Datos Específicos por Negocio** ✅

**RETAIL (Probado)**
```php
Categorías: Calzado, Ropa, Accesorios
Productos:
- Zapatillas Deportivas ($45,000) - Stock: 50
- Camiseta Básica ($12,000) - Stock: 100  
- Pantalón Jeans ($35,000) - Stock: 30
- Mochila Escolar ($25,000) - Stock: 20
```

**RESTAURANTES**
```php
Categorías: Platos Principales, Pizzas, Bebidas
Productos:
- Hamburguesa Clásica ($8,500)
- Pizza Margherita ($12,000) 
- Bebida Gaseosa ($2,500)
```

**SERVICIOS**
```php
Categorías: Consultoría, Auditoría, Formación, Soporte
Servicios:
- Consultoría Estratégica ($180,000)
- Auditoría Financiera ($250,000)
- Capacitación Empresarial ($120,000)
- Soporte Técnico Mensual ($85,000)
```

#### 5. **Acceso al Demo**
```bash
# Estado: demo_scheduled
# Duración: 30 minutos
# Posibilidad de extensión
# URL única por sesión
```

#### 6. **Finalización** ✅
```bash
# Marcar completado
$demoRequest->update([
    'status' => 'demo_completed',
    'demo_completed_at' => now()
]);
```

#### 7. **Conversión** ✅
```bash
# Conversión exitosa
$demoRequest->update([
    'status' => 'converted', 
    'converted_at' => now(),
    'subscription_plan' => 'premium'
]);

# Limpieza automática
$demoService->endDemoSession($sessionId);
```

### 📊 **ESTADÍSTICAS PROBADAS**

```bash
# Verificar sistema completo
Total Requests: 7 (6 seeded + 1 test)
- Pending: 2
- Contacted: 1  
- Demo Scheduled: 1
- Demo Completed: 1
- Converted: 2
- Tasa Conversión: 28.5%
```

### 🏗️ **ARQUITECTURA FUNCIONAL**

**Componentes Probados:**
- ✅ `DemoService` - Gestión completa del ciclo
- ✅ `DemoManagementController` - Panel admin
- ✅ `DetectEnvironment` Middleware - Auto-detección entorno
- ✅ Business-specific data seeding - Datos por tipo negocio
- ✅ Tenant isolation - Aislamiento de datos
- ✅ User lifecycle management - Gestión usuarios temporales

### 🔧 **CONFIGURACIÓN ENTORNOS**

**Demo (.env.demo)**
```env
APP_NAME="CrecePyme Demo"
APP_ENV=demo
DEMO_ENABLED=true
DEMO_SESSION_DURATION=30
```

**Producción (.env.production)**  
```env
APP_ENV=production
WAF_ENABLED=true
2FA_ENABLED=true
DEMO_ENABLED=false
```

### 🎯 **COMANDOS ÚTILES PARA TESTING**

```bash
# Ver solicitudes demo
App\Models\DemoRequest::orderBy('created_at', 'desc')->get(['id', 'company_name', 'status']);

# Estadísticas
$stats = [
    'total' => App\Models\DemoRequest::count(),
    'pending' => App\Models\DemoRequest::where('status', 'pending')->count(),
    'converted' => App\Models\DemoRequest::where('status', 'converted')->count(),
];

# Limpiar demos expirados
(new App\Services\DemoService())->cleanupExpiredDemos();
```

### 🚀 **URLs DEL SISTEMA**

**Producción:**
- App Principal: `https://app.crecepyme.cl`
- Landing: `https://crecepyme.cl`
- Admin: `https://app.crecepyme.cl/admin`

**Demo:**
- Demo App: `https://demo.crecepyme.cl`
- Demo Session: `https://demo.crecepyme.cl/demo/{session-id}`
- Request Demo: `https://crecepyme.cl/demo/request`

### ⚡ **FUNCIONES HELPER**

```php
isDemo(): bool                    // Detectar modo demo
isDemoRequest(): bool            // Verificar request demo  
demoWatermark(): string          // Marca de agua demo
```

### 🔒 **SEGURIDAD IMPLEMENTADA**

- ✅ Sesiones temporales (30 min)
- ✅ Datos aislados por tenant
- ✅ Limpieza automática
- ✅ Rate limiting por IP
- ✅ Validación RUT chileno

**🎉 SISTEMA DEMO COMPLETAMENTE FUNCIONAL Y PROBADO ✅**

---

## ⚡ PRUEBAS RÁPIDAS POR ROL

### 👑 COMO SUPER ADMIN

1. **Login** → `http://localhost:8000/super-admin/login`
2. **Ver Dashboard** → Métricas globales
3. **Gestionar Tenants** → Ver lista, editar "Empresa Demo SPA"
4. **Crear Tenant** → Click "Nuevo Tenant"
   ```
   Nombre: Test Rápido
   RUT: 76.123.456-7
   Email: admin@test.cl
   Plan: Profesional
   ```
5. **Ver Planes** → Básico, Profesional, Empresarial
6. **Cerrar Sesión**

### 🏢 COMO ADMIN TENANT

1. **Login** → `http://localhost:8000/login` (admin@demo.cl)
2. **Dashboard** → Ver widgets según rol
3. **Crear Cliente:**
   ```
   Click → CRM → Clientes → Nuevo
   RUT: 12.345.678-9
   Nombre: Juan Pérez
   ```
4. **Crear Producto:**
   ```
   Click → Inventario → Productos → Nuevo
   Nombre: Producto Test
   Precio: $10.000
   Stock: 100
   ```
5. **Crear Factura:**
   ```
   Click → Facturación → Nueva Factura
   Cliente: Juan Pérez
   Producto: Producto Test
   Emitir → Descargar PDF
   ```

### 💼 PRUEBAS POR MÓDULO (CHECKLIST)

#### ✅ CORE
- [ ] Login/Logout
- [ ] Ver Dashboard
- [ ] Crear Usuario
- [ ] Asignar Rol
- [ ] Cambiar Contraseña

#### ✅ CRM
- [ ] Crear Cliente
- [ ] Editar Cliente
- [ ] Buscar por RUT
- [ ] Exportar Lista

#### ✅ INVENTARIO
- [ ] Crear Producto
- [ ] Ver Stock
- [ ] Movimiento Manual
- [ ] Alerta Stock Bajo

#### ✅ FACTURACIÓN
- [ ] Crear Factura
- [ ] Emitir al SII
- [ ] Descargar PDF
- [ ] Enviar por Email
- [ ] Registrar Pago

#### ✅ CONTABILIDAD
- [ ] Ver Libro Ventas
- [ ] Ver Libro Compras
- [ ] Generar F29
- [ ] Conciliación Bancaria

#### ✅ RRHH
- [ ] Crear Empleado
- [ ] Generar Liquidación
- [ ] Ver Cálculos AFP/Salud
- [ ] Descargar PDF

#### ✅ POS
- [ ] Abrir Caja
- [ ] Venta Rápida
- [ ] Cerrar Caja
- [ ] Reporte Z

#### ✅ E-COMMERCE
- [ ] Ver Configuración
- [ ] Gestionar Pedidos
- [ ] Actualizar Stock

---

## 🎯 FLUJO DE PRUEBA COMPLETO (15 MIN)

### 📋 ESCENARIO: Venta Completa

1. **PREPARACIÓN**
   ```
   Login como: admin@demo.cl
   ```

2. **CREAR CLIENTE**
   ```
   CRM → Clientes → Nuevo
   RUT: 76.543.210-1
   Empresa: Cliente Prueba Ltda
   Email: contacto@prueba.cl
   ```

3. **CREAR PRODUCTO**
   ```
   Inventario → Productos → Nuevo
   Nombre: Notebook HP
   SKU: NB-001
   Precio: $599.990
   Stock: 10
   ```

4. **GENERAR COTIZACIÓN**
   ```
   Ventas → Nueva Cotización
   Cliente: Cliente Prueba Ltda
   Agregar: Notebook HP x 2
   Total: $1.199.980
   Enviar por Email
   ```

5. **CONVERTIR A FACTURA**
   ```
   Aprobar Cotización
   Generar Factura
   Emitir (SII simulado)
   ```

6. **REGISTRAR PAGO**
   ```
   Facturación → Pagos → Nuevo
   Factura: Seleccionar
   Monto: $1.199.980
   Método: Transferencia
   ```

7. **VERIFICAR**
   - [ ] Dashboard actualizado
   - [ ] Stock reducido (8 unidades)
   - [ ] Cliente con deuda $0
   - [ ] Libro de ventas actualizado

---

## 🔍 VERIFICACIONES RÁPIDAS

### ✅ ESTÁ FUNCIONANDO SI:
- Dashboard muestra datos
- Puedes crear clientes
- Puedes emitir facturas
- Los PDFs se generan
- Los totales calculan bien

### ❌ PROBLEMAS COMUNES:

**"No se ve nada"**
```bash
npm run dev
```

**"Error 500"**
```bash
php artisan config:clear
php artisan cache:clear
```

**"No puedo loguearme"**
```bash
php artisan migrate:fresh --seed
```

**"Mix manifest not found"**
```bash
npm install && npm run dev
```

---

## 📱 PROBAR EN MÓVIL

1. Obtener IP local:
   ```bash
   # Mac/Linux
   ifconfig | grep inet
   # Windows
   ipconfig
   ```

2. En .env cambiar:
   ```
   APP_URL=http://TU_IP:8000
   ```

3. Acceder desde móvil:
   ```
   http://TU_IP:8000
   ```

---

## 💡 TIPS PARA DEMO

### 🎪 PARA MOSTRAR A CLIENTES:

1. **Empezar con Dashboard** - Visual e impactante
2. **Crear factura** - Proceso core del negocio
3. **Mostrar PDF** - Resultado tangible
4. **Libro de ventas** - Valor para contador
5. **Reportes gráficos** - Toma de decisiones

### 🚫 EVITAR EN DEMOS:
- Configuraciones técnicas
- Procesos largos
- Módulos sin datos
- Funciones en desarrollo

---

## 📞 DATOS DE PRUEBA ÚTILES

### RUTS VÁLIDOS CHILE:
```
Empresas:
76.123.456-7
76.987.654-3
76.111.222-3

Personas:
12.345.678-9
11.111.111-1
22.222.222-2
```

### PRODUCTOS DEMO:
```
Laptop Dell - $899.990
Mouse Logitech - $29.990
Teclado Mecánico - $89.990
Monitor 24" - $199.990
Silla Ergonómica - $299.990
```

### FORMAS DE PAGO:
```
Efectivo
Transferencia
Tarjeta Crédito
Tarjeta Débito
Cheque 30 días
```

---

## 🎉 ¡LISTO!

Con esta guía puedes probar las funcionalidades principales en menos de 15 minutos.

**Recuerda:**
- Usar Chrome/Firefox actualizado
- Permitir popups para PDFs
- Tener paciencia en primera carga

**¿Problemas?** Revisa `storage/logs/laravel.log`

---
*Guía Rápida v1.0 - CrecePyme*