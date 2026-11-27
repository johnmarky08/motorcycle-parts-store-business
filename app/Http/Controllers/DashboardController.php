<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Dataset;
use App\Models\MlModel;
use App\Models\PredictionLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        // Customers have no dashboard: their home is the shop.
        if ($request->user()->role === 'user') {
            return redirect('/shop');
        }

        $range = $request->integer('range', 30);
        $range = in_array($range, [30, 365], true) ? $range : 30;
        $role = $request->user()->role;
        $latestSale = Sale::query()->max('transaction_date');
        $anchor = $latestSale ? Carbon::parse($latestSale) : now();
        $completedSales = Sale::query();
        $totalProducts = Product::count();
        $lowStock = Product::whereColumn('stock_quantity', '<=', 'reorder_threshold')
            ->where('stock_quantity', '>', 0)
            ->count();
        $outOfStock = Product::where('stock_quantity', 0)->count();
        $healthyStock = max($totalProducts - $lowStock - $outOfStock, 0);
        $periodStart = $anchor->copy()->subDays($range - 1)->startOfDay();
        $slowMovingSince = $anchor->copy()->subDays(90)->startOfDay();

        $totalsByDay = (clone $completedSales)
            ->where('transaction_date', '>=', $periodStart)
            ->selectRaw('DATE(transaction_date) as date, SUM(total_amount) as total')
            ->groupBy(DB::raw('DATE(transaction_date)'))
            ->pluck('total', 'date');

        // One point for every day of the period (₱0 on days without sales), so the chart has a dot per day
        // and never draws a curve across days that had no sales. Empty when the store has no sales at all.
        $trend = collect();
        if ($latestSale) {
            for ($day = $periodStart->copy(); $day->lte($anchor); $day->addDay()) {
                $key = $day->toDateString();
                $trend->push(['date' => $key, 'total' => (float) ($totalsByDay[$key] ?? 0)]);
            }
        }

        $bestSellers = SaleItem::query()
            ->select('sale_items.product_id', 'sale_items.product_name', 'sale_items.sku')
            ->selectRaw('SUM(sale_items.quantity) as units_sold')
            ->selectRaw('SUM(sale_items.line_total) as revenue')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
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
            ->leftJoin('sales', 'sales.id', '=', 'sale_items.sale_id')
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
                    ? (int) Carbon::parse($product->last_sale_at)->startOfDay()->diffInDays($anchor->copy()->startOfDay())
                    : null,
            ])
            ->values();

        $sections = [
            'sales' => in_array($role, [Role::Admin->value, Role::Instructor->value, Role::SalesPersonnel->value], true),
            'inventory' => in_array($role, [Role::Admin->value, Role::Instructor->value, Role::InventoryClerk->value], true),
            'rankings' => in_array($role, [Role::Admin->value, Role::Instructor->value], true),
            'analysis' => $role === Role::Instructor->value,
        ];
        $periodSales = (float) (clone $completedSales)->where('transaction_date', '>=', $periodStart)->sum('total_amount');
        $periodTransactions = (clone $completedSales)->where('transaction_date', '>=', $periodStart)->count();

        // Each role only receives the figures its dashboard sections show (no hidden data in the page props).
        $showSales = $sections['sales'];
        $showInventory = $sections['inventory'];
        $showRankings = $sections['rankings'];

        return Inertia::render('Dashboard', [
            'dashboard' => [
                'sections' => $sections,
                'data_as_of' => $anchor->toDateString(),
                'period_start' => $periodStart->toDateString(), // first day counted by the period cards (ends on data_as_of)
                'period_sales' => $showSales ? $periodSales : 0,
                'period_transactions' => $showSales ? $periodTransactions : 0,
                'average_sale' => $showSales && $periodTransactions > 0 ? round($periodSales / $periodTransactions, 2) : 0,
                'recent_sales' => $role === Role::SalesPersonnel->value
                    ? Sale::query()->latest('transaction_date')->latest('id')->limit(6)
                        ->get(['id', 'transaction_number', 'customer_name', 'total_amount', 'payment_method', 'transaction_date'])
                        ->map(fn (Sale $sale) => [
                            'id' => $sale->id, 'transaction_number' => $sale->transaction_number, 'customer_name' => $sale->customer_name, 'total' => (float) $sale->total_amount,
                            'payment_method' => $sale->payment_method, 'date' => $sale->transaction_date->toIso8601String(),
                        ])->values() : [],
                'recent_movements' => $role === Role::InventoryClerk->value
                    ? StockMovement::with(['product:id,name', 'user:id,name'])->latest('id')->limit(6)->get()
                        ->map(fn (StockMovement $movement) => [
                            'id' => $movement->id, 'product' => $movement->product?->name, 'type' => $movement->movement_type,
                            'quantity' => $movement->quantity, 'user' => $movement->user?->name, 'note' => $movement->note,
                            'at' => $movement->created_at?->diffForHumans(),
                        ])->values() : [],
                'low_stock_list' => $role === Role::InventoryClerk->value
                    ? Product::query()->whereColumn('stock_quantity', '<=', 'reorder_threshold')->orderBy('stock_quantity')->limit(5)
                        ->get(['id', 'name', 'sku', 'stock_quantity', 'reorder_threshold'])->values() : [],
                'model_evaluations' => $sections['analysis']
                    ? MlModel::query()->active()->get()->map(fn (MlModel $m) => [
                        'id' => $m->id, 'name' => $m->algorithmLabel(), 'task' => $m->task, 'version' => $m->version,
                        'mae' => $m->mae, 'rmse' => $m->rmse, 'mape' => $m->mape, 'r2' => $m->r2,
                    ])->values() : [],
                'analysis' => $sections['analysis'] ? [
                    'datasets' => Dataset::count(),
                    'records' => (int) Dataset::sum('records'),
                    'models' => MlModel::count(),
                    'predictions' => PredictionLog::count(),
                ] : null,
                'range' => $range,
                'total_sales' => $showSales ? (float) (clone $completedSales)->sum('total_amount') : 0,
                'transactions' => $showSales ? (clone $completedSales)->count() : 0,
                'products' => $showInventory ? $totalProducts : 0,
                'low_stock' => $showInventory ? $lowStock : 0,
                'out_of_stock' => $showInventory ? $outOfStock : 0,
                'inventory_health' => $showInventory ? [
                    'healthy' => $healthyStock,
                    'low_stock' => $lowStock,
                    'out_of_stock' => $outOfStock,
                ] : ['healthy' => 0, 'low_stock' => 0, 'out_of_stock' => 0],
                'sales_trend' => $showSales ? $trend : [],
                'best_sellers' => $showRankings ? $bestSellers : [],
                'slow_moving' => $showRankings ? $slowMoving : [],
            ],
        ]);
    }
}
