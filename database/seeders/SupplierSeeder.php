<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        $suppliers = [
            [
                'rut' => '96.123.456-7',
                'name' => 'Proveedor Tecnológico Ltda.',
                'type' => 'company',
                'business_name' => 'Venta de equipos tecnológicos',
                'email' => 'ventas@proveedortech.cl',
                'phone' => '+56 2 2234 5678',
                'address' => 'Av. Apoquindo 3000',
                'city' => 'Las Condes',
                'commune' => 'Las Condes',
                'region' => 'Metropolitana',
                'payment_terms' => '30_days'
            ],
            [
                'rut' => '77.890.123-4',
                'name' => 'Distribuidora de Oficina S.A.',
                'type' => 'company',
                'business_name' => 'Artículos de oficina y papelería',
                'email' => 'contacto@distoficina.cl',
                'phone' => '+56 2 2345 6789',
                'address' => 'San Diego 1500',
                'city' => 'Santiago',
                'commune' => 'Santiago Centro',
                'region' => 'Metropolitana',
                'payment_terms' => '15_days'
            ],
            [
                'rut' => '88.456.789-0',
                'name' => 'Servicios Contables SpA',
                'type' => 'company',
                'business_name' => 'Servicios profesionales de contabilidad',
                'email' => 'info@servicontables.cl',
                'phone' => '+56 2 2456 7890',
                'address' => 'Av. Providencia 2500',
                'city' => 'Providencia',
                'commune' => 'Providencia',
                'region' => 'Metropolitana',
                'payment_terms' => 'immediate'
            ],
            [
                'rut' => '12.345.678-9',
                'name' => 'Juan Carlos Electricista',
                'type' => 'person',
                'business_name' => null,
                'email' => 'jc.electricista@gmail.com',
                'phone' => '+56 9 8765 4321',
                'address' => 'Los Aromos 567',
                'city' => 'Maipú',
                'commune' => 'Maipú',
                'region' => 'Metropolitana',
                'payment_terms' => 'immediate'
            ],
            [
                'rut' => '99.555.777-8',
                'name' => 'Transportes Rápidos Ltda.',
                'type' => 'company',
                'business_name' => 'Servicios de transporte y logística',
                'email' => 'operaciones@transportesrapidos.cl',
                'phone' => '+56 2 2567 8901',
                'address' => 'Av. Grecia 8000',
                'city' => 'Ñuñoa',
                'commune' => 'Ñuñoa',
                'region' => 'Metropolitana',
                'payment_terms' => '30_days'
            ],
            [
                'rut' => '87.654.321-0',
                'name' => 'Suministros Industriales del Sur',
                'type' => 'company',
                'business_name' => 'Herramientas y suministros industriales',
                'email' => 'ventas@sumindelsur.cl',
                'phone' => '+56 2 2678 9012',
                'address' => 'Av. Vicuña Mackenna 4500',
                'city' => 'Macul',
                'commune' => 'Macul',
                'region' => 'Metropolitana',
                'payment_terms' => '60_days'
            ],
            [
                'rut' => '76.543.210-K',
                'name' => 'Limpieza Total Express',
                'type' => 'company',
                'business_name' => 'Servicios de aseo y mantención',
                'email' => 'servicios@limpiezatotal.cl',
                'phone' => '+56 2 2789 0123',
                'address' => 'Santa Rosa 1234',
                'city' => 'Santiago',
                'commune' => 'San Miguel',
                'region' => 'Metropolitana',
                'payment_terms' => '15_days'
            ],
            [
                'rut' => '65.432.109-8',
                'name' => 'Marketing Digital Pro',
                'type' => 'company',
                'business_name' => 'Servicios de marketing y publicidad digital',
                'email' => 'hola@marketingpro.cl',
                'phone' => '+56 2 2890 1234',
                'address' => 'Av. Las Condes 12000',
                'city' => 'Las Condes',
                'commune' => 'Las Condes',
                'region' => 'Metropolitana',
                'payment_terms' => '30_days'
            ]
        ];

        foreach ($tenants as $tenant) {
            foreach ($suppliers as $supplierData) {
                Supplier::create([
                    'tenant_id' => $tenant->id,
                    'rut' => $supplierData['rut'],
                    'name' => $supplierData['name'],
                    'type' => $supplierData['type'],
                    'business_name' => $supplierData['business_name'],
                    'email' => $supplierData['email'],
                    'phone' => $supplierData['phone'],
                    'address' => $supplierData['address'],
                    'city' => $supplierData['city'],
                    'commune' => $supplierData['commune'],
                    'region' => $supplierData['region'],
                    'payment_terms' => $supplierData['payment_terms'],
                    'is_active' => true
                ]);
            }
            
            $this->command->info("Proveedores creados para tenant: {$tenant->name}");
        }
    }
}