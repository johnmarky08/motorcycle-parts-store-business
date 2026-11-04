<?php

namespace Database\Seeders;

use App\Services\Datasets\DatasetRegistry;
use Illuminate\Database\Seeder;

class DatasetSeeder extends Seeder
{
    public function run(): void
    {
        $registry = app(DatasetRegistry::class);

        $registry->registerSystemDataset(
            base_path('ml/datasets/sales_history.csv'),
            'sales_history',
            'Motorcycle Sales History',
            'Transaction-level sales records (one row per sold item) used for sales trend and revenue forecasting.'
        );
        $registry->registerSystemDataset(
            base_path('ml/datasets/product_demand.csv'),
            'product_demand',
            'Product Demand Dataset',
            'Weekly units sold per product, used for demand, slow-moving and best-selling product prediction.'
        );
    }
}
