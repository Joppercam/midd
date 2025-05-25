<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear empresa de prueba 1
        $tenant1 = Tenant::create([
            'name' => 'Tecnología Innovadora SpA',
            'rut' => '76.543.210-K',
            'domain' => 'techinnovadora',
            'settings' => [
                'currency' => 'CLP',
                'timezone' => 'America/Santiago',
                'address' => 'Av. Providencia 1234, Providencia, Santiago',
                'phone' => '+56 2 2345 6789',
                'email' => 'contacto@techinnovadora.cl',
            ],
            'subscription_plan' => 'professional',
            'subscription_status' => 'active',
            'trial_ends_at' => null,
        ]);

        // Crear usuarios para tenant 1
        User::create([
            'name' => 'Juan Pérez',
            'email' => 'admin@techinnovadora.cl',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant1->id,
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        User::create([
            'name' => 'María González',
            'email' => 'ventas@techinnovadora.cl',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant1->id,
            'role' => 'sales',
            'permissions' => ['invoices.create', 'invoices.view', 'customers.manage'],
        ]);

        // Crear empresa de prueba 2
        $tenant2 = Tenant::create([
            'name' => 'Importadora del Pacífico Ltda',
            'rut' => '77.890.123-4',
            'domain' => 'importadorapacifico',
            'settings' => [
                'currency' => 'CLP',
                'timezone' => 'America/Santiago',
                'address' => 'Av. Libertador Bernardo O\'Higgins 2850, Santiago Centro',
                'phone' => '+56 2 2987 6543',
                'email' => 'info@importadorapacifico.cl',
            ],
            'subscription_plan' => 'starter',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        // Crear usuario para tenant 2
        User::create([
            'name' => 'Carlos Rodríguez',
            'email' => 'carlos@importadorapacifico.cl',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant2->id,
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        // Crear empresa de prueba 3
        $tenant3 = Tenant::create([
            'name' => 'Consultores Asociados S.A.',
            'rut' => '96.123.456-7',
            'domain' => 'consultoresasociados',
            'settings' => [
                'currency' => 'CLP',
                'timezone' => 'America/Santiago',
                'address' => 'Isidora Goyenechea 3000, Las Condes, Santiago',
                'phone' => '+56 2 2456 7890',
                'email' => 'contacto@consultoresasociados.cl',
            ],
            'subscription_plan' => 'enterprise',
            'subscription_status' => 'active',
            'trial_ends_at' => null,
        ]);

        // Crear usuarios para tenant 3
        User::create([
            'name' => 'Andrea Silva',
            'email' => 'andrea@consultoresasociados.cl',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant3->id,
            'role' => 'admin',
            'permissions' => ['*'],
        ]);

        User::create([
            'name' => 'Roberto Muñoz',
            'email' => 'roberto@consultoresasociados.cl',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant3->id,
            'role' => 'accountant',
            'permissions' => ['invoices.*', 'reports.*', 'customers.view'],
        ]);

        $this->command->info('Tenants y usuarios creados exitosamente.');
    }
}
