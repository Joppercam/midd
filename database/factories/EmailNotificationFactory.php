<?php

namespace Database\Factories;

use App\Models\EmailNotification;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailNotificationFactory extends Factory
{
    protected $model = EmailNotification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'subject' => $this->faker->sentence(),
            'body' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(['info', 'success', 'warning', 'error']),
            'status' => $this->faker->randomElement(['pending', 'sent', 'failed']),
            'sent_at' => $this->faker->optional()->dateTime(),
            'metadata' => [
                'title' => $this->faker->sentence(3),
                'message' => $this->faker->sentence(),
                'icon' => $this->faker->randomElement(['info', 'success', 'warning', 'error']),
                'color' => $this->faker->randomElement(['blue', 'green', 'orange', 'red'])
            ]
        ];
    }

    public function sent(): self
    {
        return $this->state([
            'status' => 'sent',
            'sent_at' => now()
        ]);
    }

    public function pending(): self
    {
        return $this->state([
            'status' => 'pending',
            'sent_at' => null
        ]);
    }

    public function failed(): self
    {
        return $this->state([
            'status' => 'failed',
            'sent_at' => null
        ]);
    }
}