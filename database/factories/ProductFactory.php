<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'category_id' => Category::factory(),
            'sku' => 'PRD-' . $this->faker->unique()->numberBetween(1000, 9999),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->numberBetween(1000, 50000),
            'cost' => $this->faker->numberBetween(500, 25000),
            'stock_quantity' => $this->faker->numberBetween(0, 100),
            'min_stock_alert' => $this->faker->numberBetween(5, 20),
            'is_service' => $this->faker->boolean(20), // 20% chance of being service
            'tax_rate' => 19.00
        ];
    }
}