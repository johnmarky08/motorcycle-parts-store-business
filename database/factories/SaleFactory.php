<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sale> */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-60 days', 'now');

        return [
            'transaction_number' => 'TX-'.$date->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'user_id' => User::factory()->salesPersonnel(),
            'payment_method' => fake()->randomElement(config('store.payment_methods')),
            'status' => 'completed',
            'total_amount' => 0,
            'transaction_date' => $date,
        ];
    }
}
