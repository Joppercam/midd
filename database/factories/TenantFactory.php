<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $rut = $this->faker->numberBetween(10000000, 99999999);
        $dv = $this->calculateDV($rut);
        
        return [
            'name' => $this->faker->company(),
            'rut' => $rut . '-' . $dv,
            'domain' => $this->faker->slug() . '.crecepyme.com',
            'subscription_plan' => $this->faker->randomElement(['trial', 'basic', 'professional', 'enterprise']),
            'subscription_status' => $this->faker->randomElement(['trial', 'active', 'suspended']),
            'trial_ends_at' => $this->faker->optional()->dateTimeBetween('now', '+30 days')
        ];
    }

    private function calculateDV($rut): string
    {
        $suma = 0;
        $multiplicador = 2;
        
        for ($i = strlen($rut) - 1; $i >= 0; $i--) {
            $suma += $rut[$i] * $multiplicador;
            $multiplicador = $multiplicador == 7 ? 2 : $multiplicador + 1;
        }
        
        $resto = $suma % 11;
        $dv = 11 - $resto;
        
        if ($dv == 11) return '0';
        if ($dv == 10) return 'K';
        
        return (string)$dv;
    }
}