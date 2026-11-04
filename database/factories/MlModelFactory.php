<?php

namespace Database\Factories;

use App\Models\Dataset;
use App\Models\MlModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MlModel> */
class MlModelFactory extends Factory
{
    public function definition(): array
    {
        $algorithm = fake()->randomElement(array_keys(MlModel::ALGORITHMS));

        return [
            'name' => MlModel::ALGORITHMS[$algorithm].' model',
            'task' => 'demand',
            'algorithm' => $algorithm,
            'version' => now()->format('Y.m.d').'.'.fake()->unique()->numberBetween(1, 9999),
            'dataset_id' => Dataset::factory(),
            'file_path' => 'models/demand/test.joblib',
            'file_sha256' => hash('sha256', fake()->uuid()),
            'file_size' => 1024,
            'target' => 'units_sold',
            'features' => ['t', 'lag1'],
            'split' => 0.8,
            'train_points' => 76,
            'test_points' => 19,
            'dataset_records' => 5985,
            'mae' => 1.9, 'rmse' => 2.6, 'mape' => 57.0, 'wape' => 41.0, 'r2' => 0.5,
            'trained_through' => '2026-10-26',
            'status' => 'ready',
            'is_active' => false,
            'trained_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }
}
