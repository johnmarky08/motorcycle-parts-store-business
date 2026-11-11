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

        return Inertia::render('Dashboard', [
            'dashboard' => [
                'sections' => $sections,
                'data_as_of' => $anchor->toDateString(),
                'period_sales' => $periodSales,
                'period_transactions' => $periodTransactions,
                'average_sale' => $periodTransactions > 0 ? round($periodSales / $periodTransactions, 2) : 0,
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
