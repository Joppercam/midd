<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\DemoRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DemoService
{
    protected $demoTenantId = 'demo';
    
    public function createDemoSession($demoRequestId = null)
    {
        $sessionId = Str::uuid();
        $demoTenant = $this->getOrCreateDemoTenant();
        
        // Crear usuario temporal para la sesión de demo
        $demoUser = $this->createDemoUser($sessionId, $demoTenant);
        
        // Configurar datos específicos si viene de una solicitud
        if ($demoRequestId) {
            $demoRequest = DemoRequest::find($demoRequestId);
            if ($demoRequest) {
                $this->customizeDemoForRequest($demoTenant, $demoRequest);
            }
        }
        
        // Registrar inicio de sesión demo
        $this->trackDemoStart($sessionId, $demoRequestId);
        
        return [
            'session_id' => $sessionId,
            'tenant' => $demoTenant,
            'user' => $demoUser,
            'expires_at' => now()->addMinutes((int) config('demo.session.duration', 30)),
            'demo_url' => $this->buildDemoUrl($sessionId)
        ];
    }
    
    protected function getOrCreateDemoTenant()
    {
        // Buscar por dominio en lugar de ID fijo
        $tenant = Tenant::where('domain', 'demo.midd.local')->first();
        
        if (!$tenant) {
            $tenant = Tenant::create([
                'name' => config('demo.content.company_name', 'Demo Empresa SPA'),
                'domain' => 'demo.midd.local',
                'rut' => config('demo.content.company_rut', '12.345.678-9'),
                'business_type' => 'Demo',
                'is_demo' => true,
                'demo_expires_at' => now()->addDays((int) config('demo.data.cleanup_after_days', 7)),
            ]);
            
            $this->seedDemoData($tenant);
        }
        
        return $tenant;
    }
    
    protected function createDemoUser($sessionId, $tenant)
    {
        return User::create([
            'name' => 'Usuario Demo',
            'email' => "demo+{$sessionId}@midd.com",
            'password' => Hash::make(Str::random(16)),
            'tenant_id' => $tenant->id,
            'is_demo_user' => true,
            'demo_session_id' => $sessionId,
            'demo_expires_at' => now()->addMinutes((int) config('demo.session.duration', 30)),
        ]);
    }
    
    protected function seedDemoData($tenant)
    {
        // Seed con datos de ejemplo relevantes
        DB::transaction(function () use ($tenant) {
            // Crear categorías de ejemplo
            $categoryNames = ['Productos', 'Servicios', 'Materiales'];
            
            foreach ($categoryNames as $categoryName) {
                $category = new \App\Models\Category([
                    'name' => $categoryName,
                    'slug' => \Illuminate\Support\Str::slug($categoryName)
                ]);
                $category->tenant_id = $tenant->id;
                $category->save();
            }
            
            // Crear clientes de ejemplo
            $customers = [
                [
                    'name' => 'Empresa Cliente ABC SpA',
                    'rut' => '76.123.456-7',
                    'email' => 'contacto@clienteabc.cl',
                    'phone' => '+56 2 2234 5678',
                    'address' => 'Av. Providencia 123, Santiago',
                    'tenant_id' => $tenant->id
                ],
                [
                    'name' => 'Comercial XYZ Ltda',
                    'rut' => '96.789.012-3',
                    'email' => 'ventas@comercialxyz.cl', 
                    'phone' => '+56 2 2345 6789',
                    'address' => 'Las Condes 456, Santiago',
                    'tenant_id' => $tenant->id
                ]
            ];
            
            foreach ($customers as $customer) {
                $customerModel = new \App\Models\Customer($customer);
                $customerModel->tenant_id = $tenant->id;
                $customerModel->save();
            }
            
            // Crear productos de ejemplo
            $products = [
                [
                    'name' => 'Servicio de Consultoría',
                    'description' => 'Consultoría empresarial especializada',
                    'price' => 150000,
                    'sku' => 'CONS-001',
                    'is_service' => true
                ],
                [
                    'name' => 'Producto Estrella',
                    'description' => 'Nuestro producto más vendido',
                    'price' => 25000,
                    'stock_quantity' => 100,
                    'sku' => 'PROD-001'
                ]
            ];
            
            foreach ($products as $product) {
                $productModel = new \App\Models\Product($product);
                $productModel->tenant_id = $tenant->id;
                $productModel->save();
            }
        });
    }
    
    protected function customizeDemoForRequest($tenant, $demoRequest)
    {
        // Personalizar datos según el tipo de negocio del prospecto
        $businessType = $demoRequest->business_type;
        
        if (isset(config('demo.content.sample_data_sets')[$businessType])) {
            $this->loadSpecificDataSet($tenant, $businessType);
        }
    }
    
    protected function loadSpecificDataSet($tenant, $businessType)
    {
        // Cargar datos específicos según el tipo de negocio
        switch ($businessType) {
            case 'restaurant':
                $this->loadRestaurantData($tenant);
                break;
            case 'retail':
                $this->loadRetailData($tenant);
                break;
            case 'services':
                $this->loadServicesData($tenant);
                break;
            // ... más tipos de negocio
        }
    }
    
    protected function loadRestaurantData($tenant)
    {
        // Crear categorías específicas para restaurantes
        $categories = [];
        foreach (['Platos Principales', 'Pizzas', 'Bebidas'] as $categoryName) {
            $category = new \App\Models\Category([
                'name' => $categoryName,
                'slug' => \Illuminate\Support\Str::slug($categoryName)
            ]);
            $category->tenant_id = $tenant->id;
            $category->save();
            $categories[$categoryName] = $category;
        }
        
        // Productos específicos para restaurantes
        $products = [
            ['name' => 'Hamburguesa Clásica', 'price' => 8500, 'category' => 'Platos Principales'],
            ['name' => 'Pizza Margherita', 'price' => 12000, 'category' => 'Pizzas'],
            ['name' => 'Bebida Gaseosa', 'price' => 2500, 'category' => 'Bebidas'],
        ];
        
        foreach ($products as $product) {
            $productModel = new \App\Models\Product([
                'name' => $product['name'],
                'price' => $product['price'],
                'category_id' => $categories[$product['category']]->id,
                'sku' => 'REST-' . strtoupper(substr($product['name'], 0, 3)) . '-' . rand(100, 999)
            ]);
            $productModel->tenant_id = $tenant->id;
            $productModel->save();
        }
    }
    
    protected function loadRetailData($tenant)
    {
        // Crear categorías específicas para retail usando save() para evitar problemas con el trait
        $categories = [];
        foreach (['Calzado', 'Ropa', 'Accesorios'] as $categoryName) {
            $category = new \App\Models\Category([
                'name' => $categoryName,
                'slug' => \Illuminate\Support\Str::slug($categoryName)
            ]);
            $category->tenant_id = $tenant->id;
            $category->save();
            $categories[$categoryName] = $category;
        }
        
        // Productos específicos para retail
        $products = [
            ['name' => 'Zapatillas Deportivas', 'price' => 45000, 'stock_quantity' => 50, 'category' => 'Calzado'],
            ['name' => 'Camiseta Básica', 'price' => 12000, 'stock_quantity' => 100, 'category' => 'Ropa'],
            ['name' => 'Pantalón Jeans', 'price' => 35000, 'stock_quantity' => 30, 'category' => 'Ropa'],
            ['name' => 'Mochila Escolar', 'price' => 25000, 'stock_quantity' => 20, 'category' => 'Accesorios'],
        ];
        
        foreach ($products as $product) {
            $productModel = new \App\Models\Product([
                'name' => $product['name'],
                'price' => $product['price'],
                'stock_quantity' => $product['stock_quantity'],
                'category_id' => $categories[$product['category']]->id,
                'sku' => 'RET-' . strtoupper(substr($product['name'], 0, 3)) . '-' . rand(100, 999)
            ]);
            $productModel->tenant_id = $tenant->id;
            $productModel->save();
        }
    }
    
    protected function loadServicesData($tenant)
    {
        // Crear categorías específicas para servicios
        $categories = [];
        foreach (['Consultoría', 'Auditoría', 'Formación', 'Soporte'] as $categoryName) {
            $category = new \App\Models\Category([
                'name' => $categoryName,
                'slug' => \Illuminate\Support\Str::slug($categoryName)
            ]);
            $category->tenant_id = $tenant->id;
            $category->save();
            $categories[$categoryName] = $category;
        }
        
        // Servicios específicos para empresas de servicios
        $products = [
            ['name' => 'Consultoría Estratégica', 'price' => 180000, 'category' => 'Consultoría'],
            ['name' => 'Auditoría Financiera', 'price' => 250000, 'category' => 'Auditoría'],
            ['name' => 'Capacitación Empresarial', 'price' => 120000, 'category' => 'Formación'],
            ['name' => 'Soporte Técnico Mensual', 'price' => 85000, 'category' => 'Soporte'],
        ];
        
        foreach ($products as $product) {
            $productModel = new \App\Models\Product([
                'name' => $product['name'],
                'price' => $product['price'],
                'category_id' => $categories[$product['category']]->id,
                'is_service' => true,
                'sku' => 'SRV-' . strtoupper(substr($product['name'], 0, 3)) . '-' . rand(100, 999)
            ]);
            $productModel->tenant_id = $tenant->id;
            $productModel->save();
        }
    }
    
    public function extendDemoSession($sessionId)
    {
        $user = User::where('demo_session_id', $sessionId)->first();
        
        if (!$user || $user->demo_extensions >= config('demo.session.extend_limit')) {
            return false;
        }
        
        $user->update([
            'demo_expires_at' => now()->addMinutes((int) config('demo.session.duration', 30)),
            'demo_extensions' => ($user->demo_extensions ?? 0) + 1
        ]);
        
        return true;
    }
    
    public function endDemoSession($sessionId)
    {
        $user = User::where('demo_session_id', $sessionId)->first();
        
        if ($user) {
            $this->trackDemoEnd($sessionId);
            $user->delete();
        }
    }
    
    public function isDemoExpired($sessionId)
    {
        $user = User::where('demo_session_id', $sessionId)->first();
        
        return !$user || $user->demo_expires_at < now();
    }
    
    protected function buildDemoUrl($sessionId)
    {
        $subdomain = config('demo.subdomain');
        $domain = config('demo.domain');
        
        return "https://{$subdomain}.{$domain}/demo/{$sessionId}";
    }
    
    protected function trackDemoStart($sessionId, $demoRequestId = null)
    {
        // Implementar tracking de inicio de demo
        // Aquí puedes usar Google Analytics, Mixpanel, etc.
    }
    
    protected function trackDemoEnd($sessionId)
    {
        // Implementar tracking de fin de demo
    }
    
    public function cleanupExpiredDemos()
    {
        // Limpiar usuarios demo expirados
        User::where('is_demo_user', true)
            ->where('demo_expires_at', '<', now())
            ->delete();
            
        // Limpiar datos del tenant demo si es necesario
        $this->resetDemoTenantData();
    }
    
    protected function resetDemoTenantData()
    {
        $tenant = Tenant::where('domain', 'demo.midd.local')->first();
        if (!$tenant) return;
        
        DB::transaction(function () use ($tenant) {
            // Resetear datos del demo manteniendo la estructura
            \App\Models\TaxDocument::where('tenant_id', $tenant->id)->delete();
            \App\Models\Product::where('tenant_id', $tenant->id)->delete();
            \App\Models\Customer::where('tenant_id', $tenant->id)->delete();
            \App\Models\Category::where('tenant_id', $tenant->id)->delete();
            
            // Volver a seed con datos frescos
            $this->seedDemoData($tenant);
        });
    }
}