<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Tenant;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $customers = [
                [
                    'tenant_id' => $tenant->id,
                    'rut' => '76.123.456-7',
                    'name' => 'Distribuidora Nacional S.A.',
                    'email' => 'contacto@distribuidoranacional.cl',
                    'phone' => '+56 2 2345 6789',
                    'address' => [
                        'street' => 'Av. Providencia 2594',
                        'comuna' => 'Providencia',
                        'city' => 'Santiago',
                        'region' => 'Región Metropolitana',
                    ],
                    'credit_limit' => 5000000.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'rut' => '77.890.123-4',
                    'name' => 'Comercial Los Andes Ltda.',
                    'email' => 'ventas@comerciallosandes.cl',
                    'phone' => '+56 2 2987 6543',
                    'address' => [
                        'street' => 'Calle Moneda 975, Piso 4',
                        'comuna' => 'Santiago Centro',
                        'city' => 'Santiago',
                        'region' => 'Región Metropolitana',
                    ],
                    'credit_limit' => 3000000.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'rut' => '12.345.678-9',
                    'name' => 'María Fernández Consultores',
                    'email' => 'maria.fernandez@mfconsultores.cl',
                    'phone' => '+56 9 9876 5432',
                    'address' => [
                        'street' => 'Av. Apoquindo 3000, Of. 701',
                        'comuna' => 'Las Condes',
                        'city' => 'Santiago',
                        'region' => 'Región Metropolitana',
                    ],
                    'credit_limit' => 1000000.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'rut' => '96.555.666-K',
                    'name' => 'Importadora del Pacífico S.A.',
                    'email' => 'info@importadorapacifico.cl',
                    'phone' => '+56 32 2123 4567',
                    'address' => [
                        'street' => 'Av. Brasil 2830',
                        'comuna' => 'Valparaíso',
                        'city' => 'Valparaíso',
                        'region' => 'Región de Valparaíso',
                    ],
                    'credit_limit' => 7500000.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'rut' => '10.987.654-3',
                    'name' => 'Juan Carlos Pérez',
                    'email' => 'jcperez@gmail.com',
                    'phone' => '+56 9 8765 4321',
                    'address' => [
                        'street' => 'Los Aromos 123',
                        'comuna' => 'La Reina',
                        'city' => 'Santiago',
                        'region' => 'Región Metropolitana',
                    ],
                    'credit_limit' => 500000.00,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'rut' => '78.901.234-5',
                    'name' => 'Tecnología Avanzada SpA',
                    'email' => 'ventas@tecavanzada.cl',
                    'phone' => '+56 2 2456 7890',
                    'address' => [
                        'street' => 'Av. Presidente Riesco 5435, Torre 2',
                        'comuna' => 'Las Condes',
                        'city' => 'Santiago',
                        'region' => 'Región Metropolitana',
                    ],
                    'credit_limit' => 10000000.00,
                ],
            ];

            foreach ($customers as $customer) {
                Customer::create($customer);
            }
        }

        $this->command->info('Clientes creados para todos los tenants.');
    }
}
