<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $rut = $this->faker->numberBetween(10000000, 99999999);
        $dv = $this->calculateDV($rut);
        
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->company(),
            'rut' => $rut . '-' . $dv,
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'region' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'contact_person' => $this->faker->name(),
            'credit_limit' => $this->faker->numberBetween(100000, 5000000),
            'payment_terms' => $this->faker->randomElement([0, 15, 30, 45, 60]),
            'is_active' => true
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