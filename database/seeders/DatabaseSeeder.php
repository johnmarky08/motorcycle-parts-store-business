<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters: users -> catalog -> sales history -> datasets -> trained models.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategoryProductSeeder::class,
            SaleSeeder::class,
            DatasetSeeder::class,
            MlModelSeeder::class,
        ]);
    }
}
