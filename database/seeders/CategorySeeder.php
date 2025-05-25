<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Tecnología',
                'description' => 'Productos tecnológicos y electrónicos',
            ],
            [
                'name' => 'Oficina',
                'description' => 'Artículos de oficina y papelería',
            ],
            [
                'name' => 'Servicios Profesionales',
                'description' => 'Servicios de consultoría y asesoría',
            ],
            [
                'name' => 'Software',
                'description' => 'Licencias y desarrollos de software',
            ],
            [
                'name' => 'Hardware',
                'description' => 'Equipos y componentes informáticos',
            ],
            [
                'name' => 'Alimentación',
                'description' => 'Productos alimenticios y bebidas',
            ],
            [
                'name' => 'Materiales',
                'description' => 'Materiales de construcción e insumos',
            ],
            [
                'name' => 'Capacitación',
                'description' => 'Cursos y programas de formación',
            ],
        ];

        // Crear categorías para cada tenant
        $tenants = Tenant::all();
        
        foreach ($tenants as $tenant) {
            foreach ($categories as $category) {
                Category::create([
                    'tenant_id' => $tenant->id,
                    'name' => $category['name'],
                    'slug' => Str::slug($category['name']),
                    'description' => $category['description'],
                ]);
            }
        }

        $this->command->info('Categorías creadas para todos los tenants.');
    }
}
