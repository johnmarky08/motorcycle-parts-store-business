<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockMovement> */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 30);

        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory()->inventoryClerk(),
            'movement_type' => 'received',
            'quantity' => $quantity,
            'balance_after' => $quantity + fake()->numberBetween(0, 50),
            'note' => 'Supplier delivery',
        ];
    }
}
