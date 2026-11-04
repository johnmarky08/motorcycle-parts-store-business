<?php

namespace Database\Factories;

use App\Models\Dataset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Dataset> */
class DatasetFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'description' => fake()->sentence(),
            'type' => 'sales_history',
            'file_path' => 'datasets/'.Str::slug($name).'.csv',
            'records' => fake()->numberBetween(1000, 20000),
            'columns' => 10,
            'coverage_start' => '2025-01-06',
            'coverage_end' => '2026-11-01',
            'status' => 'validated',
            'target' => 'total_amount',
            'features' => ['transaction_date', 'product_id', 'quantity'],
            'schema' => [['transaction_date', 'date'], ['quantity', 'integer']],
            'is_system' => false,
        ];
    }
}
