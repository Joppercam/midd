<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $demoRequests = [
            [
                'company_name' => 'Comercial Las Flores SpA',
                'rut' => '76.123.456-7',
                'contact_name' => 'María González Pérez',
                'email' => 'maria.gonzalez@comerciallasflores.cl',
                'phone' => '+56 9 8765 4321',
                'business_type' => 'retail',
                'employees' => '6-20',
                'message' => 'Estamos interesados en digitalizar nuestros procesos de facturación. Tenemos 3 sucursales y necesitamos integración con SII.',
                'status' => 'pending',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'company_name' => 'Restaurante El Sabor Chileno',
                'rut' => '96.789.012-3',
                'contact_name' => 'Carlos Mendoza Silva',
                'email' => 'carlos@elsaborchileno.cl',
                'phone' => '+56 2 2234 5678',
                'business_type' => 'restaurant',
                'employees' => '1-5',
                'message' => 'Buscamos un sistema completo para nuestro restaurante que incluya POS, inventario y facturación electrónica.',
                'status' => 'contacted',
                'contacted_at' => now()->subDays(1),
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(1),
            ],
            [
                'company_name' => 'Construcciones del Norte Ltda',
                'rut' => '87.654.321-0',
                'contact_name' => 'Ana Ruiz Torres',
                'email' => 'ana.ruiz@construccionesdelnorte.cl',
                'phone' => '+56 9 9876 5432',
                'business_type' => 'construction',
                'employees' => '21-50',
                'message' => 'Empresa constructora con múltiples proyectos simultáneos. Necesitamos mejor control de gastos y facturación por proyecto.',
                'status' => 'demo_scheduled',
                'contacted_at' => now()->subDays(2),
                'demo_scheduled_at' => now()->subHours(12),
                'created_at' => now()->subDays(4),
                'updated_at' => now()->subHours(12),
            ],
            [
                'company_name' => 'Servicios Profesionales ABC',
                'rut' => '65.432.109-8',
                'contact_name' => 'Roberto Jiménez López',
                'email' => 'roberto@serviciosabcd.cl',
                'phone' => '+56 9 5555 1234',
                'business_type' => 'services',
                'employees' => '6-20',
                'message' => 'Consultora de servicios profesionales. Buscamos automatizar la facturación y mejorar el seguimiento de clientes.',
                'status' => 'demo_completed',
                'contacted_at' => now()->subDays(5),
                'demo_scheduled_at' => now()->subDays(3),
                'created_at' => now()->subWeek(),
                'updated_at' => now()->subDays(3),
            ],
            [
                'company_name' => 'Distribuidora Central SA',
                'rut' => '45.678.901-2',
                'contact_name' => 'Patricia Morales Soto',
                'email' => 'patricia.morales@distribuidoracentral.cl',
                'phone' => '+56 2 2987 6543',
                'business_type' => 'retail',
                'employees' => '51-100',
                'message' => 'Distribuidora mayorista con gran volumen de transacciones. Necesitamos sistema robusto para facturación masiva.',
                'status' => 'converted',
                'contacted_at' => now()->subDays(10),
                'demo_scheduled_at' => now()->subDays(8),
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(6),
            ],
            [
                'company_name' => 'Farmacia Popular',
                'rut' => '34.567.890-1',
                'contact_name' => 'José Luis Fernández',
                'email' => 'joseluis@farmaciapopular.cl',
                'phone' => '+56 9 3333 7777',
                'business_type' => 'healthcare',
                'employees' => '1-5',
                'message' => 'Farmacia independiente que busca modernizar su sistema de ventas y control de inventario de medicamentos.',
                'status' => 'pending',
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6),
            ]
        ];

        foreach ($demoRequests as $request) {
            \App\Models\DemoRequest::create($request);
        }

        // Agregar notas a algunas solicitudes
        $requests = \App\Models\DemoRequest::all();
        
        if ($requests->count() >= 6) {
            $requests[1]->addNote('Cliente muy interesado. Programar llamada para mañana.', 'Admin User');
            $requests[2]->addNote('Demo programada para el viernes a las 10:00 AM.', 'Admin User');
            $requests[2]->addNote('Cliente confirmó asistencia a la demo.', 'Admin User');
            $requests[3]->addNote('Demo realizada exitosamente. Cliente muy satisfecho con las funcionalidades.', 'Admin User');
            $requests[4]->addNote('Cliente decidió contratar el plan empresarial.', 'Admin User');
            $requests[4]->addNote('Proceso de onboarding iniciado.', 'Admin User');
        }
    }
}
