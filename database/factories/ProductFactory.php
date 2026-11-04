<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $bike = fake()->randomElement(['Honda Click', 'Yamaha Mio', 'Suzuki Raider', 'Kawasaki Ninja', 'Yamaha NMAX', 'Honda Beat']);
        $part = fake()->randomElement(['Brake Pad', 'Air Filter', 'Drive Belt', 'Clutch Cable', 'Spark Plug', 'Chain Set', 'Side Mirror']);

        return [
            'category_id' => Category::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('??-???-###')),
            'name' => "{$bike} {$part}",
            'description' => "Replacement {$part} for {$bike} models.",
            'selling_price' => fake()->randomFloat(2, 90, 4500),
            'stock_quantity' => fake()->numberBetween(0, 120),
            'reorder_threshold' => fake()->numberBetween(5, 20),
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock_quantity' => 0]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => ['stock_quantity' => 3, 'reorder_threshold' => 10]);
    }
}
