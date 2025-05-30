# 🚀 GUÍA RÁPIDA DE PRUEBAS - CRECEPYME

## 📌 INICIO RÁPIDO (5 MINUTOS)

### 1️⃣ INSTALACIÓN EXPRESS
```bash
# Clonar y entrar al proyecto
cd crecepyme

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