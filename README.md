# CrecePyme

Plataforma SaaS B2B integral de gestión empresarial para PyMEs chilenas.

## 🚀 Inicio Rápido

### Requisitos
- PHP 8.3+
- Composer
- Node.js 18+
- PostgreSQL 15+
- Docker (opcional)

### Instalación Local

1. **Clonar el repositorio**
```bash
git clone <repo-url>
cd crecepyme
```

2. **Instalar dependencias PHP**
```bash
composer install
```

3. **Instalar dependencias Node**
```bash
npm install
```

4. **Configurar ambiente**
```bash
cp .env.example .env
php artisan key:generate
```

5. **Configurar base de datos**
Editar `.env` con los datos de PostgreSQL:
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=crecepyme
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

6. **Ejecutar migraciones**
```bash
php artisan migrate
```

7. **Cargar datos de prueba** (opcional)
```bash
php artisan db:seed
```

Esto creará:
- 3 empresas de prueba con usuarios
- Categorías y productos para cada empresa
- Clientes de ejemplo
- Facturas y documentos de muestra

Usuarios de prueba:
- admin@techinnovadora.cl / password
- carlos@importadorapacifico.cl / password
- andrea@consultoresasociados.cl / password

8. **Compilar assets**
```bash
npm run dev
```

9. **Iniciar servidor**
```bash
php artisan serve
```

### Instalación con Docker

1. **Iniciar servicios**
```bash
docker-compose up -d
```

2. **Ejecutar migraciones**
```bash
docker-compose exec app php artisan migrate
```

3. **Compilar assets**
```bash
docker-compose exec app npm run dev
```

La aplicación estará disponible en http://localhost:8000

## 📊 Características Principales

- ✅ **Autenticación y Multitenancy**: Sistema completo de usuarios por empresa
- ✅ **Dashboard Inteligente**: Métricas en tiempo real con gráficos
- ✅ **Modelos de Datos**: Estructura completa para clientes, productos, facturas
- 🚧 **Facturación Electrónica SII**: Integración con servicios chilenos (próximamente)
- 🚧 **Gestión de Inventario**: Control de stock con alertas automáticas
- 🚧 **CRM**: Gestión de clientes y segmentación automática
- 🚧 **IA Integrada**: Predicción de demanda y análisis inteligente

## 🛠️ Stack Tecnológico

- **Backend**: Laravel 11, PostgreSQL, Redis
- **Frontend**: Vue.js 3, Inertia.js, Tailwind CSS
- **IA**: OpenAI GPT-4
- **Infraestructura**: Docker, AWS/DigitalOcean

## 📁 Estructura del Proyecto

```
crecepyme/
├── app/
│   ├── Models/          # Modelos Eloquent
│   ├── Http/
│   │   ├── Controllers/ # Controladores
│   │   └── Middleware/  # Middleware personalizado
│   └── Traits/          # Traits reutilizables
├── resources/
│   ├── js/
│   │   ├── Pages/      # Componentes de página Vue
│   │   ├── Components/ # Componentes reutilizables
│   │   └── Layouts/    # Layouts de la aplicación
│   └── css/            # Estilos
├── routes/             # Definición de rutas
├── database/           # Migraciones y seeders
└── docker/             # Configuración Docker
```

## 🧪 Testing

```bash
# Ejecutar tests
php artisan test

# Con coverage
php artisan test --coverage
```

## 📝 Licencia

Proyecto privado - CrecePyme © 2024