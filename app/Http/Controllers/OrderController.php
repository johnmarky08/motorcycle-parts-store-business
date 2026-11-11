<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Support\Like;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** A customer's own order history. */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:60'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $orders = Sale::query()->with('items')->where('customer_id', $request->user()->id)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('transaction_number', 'like', '%'.Like::escape($search).'%'))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('transaction_date', '<=', $date))
            ->latest('transaction_date')->latest('id')
            ->paginate(10)->withQueryString()
            ->through(fn (Sale $sale) => [
                'id' => $sale->id,
                'transaction_number' => $sale->transaction_number,
                'transaction_date' => $sale->transaction_date->toIso8601String(),
                'payment_method' => $sale->payment_method,
                'total_amount' => (float) $sale->total_amount,
                'items' => $sale->items->map(fn ($item) => [
                    'product_name' => $item->product_name, 'sku' => $item->sku, 'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price, 'line_total' => (float) $item->line_total,
                ])->values(),
            ]);

        return Inertia::render('MyOrders', [
            'orders' => $orders,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
        ]);
    }
}
