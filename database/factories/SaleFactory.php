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
        $customer = User::factory()->customer()->create();

        return [
            'transaction_number' => 'TX-'.$date->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'payment_method' => fake()->randomElement(config('store.payment_methods')),
            'total_amount' => 0,
            'transaction_date' => $date,
        ];
    }
}
