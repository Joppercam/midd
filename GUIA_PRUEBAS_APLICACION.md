# 📋 GUÍA DE PRUEBAS - CRECEPYME

## 🚀 Guía Completa para Probar la Aplicación

### 📌 ÍNDICE
1. [Requisitos Previos](#requisitos-previos)
2. [Instalación y Configuración](#instalación-y-configuración)
3. [Credenciales de Acceso](#credenciales-de-acceso)
4. [Primer Acceso - Super Admin](#primer-acceso---super-admin)
5. [Crear y Configurar un Tenant](#crear-y-configurar-un-tenant)
6. [Acceso como Usuario Tenant](#acceso-como-usuario-tenant)
7. [Pruebas por Módulo](#pruebas-por-módulo)
8. [Casos de Prueba Específicos](#casos-de-prueba-específicos)
9. [Verificación de Funcionalidades](#verificación-de-funcionalidades)
10. [Solución de Problemas Comunes](#solución-de-problemas-comunes)

---

## 📋 REQUISITOS PREVIOS

### Software Necesario:
- **PHP 8.2+**
- **Composer**
- **Node.js 18+** y npm
- **MySQL 8.0+** o **PostgreSQL 14+**
- **Redis** (opcional, para caché y colas)

### Verificar Versiones:
```bash
php -v          # Debe mostrar PHP 8.2 o superior
composer -V     # Debe mostrar Composer 2.x
node -v         # Debe mostrar v18.x o superior
npm -v          # Debe mostrar 8.x o superior
mysql --version # o psql --version
```

---

## 🛠️ INSTALACIÓN Y CONFIGURACIÓN

### 1. Clonar el Repositorio
```bash
git clone [URL_DEL_REPOSITORIO]
cd crecepyme
```

### 2. Instalar Dependencias PHP
```bash
composer install
```

### 3. Instalar Dependencias JavaScript
```bash
npm install
```

### 4. Configurar Variables de Entorno
```bash
cp .env.example .env
```

### 5. Editar el archivo `.env`:
```env
APP_NAME=CrecePyme
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crecepyme
DB_USERNAME=root
DB_PASSWORD=

# Configuración de Email (para pruebas usar Mailtrap)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=tu_usuario_mailtrap
MAIL_PASSWORD=tu_password_mailtrap
MAIL_ENCRYPTION=tls

# Redis (opcional)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Pusher (para notificaciones en tiempo real)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=us2

# SII Chile (Ambiente de Certificación)
SII_AMBIENTE=certificacion
SII_CERT_PATH=storage/app/sii/certificados/
```

### 6. Generar Key de Aplicación
```bash
php artisan key:generate
```

### 7. Crear Base de Datos
```sql
CREATE DATABASE crecepyme CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 8. Ejecutar Migraciones y Seeders
```bash
php artisan migrate:fresh --seed
```

### 9. Crear Link Simbólico para Storage
```bash
php artisan storage:link
```

### 10. Compilar Assets
```bash
npm run dev
# o para producción:
npm run build
```

### 11. Iniciar el Servidor
```bash
php artisan serve
```

Abrir en el navegador: `http://localhost:8000`

---

## 🔐 CREDENCIALES DE ACCESO

### 🔴 SUPER ADMINISTRADOR
```
URL: http://localhost:8000/super-admin/login
Email: superadmin@crecepyme.cl
Password: SuperAdmin123!
```

### 🔵 USUARIOS POR TENANT

#### Tenant: "Empresa Demo SPA"
```
URL: http://localhost:8000/login

ADMINISTRADOR:
Email: admin@demo.cl
Password: password

CONTADOR:
Email: contador@demo.cl
Password: password

VENDEDOR:
Email: vendedor@demo.cl
Password: password

GERENTE:
Email: gerente@demo.cl
Password: password
```

#### Tenant: "Comercial Test Ltda"
```
URL: http://localhost:8000/login

ADMINISTRADOR:
Email: admin@test.cl
Password: password
```

---

## 👨‍💼 PRIMER ACCESO - SUPER ADMIN

### 1. Acceder al Panel Super Admin
- Ir a: `http://localhost:8000/super-admin/login`
- Ingresar credenciales de Super Admin
- Serás redirigido al Dashboard Super Admin

### 2. Explorar el Dashboard Super Admin
Verificar que puedas ver:
- **Métricas Globales:**
  - Total de Tenants
  - Usuarios Activos
  - Ingresos Mensuales
  - Uso de Almacenamiento
  
- **Gráficos:**
  - Crecimiento de Tenants
  - Ingresos por Plan
  - Uso del Sistema

### 3. Gestión de Tenants
Navegar a: **Tenants** → **Lista de Tenants**

Verificar:
- ✅ Ver lista de tenants existentes
- ✅ Buscar y filtrar tenants
- ✅ Ver detalles de cada tenant
- ✅ Editar configuración de tenant
- ✅ Suspender/Activar tenants

### 4. Crear Nuevo Tenant
Click en **"Nuevo Tenant"** y completar:
```
Nombre: Mi Empresa Prueba
RUT: 76.123.456-7
Email Admin: admin@miempresa.cl
Plan: Profesional
Módulos: Seleccionar los deseados
```

### 5. Gestión de Planes
Navegar a: **Planes** → **Administrar**

Verificar planes disponibles:
- **Básico:** $29.990/mes
- **Profesional:** $59.990/mes
- **Empresarial:** $99.990/mes

### 6. Monitoreo del Sistema
Navegar a: **Sistema** → **Monitoreo**

Verificar:
- Estado de servicios
- Logs de actividad
- Métricas de rendimiento

---

## 🏢 CREAR Y CONFIGURAR UN TENANT

### 1. Crear Tenant de Prueba
Como Super Admin, crear un nuevo tenant:

```
Información Básica:
- Nombre: Prueba Manual SPA
- RUT: 76.555.444-3
- Razón Social: Empresa de Prueba Manual SPA
- Giro: Servicios Informáticos
- Dirección: Av. Principal 123
- Comuna: Providencia
- Ciudad: Santiago
- Teléfono: +56912345678

Información SII:
- Resolución SII: 80
- Fecha Resolución: 2014-08-22
- Ambiente: Certificación

Usuario Administrador:
- Nombre: Administrador Prueba
- Email: admin@prueba.cl
- Contraseña: TestAdmin123!

Plan y Módulos:
- Plan: Profesional
- Módulos: Todos
```

### 2. Configurar Certificado Digital (Opcional)
Para pruebas con SII, subir certificado de pruebas:
- Navegar a: **Configuración** → **SII**
- Subir archivo .p12
- Ingresar contraseña del certificado

---

## 👤 ACCESO COMO USUARIO TENANT

### 1. Cerrar Sesión de Super Admin
Click en el menú de usuario → **Cerrar Sesión**

### 2. Acceder como Admin del Tenant
- Ir a: `http://localhost:8000/login`
- Email: `admin@demo.cl`
- Password: `password`

### 3. Primer Login - Configuración Inicial
Al primer acceso deberías ver:
- Dashboard personalizado según el rol
- Notificación de bienvenida
- Widgets según permisos

### 4. Completar Configuración del Tenant
Navegar a: **Configuración** → **Empresa**

Completar:
- Logo de la empresa (opcional)
- Información adicional
- Configuración de folios
- Preferencias de facturación

---

## 📦 PRUEBAS POR MÓDULO

### 🧮 MÓDULO CORE (Núcleo)

#### 1. Gestión de Usuarios
**Ruta:** Configuración → Usuarios

**Pruebas:**
- ✅ Crear nuevo usuario
- ✅ Asignar roles (Admin, Contador, Vendedor)
- ✅ Editar permisos específicos
- ✅ Activar/Desactivar usuarios
- ✅ Restablecer contraseñas

**Datos de Prueba:**
```
Nombre: Juan Pérez
Email: jperez@prueba.cl
Rol: Vendedor
Permisos: Solo lectura en reportes
```

#### 2. Gestión de Clientes
**Ruta:** CRM → Clientes

**Pruebas:**
- ✅ Crear cliente persona natural
- ✅ Crear cliente empresa
- ✅ Validación de RUT chileno
- ✅ Importar clientes desde Excel
- ✅ Exportar listado

**Datos de Prueba:**
```
Empresa:
RUT: 76.111.222-3
Razón Social: Cliente Test Ltda
Giro: Comercio
Email: contacto@clientetest.cl

Persona:
RUT: 12.345.678-9
Nombre: María González
Email: maria@email.cl
```

#### 3. Gestión de Productos
**Ruta:** Inventario → Productos

**Pruebas:**
- ✅ Crear producto simple
- ✅ Crear producto con variantes
- ✅ Establecer precios y costos
- ✅ Gestionar stock
- ✅ Cargar imágenes

**Datos de Prueba:**
```
Nombre: Laptop HP ProBook
SKU: LAP-HP-001
Precio: $599.990
Costo: $450.000
Stock: 10
Categoría: Computación
```

### 💰 MÓDULO FACTURACIÓN

#### 1. Crear Factura Electrónica
**Ruta:** Facturación → Nueva Factura

**Pruebas paso a paso:**

1. **Seleccionar Cliente:**
   - Buscar por RUT o nombre
   - Verificar que se carguen datos automáticamente

2. **Agregar Productos:**
   - Buscar productos por código o nombre
   - Modificar cantidad y precio
   - Verificar cálculo automático de totales

3. **Datos de Factura:**
   ```
   Tipo: Factura Electrónica (33)
   Fecha: Hoy
   Condición Pago: 30 días
   Observaciones: Factura de prueba
   ```

4. **Revisar y Emitir:**
   - Verificar vista previa
   - Emitir factura
   - Verificar generación de XML
   - Descargar PDF

#### 2. Nota de Crédito
**Ruta:** Facturación → Nueva Nota de Crédito

**Pruebas:**
- ✅ Referenciar factura existente
- ✅ Anulación completa
- ✅ Devolución parcial
- ✅ Verificar actualización de saldos

#### 3. Gestión de Pagos
**Ruta:** Facturación → Pagos

**Pruebas:**
- ✅ Registrar pago total
- ✅ Registrar pago parcial
- ✅ Múltiples formas de pago
- ✅ Aplicar a múltiples facturas

**Datos de Prueba:**
```
Factura: F-001
Monto: $250.000
Forma de Pago: Transferencia
Fecha: Hoy
Referencia: TRF-12345
```

### 📊 MÓDULO CONTABILIDAD

#### 1. Libro de Ventas
**Ruta:** Contabilidad → Libro de Ventas

**Pruebas:**
- ✅ Generar libro mensual
- ✅ Exportar a Excel
- ✅ Exportar formato SII
- ✅ Verificar totales

#### 2. Libro de Compras
**Ruta:** Contabilidad → Libro de Compras

**Pruebas:**
- ✅ Registrar factura de compra
- ✅ Cargar XML de proveedor
- ✅ Generar libro mensual
- ✅ Cuadrar IVA

#### 3. Conciliación Bancaria
**Ruta:** Contabilidad → Conciliación

**Pruebas:**
1. **Cargar Cartola:**
   - Subir archivo Excel del banco
   - Verificar parsing correcto

2. **Conciliar Movimientos:**
   - Match automático
   - Match manual
   - Crear transacciones faltantes

3. **Verificar Saldos:**
   - Saldo según libros
   - Saldo según banco
   - Diferencias identificadas

### 📦 MÓDULO INVENTARIO

#### 1. Movimientos de Inventario
**Ruta:** Inventario → Movimientos

**Pruebas:**
- ✅ Entrada por compra
- ✅ Salida por venta
- ✅ Ajuste manual
- ✅ Transferencia entre bodegas

#### 2. Control de Stock
**Ruta:** Inventario → Stock

**Pruebas:**
- ✅ Ver stock actual
- ✅ Stock mínimo/máximo
- ✅ Alertas de reposición
- ✅ Valorización de inventario

### 👥 MÓDULO RRHH

#### 1. Gestión de Empleados
**Ruta:** RRHH → Empleados

**Datos de Prueba:**
```
Nombre: Carlos Muñoz
RUT: 15.678.901-2
Cargo: Analista Contable
Departamento: Finanzas
Fecha Ingreso: 01/01/2024
Sueldo Base: $850.000
```

#### 2. Liquidaciones de Sueldo
**Ruta:** RRHH → Liquidaciones

**Pruebas:**
- ✅ Generar liquidación mensual
- ✅ Cálculo automático AFP (10%)
- ✅ Cálculo automático Salud (7%)
- ✅ Cálculo impuesto único
- ✅ Agregar bonos/descuentos

**Verificar Cálculos:**
```
Sueldo Base: $850.000
AFP (10%): -$85.000
Salud (7%): -$59.500
Base Imponible: $705.500
Impuesto: Según tabla
Líquido a Pagar: Verificar total
```

### 🛒 MÓDULO E-COMMERCE

#### 1. Configuración Tienda
**Ruta:** E-commerce → Configuración

**Pruebas:**
- ✅ Activar tienda online
- ✅ Configurar métodos de pago
- ✅ Configurar envíos
- ✅ Personalizar diseño

#### 2. Gestión de Pedidos
**Ruta:** E-commerce → Pedidos

**Pruebas:**
- ✅ Ver pedidos pendientes
- ✅ Procesar pedido
- ✅ Generar factura desde pedido
- ✅ Actualizar estado de envío

### 💳 MÓDULO POS

#### 1. Apertura de Caja
**Ruta:** POS → Abrir Caja

**Datos:**
```
Terminal: CAJA-01
Monto Inicial: $50.000
Cajero: Usuario actual
```

#### 2. Venta Rápida
**Ruta:** POS → Nueva Venta

**Pruebas:**
- ✅ Buscar productos por código
- ✅ Aplicar descuentos
- ✅ Múltiples formas de pago
- ✅ Imprimir boleta

#### 3. Cierre de Caja
**Ruta:** POS → Cerrar Caja

**Verificar:**
- Total ventas efectivo
- Total ventas tarjeta
- Diferencias de caja
- Generar reporte Z

---

## 🧪 CASOS DE PRUEBA ESPECÍFICOS

### 📄 CASO 1: Ciclo Completo de Venta

1. **Crear Cliente Nuevo**
   ```
   RUT: 76.999.888-7
   Razón Social: Empresa Ciclo Completo Ltda
   ```

2. **Crear Cotización**
   - 3 productos diferentes
   - Aplicar 10% descuento
   - Enviar por email

3. **Convertir a Factura**
   - Aprobar cotización
   - Generar factura
   - Emitir al SII

4. **Registrar Pago**
   - 50% transferencia
   - 50% cheque a 30 días

5. **Verificar:**
   - Estado documentos
   - Libro de ventas
   - Estado de cuenta cliente

### 📊 CASO 2: Proceso Contable Mensual

1. **Cerrar Mes Anterior**
   - Generar libros
   - Revisar pendientes

2. **Cargar Compras**
   - 10 facturas de compra
   - Diferentes proveedores
   - Con y sin retención

3. **Conciliar Banco**
   - Cargar cartola
   - Identificar pagos
   - Cuadrar saldos

4. **Generar Reportes**
   - F29 preliminar
   - Balance tributario
   - Estado de resultados

### 👥 CASO 3: Proceso de Nómina

1. **Preparar Período**
   - Mes: Actual
   - Verificar empleados activos

2. **Cargar Novedades**
   - Horas extras: 10 hrs
   - Bono: $50.000
   - Licencia: 2 días

3. **Generar Liquidaciones**
   - Revisar cálculos
   - Aprobar
   - Generar PDFs

4. **Contabilizar**
   - Generar asiento contable
   - Verificar cuentas

---

## ✅ VERIFICACIÓN DE FUNCIONALIDADES

### 🔔 Notificaciones en Tiempo Real

1. **Configurar Navegador**
   - Permitir notificaciones del sitio

2. **Pruebas:**
   - Crear factura en una sesión
   - Verificar notificación en otra sesión
   - Click en notificación lleva al documento

### 📧 Sistema de Emails

1. **Configurar Mailtrap** (para pruebas)
   - Crear cuenta en mailtrap.io
   - Copiar credenciales a .env

2. **Pruebas:**
   - Enviar factura por email
   - Recordatorio de pago
   - Notificaciones de sistema

### 🔒 Seguridad y Permisos

1. **Pruebas de Roles:**
   - Login como vendedor
   - Intentar acceder a contabilidad
   - Verificar mensaje de error

2. **2FA (Si está activo):**
   - Activar en perfil
   - Escanear QR
   - Verificar login con código

### 📱 Responsividad

1. **Probar en diferentes dispositivos:**
   - Desktop (1920x1080)
   - Tablet (768x1024)
   - Móvil (375x667)

2. **Verificar:**
   - Menú móvil funcional
   - Tablas responsivas
   - Formularios adaptables

---

## 🔧 SOLUCIÓN DE PROBLEMAS COMUNES

### ❌ Error: "SQLSTATE[HY000] [2002] Connection refused"
**Solución:**
```bash
# Verificar que MySQL esté corriendo
sudo service mysql start
# o
mysql.server start
```

### ❌ Error: "The Mix manifest does not exist"
**Solución:**
```bash
npm run dev
# o
npm run build
```

### ❌ Error: "Failed to clear cache"
**Solución:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

### ❌ Error: "Class not found" después de actualizar
**Solución:**
```bash
composer dump-autoload
php artisan optimize:clear
```

### ❌ Las notificaciones no funcionan
**Verificar:**
1. Credenciales de Pusher en .env
2. `npm run dev` está corriendo
3. Permisos del navegador

### ❌ No se ven los cambios en el código
**Solución:**
```bash
php artisan optimize:clear
npm run build
# Limpiar caché del navegador (Ctrl+Shift+R)
```

---

## 📝 NOTAS IMPORTANTES

### 🔐 Seguridad
- **NUNCA** usar estas credenciales en producción
- Cambiar TODAS las contraseñas antes de ir a producción
- Configurar certificados SSL reales
- Habilitar 2FA para todos los usuarios admin

### 🚀 Performance
- En producción usar `npm run build`
- Configurar Redis para caché
- Habilitar OPcache en PHP
- Configurar queue workers

### 📊 Datos de Prueba
- Los seeders crean datos ficticios
- RUTs de prueba son válidos pero ficticios
- No usar datos reales en ambiente de desarrollo

### 🆘 Soporte
- Documentación técnica en `/docs`
- Logs en `storage/logs/laravel.log`
- Reportar bugs en el sistema de tickets

---

## 🎯 CHECKLIST FINAL

Antes de considerar la aplicación lista para producción:

- [ ] Todas las pruebas pasan exitosamente
- [ ] Sin errores en los logs
- [ ] Performance aceptable (<3s carga de página)
- [ ] Emails funcionando correctamente
- [ ] Backups automáticos configurados
- [ ] SSL certificado instalado
- [ ] Variables de entorno de producción
- [ ] Monitoring configurado
- [ ] Documentación actualizada
- [ ] Plan de contingencia definido

---

**¡Felicitaciones! 🎉** 

Si has completado todas estas pruebas exitosamente, la aplicación CrecePyme está lista para ser utilizada. Recuerda siempre hacer respaldos antes de cualquier actualización importante.

Para soporte adicional o consultas, contactar al equipo de desarrollo.

---
*Última actualización: 30/05/2025*
*Versión del documento: 1.0*