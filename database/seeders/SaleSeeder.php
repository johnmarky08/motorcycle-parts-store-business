<?php

namespace Database\Seeders;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads the transaction history from datasets/sales_history.csv so the database, the dashboard and the
 * machine-learning dataset all describe exactly the same sales. Stock levels are NOT reduced here: the
 * catalog CSV already holds today's stock.
 */
class SaleSeeder extends Seeder
{
    public function run(): void
    {
        if (Sale::query()->exists()) {
            return;
        }

        $file = base_path('ml/datasets/sales_history.csv');
        if (! is_file($file)) {
            $this->command?->warn('ml/datasets/sales_history.csv not found - skipping sales history.');

            return;
        }

        $cashiers = User::where('role', 'sales_personnel')->where('status', 'active')->pluck('id')->all();
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $names = DB::table('products')->pluck('name', 'id');
        $skus = DB::table('products')->pluck('sku', 'id');

        $sales = [];
        $items = [];
        $saleId = 0;
        $current = null;

        // Rows are written in batches (ids are assigned here because the tables are empty).
        $flush = function () use (&$sales, &$items) {
            if ($sales !== []) {
                DB::table('sales')->insert($sales);
                foreach (array_chunk($items, 1000) as $chunk) {
                    DB::table('sale_items')->insert($chunk);
                }
            }
            $sales = [];
            $items = [];
        };

        DB::transaction(function () use ($handle, $header, $cashiers, $names, $skus, &$sales, &$items, &$saleId, &$current, $flush) {
            while (($line = fgetcsv($handle)) !== false) {
                $row = array_combine($header, $line);

                if ($row['transaction_number'] !== $current) {
                    $current = $row['transaction_number'];
                    $saleId++;
                    if (count($sales) >= 500) {
                        $flush();
                    }
                    $sales[$saleId] = [
                        'id' => $saleId,
                        'transaction_number' => $current,
                        'user_id' => $cashiers ? $cashiers[$saleId % count($cashiers)] : null,
                        'payment_method' => $row['payment_method'],
                        'status' => 'completed',
                        'total_amount' => 0.0,
                        'transaction_date' => $row['transaction_date'],
                        'created_at' => $row['transaction_date'],
                        'updated_at' => $row['transaction_date'],
                    ];
                }

                $pid = (int) $row['product_id'];
                $sales[$saleId]['total_amount'] = round($sales[$saleId]['total_amount'] + (float) $row['total_amount'], 2);
                $items[] = [
                    'sale_id' => $saleId,
                    'product_id' => $pid,
                    'product_name' => $names[$pid] ?? $row['product_name'],
                    'sku' => $skus[$pid] ?? $row['sku'],
                    'quantity' => (int) $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'line_total' => $row['total_amount'],
                    'created_at' => $row['transaction_date'],
                    'updated_at' => $row['transaction_date'],
                ];
            }
            $flush();
        });
        fclose($handle);
    }
}
