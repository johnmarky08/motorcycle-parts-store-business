<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = $request->string('sort')->toString();
        $products = Product::query()->with('category')
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when($request->integer('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->string('status')->isNotEmpty(), function ($query) use ($request) {
                $status = $request->string('status')->toString();
                if ($status === 'out_of_stock') {
                    $query->where('stock_quantity', 0);
                } elseif ($status === 'low_stock') {
                    $query->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'reorder_threshold');
                } elseif ($status === 'in_stock') {
                    $query->whereColumn('stock_quantity', '>', 'reorder_threshold');
                } elseif ($status === 'slow_moving') {
                    $query->where('stock_quantity', '>', 0)
                        ->whereDoesntHave('saleItems', function ($query) {
                            $query->whereHas('sale', function ($query) {
                                $query->where('status', 'completed')
                                    ->where('transaction_date', '>=', now()->subDays(90)->startOfDay());
                            });
                        });
                }
            })
            ->when($sort === 'price_asc', fn ($query) => $query->orderBy('selling_price'))
            ->when($sort === 'price_desc', fn ($query) => $query->orderByDesc('selling_price'))
            ->when($sort === 'stock_asc', fn ($query) => $query->orderBy('stock_quantity'))
            ->when($sort === 'stock_desc', fn ($query) => $query->orderByDesc('stock_quantity'))
            ->when($sort === 'best_selling', function ($query) {
                $query->withSum(['saleItems as completed_units_sold' => function ($query) {
                    $query->whereHas('sale', fn ($query) => $query->where('status', 'completed'));
                }], 'quantity')->orderByDesc('completed_units_sold');
            }, fn ($query) => $query->orderBy('name'))
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('InventoryManagement', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'category_id', 'status', 'sort']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data, $request) {
            $product = Product::create($data);
            if ($product->stock_quantity > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'movement_type' => 'received',
                    'quantity' => $product->stock_quantity,
                    'balance_after' => $product->stock_quantity,
                    'note' => 'Initial stock',
                ]);
            }
        });

        return back()->with('status', 'Product created successfully.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data, $request, $product) {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $stockDifference = $data['stock_quantity'] - $lockedProduct->stock_quantity;
            $lockedProduct->update($data);
            if ($stockDifference !== 0) {
                StockMovement::create([
                    'product_id' => $lockedProduct->id,
                    'user_id' => $request->user()->id,
                    'movement_type' => 'correction',
                    'quantity' => $stockDifference,
                    'balance_after' => $data['stock_quantity'],
                    'note' => 'Product stock edited',
                ]);
            }
        });

        return back()->with('status', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        abort_if($product->saleItems()->exists(), 422, 'Products with sales history cannot be deleted.');
        $product->delete();

        return back()->with('status', 'Product deleted successfully.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
        ]);
        Category::create($data);

        return back()->with('status', 'Category created successfully.');
    }

    public function movements(Product $product)
    {
        return response()->json([
            'product' => $product->only(['id', 'name', 'sku']),
            'movements' => $product->stockMovements()
                ->with('user:id,name')
                ->latest()
                ->get()
                ->map(fn (StockMovement $movement) => [
                    'id' => $movement->id,
                    'movement_type' => $movement->movement_type,
                    'quantity' => $movement->quantity,
                    'balance_after' => $movement->balance_after,
                    'note' => $movement->note,
                    'user' => $movement->user?->name,
                    'created_at' => $movement->created_at?->toISOString(),
                ]),
        ]);
    }

    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'movement_type' => ['required', Rule::in(['received', 'reduction', 'correction'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $product, $request) {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $newQuantity = $data['movement_type'] === 'received'
                ? $lockedProduct->stock_quantity + $data['quantity']
                : $lockedProduct->stock_quantity - $data['quantity'];

            abort_if($newQuantity < 0, 422, 'The adjustment cannot reduce stock below zero.');
            $lockedProduct->update(['stock_quantity' => $newQuantity]);
            StockMovement::create([
                'product_id' => $lockedProduct->id,
                'user_id' => $request->user()->id,
                'movement_type' => $data['movement_type'],
                'quantity' => $data['movement_type'] === 'received' ? $data['quantity'] : -$data['quantity'],
                'balance_after' => $newQuantity,
                'note' => $data['note'],
            ]);
        });

        return back()->with('status', 'Stock adjusted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($request->route('product'))],
            'description' => ['nullable', 'string'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'reorder_threshold' => ['required', 'integer', 'min:0'],
        ]);
    }
}
