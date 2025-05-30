# 🚀 CrecePyme - MVP v1.0

## 📋 Descripción
CrecePyme es un sistema ERP completo diseñado específicamente para PyMEs chilenas. Esta versión MVP incluye todos los módulos esenciales para la gestión empresarial con integración completa al SII.

## 🏗️ Stack Tecnológico
- **Backend:** Laravel 12 + PHP 8.2
- **Frontend:** Vue.js 3 + Inertia.js
- **Base de Datos:** MySQL 8.0 / PostgreSQL 14
- **Cache/Queue:** Redis
- **Estilos:** TailwindCSS 3.4

## 📦 Módulos Implementados

### ✅ Completados
1. **Core** - Sistema base con multi-tenancy
2. **Facturación** - Emisión de DTEs al SII
3. **Inventario** - Control de stock completo
4. **Contabilidad** - Libros contables y reportes
5. **CRM** - Gestión de clientes
6. **RRHH** - Liquidaciones y empleados
7. **Banca** - Conciliación bancaria
8. **POS** - Punto de venta
9. **E-commerce** - Tienda online básica
10. **Panel Super Admin** - Gestión de tenants

### 🔧 Características Principales
- 🏢 **Multi-tenancy** con aislamiento completo
- 🇨🇱 **Integración SII** para facturación electrónica
- 📊 **Dashboards** personalizados por rol
- 🔔 **Notificaciones** en tiempo real
- 📱 **Responsive** para todos los dispositivos
- 🔒 **Seguridad** con 2FA y permisos granulares
- 📈 **Reportes** automáticos programables
- 💾 **Backups** automáticos
- 🌐 **100% en Español**

## 🚀 Instalación Rápida

```bash
# 1. Clonar repositorio
git clone [URL_REPOSITORIO]
cd crecepyme

# 2. Instalar dependencias
composer install
npm install

# 3. Configurar entorno
cp .env.example .env
php artisan key:generate

# 4. Configurar base de datos en .env
# DB_DATABASE=crecepyme
# DB_USERNAME=root
# DB_PASSWORD=

# 5. Ejecutar migraciones
php artisan migrate:fresh --seed

# 6. Compilar assets
npm run build

# 7. Iniciar servidor
php artisan serve
```

## 🔐 Credenciales de Acceso

### Super Admin
```
URL: http://localhost:8000/super-admin/login
Email: superadmin@crecepyme.cl
Password: SuperAdmin123!
```

### Admin Tenant Demo
```
URL: http://localhost:8000/login
Email: admin@demo.cl
Password: password
```

## 📚 Documentación

- **[GUIA_PRUEBAS_APLICACION.md](./GUIA_PRUEBAS_APLICACION.md)** - Guía completa de pruebas
- **[GUIA_RAPIDA_PRUEBAS.md](./GUIA_RAPIDA_PRUEBAS.md)** - Inicio rápido en 5 minutos
- **[REQUISITOS_IMPLEMENTACION.md](./REQUISITOS_IMPLEMENTACION.md)** - Especificaciones técnicas
- **[BITACORA_DESARROLLO.md](./BITACORA_DESARROLLO.md)** - Registro de desarrollo

## 🎯 Estado del Proyecto

### ✅ MVP Completado
- Todos los módulos core funcionando
- Integración SII operativa
- Sistema de permisos implementado
- Multi-tenancy funcionando
- Dashboards por rol
- Notificaciones en tiempo real
- Sistema de reportes
- 100% traducido al español

### 🔄 Próximas Mejoras
- [ ] Integración con bancos
- [ ] App móvil
- [ ] Más integraciones de pago
- [ ] Reportes avanzados
- [ ] API pública
- [ ] Mejoras de UI/UX

## 🤝 Contribuir
Este es un proyecto en desarrollo activo. Para contribuir:
1. Fork el proyecto
2. Crear feature branch (`git checkout -b feature/NuevaCaracteristica`)
3. Commit cambios (`git commit -m 'Agregar nueva característica'`)
4. Push al branch (`git push origin feature/NuevaCaracteristica`)
5. Abrir Pull Request

## 📝 Licencia
Proyecto privado - Todos los derechos reservados

## 🆘 Soporte
Para soporte o consultas sobre el MVP, revisar la documentación o contactar al equipo de desarrollo.

---
**Versión:** 1.0.0-MVP  
**Fecha:** 30/05/2025  
**Estado:** ✅ Listo para pruebas