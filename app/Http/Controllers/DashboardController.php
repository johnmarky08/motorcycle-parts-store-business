<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $range = $request->integer('range', 30);
        $range = in_array($range, [30, 365], true) ? $range : 30;
        $completedSales = Sale::query()->where('status', 'completed');
        $totalProducts = Product::count();
        $lowStock = Product::whereColumn('stock_quantity', '<=', 'reorder_threshold')
            ->where('stock_quantity', '>', 0)
            ->count();
        $outOfStock = Product::where('stock_quantity', 0)->count();
        $healthyStock = max($totalProducts - $lowStock - $outOfStock, 0);
        $periodStart = now()->subDays($range - 1)->startOfDay();
        $slowMovingSince = now()->subDays(90)->startOfDay();

        $trend = (clone $completedSales)
            ->where('transaction_date', '>=', $periodStart)
            ->selectRaw('DATE(transaction_date) as date, SUM(total_amount) as total')
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'total' => (float) $row->total,
            ])
            ->values();

        $bestSellers = SaleItem::query()
            ->select('sale_items.product_id', 'sale_items.product_name', 'sale_items.sku')
            ->selectRaw('SUM(sale_items.quantity) as units_sold')
            ->selectRaw('SUM(sale_items.line_total) as revenue')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->groupBy('sale_items.product_id', 'sale_items.product_name', 'sale_items.sku')
            ->orderByDesc('units_sold')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'name' => $item->product_name,
                'sku' => $item->sku,
                'units_sold' => (int) $item->units_sold,
                'revenue' => (float) $item->revenue,
            ])
            ->values();

        $slowMoving = Product::query()
            ->select('products.id', 'products.name', 'products.sku', 'products.stock_quantity')
            ->selectRaw('MAX(sales.transaction_date) as last_sale_at')
            ->leftJoin('sale_items', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_items.sale_id')
                    ->where('sales.status', 'completed');
            })
            ->where('products.stock_quantity', '>', 0)
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.stock_quantity')
            ->havingRaw('MAX(sales.transaction_date) IS NULL OR MAX(sales.transaction_date) < ?', [$slowMovingSince])
            ->orderBy('last_sale_at', 'asc')
            ->limit(5)
            ->get()
            ->map(fn ($product) => [
                'name' => $product->name,
                'sku' => $product->sku,
                'stock' => (int) $product->stock_quantity,
                'days_without_sale' => $product->last_sale_at
                    ? Carbon::parse($product->last_sale_at)->diffInDays(now())
                    : null,
            ])
            ->values();

        return Inertia::render('Dashboard', [
            'dashboard' => [
                'range' => $range,
                'total_sales' => (float) (clone $completedSales)->sum('total_amount'),
                'transactions' => (clone $completedSales)->count(),
                'products' => $totalProducts,
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
                'inventory_health' => [
                    'healthy' => $healthyStock,
                    'low_stock' => $lowStock,
                    'out_of_stock' => $outOfStock,
                ],
                'sales_trend' => $trend,
                'best_sellers' => $bestSellers,
                'slow_moving' => $slowMoving,
            ],
        ]);
    }
}
