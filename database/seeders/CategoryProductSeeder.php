<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;

class CategoryProductSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('seeders/data/products.csv');
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $categories = [];

        while (($line = fgetcsv($handle)) !== false) {
            $row = array_combine($header, $line);
            $category = $categories[$row['category']] ??= Category::firstOrCreate(['name' => $row['category']]);

            $product = Product::updateOrCreate(['sku' => $row['sku']], [
                'category_id' => $category->id,
                'name' => $row['name'],
                'description' => $row['description'],
                'image' => is_file(public_path('images/products/'.$row['sku'].'.svg')) ? 'images/products/'.$row['sku'].'.svg' : null,
                'selling_price' => $row['selling_price'],
                'stock_quantity' => (int) $row['stock_quantity'],
                'reorder_threshold' => (int) $row['reorder_threshold'],
            ]);
            // Keep ids identical to the CSV datasets (product_id column) on a fresh database.
            if ($product->wasRecentlyCreated && (int) $product->id !== (int) $row['id']) {
                logger()->warning("Product id mismatch for {$row['sku']}: expected {$row['id']}, got {$product->id}. Run migrate:fresh --seed.");
            }

            if ($product->stockMovements()->doesntExist()) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'movement_type' => 'received',
                    'quantity' => $product->stock_quantity,
                    'balance_after' => $product->stock_quantity,
                    'note' => 'Opening stock',
                ]);
            }
        }
        fclose($handle);
    }
}
