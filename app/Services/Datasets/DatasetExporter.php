<?php

namespace App\Services\Datasets;

use App\Models\Category;
use App\Models\Dataset;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Turns the real orders in the database into the two CSV datasets (sales history and weekly product demand)
 * so the models can be retrained on what customers actually bought.
 */
class DatasetExporter
{
    public function __construct(private DatasetRegistry $registry)
    {
    }

    /** @return array{sales: Dataset, demand: Dataset} */
    public function refresh(?User $user): array
    {
        $directory = storage_path('app/ml/datasets/live');
        File::ensureDirectoryExists($directory);
        $salesPath = $directory.'/sales_history.csv';
        $demandPath = $directory.'/product_demand.csv';

        $weekly = $this->writeSalesHistory($salesPath);
        $this->writeProductDemand($demandPath, $weekly);

        return [
            'sales' => $this->registry->registerGenerated($salesPath, 'datasets/live/sales_history.csv', 'sales_history', 'Live Sales History', 'All orders in the database, one row per item (refreshed from orders).', $user),
            'demand' => $this->registry->registerGenerated($demandPath, 'datasets/live/product_demand.csv', 'product_demand', 'Live Product Demand', 'Weekly units per product from the orders in the database (complete weeks only).', $user),
        ];
    }

    /**
     * Writes the transaction-level CSV and returns weekly aggregates: [product_id][week_start] => [units, revenue, transactions[]].
     *
     * @return array{weeks: array<string, array<int, array{0: int, 1: float, 2: array<string, true>}>>, last_day: ?string}
     */
    private function writeSalesHistory(string $path): array
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, ['transaction_number', 'transaction_date', 'product_id', 'sku', 'product_name', 'category', 'quantity', 'unit_price', 'total_amount', 'payment_method']);

        $weeks = [];
        $lastDay = null;
        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->select('sale_items.id as id', 'sales.transaction_number', 'sales.transaction_date', 'sales.payment_method', 'sale_items.product_id', 'sale_items.sku',
                'sale_items.product_name', 'sale_items.quantity', 'sale_items.unit_price', 'sale_items.line_total', 'categories.name as category')
            ->whereNotNull('sale_items.product_id');

        // Items are read in insertion order, which is chronological because orders are only ever appended.
        foreach ($query->lazyById(2000, 'sale_items.id', 'id') as $row) {
            fputcsv($handle, [$row->transaction_number, substr($row->transaction_date, 0, 19), $row->product_id, $row->sku, $row->product_name,
                $row->category ?? 'Uncategorized', $row->quantity, number_format((float) $row->unit_price, 2, '.', ''), number_format((float) $row->line_total, 2, '.', ''), $row->payment_method]);

            $day = substr($row->transaction_date, 0, 10);
            $lastDay = $lastDay === null || $day > $lastDay ? $day : $lastDay;
            $week = Carbon::parse($day)->startOfWeek(Carbon::MONDAY)->toDateString();
            $weeks[$week][$row->product_id] ??= [0, 0.0, []];
            $weeks[$week][$row->product_id][0] += (int) $row->quantity;
            $weeks[$week][$row->product_id][1] += (float) $row->line_total;
            $weeks[$week][$row->product_id][2][$row->transaction_number] = true;
        }
        fclose($handle);

        return ['weeks' => $weeks, 'last_day' => $lastDay];
    }

    /** @param  array{weeks: array<string, array<int, array{0: int, 1: float, 2: array<string, true>}>>, last_day: ?string}  $weekly */
    private function writeProductDemand(string $path, array $weekly): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, ['week_start', 'product_id', 'sku', 'product_name', 'category', 'units_sold', 'revenue', 'transactions', 'avg_unit_price']);

        if ($weekly['weeks'] !== [] && $weekly['last_day'] !== null) {
            $first = Carbon::parse(min(array_keys($weekly['weeks'])));
            $last = Carbon::parse($weekly['last_day']);
            // Only complete Monday-Sunday weeks are exported.
            $lastWeekStart = $last->dayOfWeekIso === 7 ? $last->copy()->startOfWeek(Carbon::MONDAY) : $last->copy()->startOfWeek(Carbon::MONDAY)->subWeek();
            $products = Product::query()->with('category:id,name')->orderBy('id')->get();

            for ($week = $first->copy(); $week->lte($lastWeekStart); $week->addWeek()) {
                $key = $week->toDateString();
                foreach ($products as $product) {
                    [$units, $revenue, $transactions] = $weekly['weeks'][$key][$product->id] ?? [0, 0.0, []];
                    fputcsv($handle, [$key, $product->id, $product->sku, $product->name, $product->category?->name ?? 'Uncategorized', $units,
                        number_format($revenue, 2, '.', ''), count($transactions), number_format((float) $product->selling_price, 2, '.', '')]);
                }
            }
        }
        fclose($handle);
    }
}
