<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Brake System', 'Engine', 'Electrical', 'Drivetrain', 'Tires', 'Filters', 'Lubricants', 'Accessories',
                'Suspension', 'Body Parts', 'Cooling', 'Exhaust',
            ]).' '.fake()->unique()->numerify('##'),
        ];
    }
}
