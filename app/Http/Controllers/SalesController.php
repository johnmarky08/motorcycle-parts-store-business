<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        $sales = Sale::with('items')
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where('transaction_number', 'like', '%' . $request->string('search') . '%'))
            ->when($request->filled('date'), function ($query) use ($request) {
                $day = Carbon::createFromFormat('!Y-m-d', $request->string('date')->toString(), config('app.timezone'));

                $query->where('transaction_date', '>=', $day)
                    ->where('transaction_date', '<', $day->copy()->addDay());
            })
            ->when($request->string('payment_method')->isNotEmpty(), fn ($query) => $query->where('payment_method', $request->string('payment_method')))
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('transaction_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('SalesManagement', [
            'transactions' => $sales,
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku', 'selling_price', 'stock_quantity']),
            'filters' => $request->only(['search', 'date', 'payment_method', 'status']),
        ]);
    }

    public function show(Sale $sale): Response
    {
        return Inertia::render('SalesManagement', [
            'selectedTransaction' => $sale->load('items'),
            'transactions' => Sale::with('items')->latest('transaction_date')->paginate(20),
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku', 'selling_price', 'stock_quantity']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'uuid'],
            'payment_method' => ['required', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($data, $request) {
            abort_if(Sale::where('idempotency_key', $data['idempotency_key'])->exists(), 422, 'This sale has already been submitted.');
            $items = [];
            $total = 0;

            foreach ($data['items'] as $item) {
                $product = Product::query()->lockForUpdate()->findOrFail($item['product_id']);
                abort_if($product->stock_quantity < $item['quantity'], 422, "Insufficient stock for {$product->name}.");
                $lineTotal = $product->selling_price * $item['quantity'];
                $items[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->selling_price,
                    'line_total' => $lineTotal,
                ];
                $total += $lineTotal;
                $product->decrement('stock_quantity', $item['quantity']);
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'movement_type' => 'sale',
                    'quantity' => -$item['quantity'],
                    'balance_after' => $product->stock_quantity,
                    'note' => 'Sale transaction',
                ]);
            }

            $sale = Sale::create([
                'user_id' => $request->user()->id,
                'transaction_number' => 'TX-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4)),
                'idempotency_key' => $data['idempotency_key'],
                'transaction_date' => now(),
                'status' => 'completed',
                'payment_method' => $data['payment_method'],
                'total_amount' => $total,
            ]);
            $sale->items()->createMany($items);
        });

        return back()->with('status', 'Sale completed successfully.');
    }
}
