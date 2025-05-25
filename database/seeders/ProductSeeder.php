<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $categories = Category::where('tenant_id', $tenant->id)->get();
            
            $products = [
                [
                    'tenant_id' => $tenant->id,
                    'category_id' => $categories->where('name', 'Hardware')->first()->id ?? null,
                    'sku' => 'COMP-001',
                    'name' => 'Computadora de Escritorio HP',
                    'description' => 'PC HP ProDesk 400 G7, Intel Core i5, 8GB RAM, 256GB SSD',
                    'price' => 850000.00,
                    'cost' => 680000.00,
                    'stock_quantity' => 15,
                    'min_stock_alert' => 5,
                    'is_service' => false,
                    'tax_rate' => 19.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'category_id' => $categories->where('name', 'Hardware')->first()->id ?? null,
                    'sku' => 'MON-001',
                    'name' => 'Monitor LED 24"',
                    'description' => 'Monitor Samsung 24" Full HD, Panel IPS',
                    'price' => 280000.00,
                    'cost' => 210000.00,
                    'stock_quantity' => 25,
                    'min_stock_alert' => 10,
                    'is_service' => false,
                    'tax_rate' => 19.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'category_id' => $categories->where('name', 'Software')->first()->id ?? null,
                    'sku' => 'SOFT-001',
                    'name' => 'Licencia Microsoft Office 365',
                    'description' => 'Licencia anual Office 365 Business Standard',
                    'price' => 45000.00,
                    'cost' => 35000.00,
                    'stock_quantity' => 100,
                    'min_stock_alert' => 20,
                    'is_service' => false,
                    'tax_rate' => 19.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'category_id' => $categories->where('name', 'Servicios Profesionales')->first()->id ?? null,
                    'sku' => 'SERV-001',
                    'name' => 'Soporte Técnico Mensual',
                    'description' => 'Servicio de soporte técnico remoto, 40 horas mensuales',
                    'price' => 120000.00,
                    'cost' => 80000.00,
                    'stock_quantity' => 0,
                    'min_stock_alert' => 0,
                    'is_service' => true,
                    'tax_rate' => 19.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'category_id' => $categories->where('name', 'Oficina')->first()->id ?? null,
                    'sku' => 'OFI-001',
                    'name' => 'Resma Papel A4',
                    'description' => 'Resma de papel A4 75gr, 500 hojas',
                    'price' => 4500.00,
                    'cost' => 3200.00,
                    'stock_quantity' => 200,
                    'min_stock_alert' => 50,
                    'is_service' => false,
                    'tax_rate' => 19.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'category_id' => $categories->where('name', 'Hardware')->first()->id ?? null,
                    'sku' => 'IMP-001',
                    'name' => 'Impresora Láser HP',
                    'description' => 'Impresora HP LaserJet Pro M404dw',
                    'price' => 450000.00,
                    'cost' => 350000.00,
                    'stock_quantity' => 8,
                    'min_stock_alert' => 3,
                    'is_service' => false,
                    'tax_rate' => 19.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'category_id' => $categories->where('name', 'Servicios Profesionales')->first()->id ?? null,
                    'sku' => 'CONS-001',
                    'name' => 'Consultoría en Sistemas',
                    'description' => 'Hora de consultoría en implementación de sistemas',
                    'price' => 50000.00,
                    'cost' => 30000.00,
                    'stock_quantity' => 0,
                    'min_stock_alert' => 0,
                    'is_service' => true,
                    'tax_rate' => 19.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'category_id' => $categories->where('name', 'Capacitación')->first()->id ?? null,
                    'sku' => 'CAP-001',
                    'name' => 'Curso Excel Avanzado',
                    'description' => 'Curso de Excel Avanzado, 16 horas',
                    'price' => 150000.00,
                    'cost' => 80000.00,
                    'stock_quantity' => 0,
                    'min_stock_alert' => 0,
                    'is_service' => true,
                    'tax_rate' => 19.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'category_id' => $categories->where('name', 'Tecnología')->first()->id ?? null,
                    'sku' => 'NOTE-001',
                    'name' => 'Notebook Lenovo ThinkPad',
                    'description' => 'Notebook Lenovo ThinkPad L14, Intel Core i7, 16GB RAM, 512GB SSD',
                    'price' => 1200000.00,
                    'cost' => 950000.00,
                    'stock_quantity' => 10,
                    'min_stock_alert' => 3,
                    'is_service' => false,
                    'tax_rate' => 19.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'category_id' => $categories->where('name', 'Oficina')->first()->id ?? null,
                    'sku' => 'SILLA-001',
                    'name' => 'Silla Ergonómica',
                    'description' => 'Silla ergonómica con soporte lumbar ajustable',
                    'price' => 180000.00,
                    'cost' => 120000.00,
                    'stock_quantity' => 20,
                    'min_stock_alert' => 5,
                    'is_service' => false,
                    'tax_rate' => 19.00,
                ],
            ];

            foreach ($products as $product) {
                Product::create($product);
            }
        }

        $this->command->info('Productos creados para todos los tenants.');
    }
}
