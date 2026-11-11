<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Support\Like;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only transaction history for sales personnel: orders are placed by customers in the shop. */
class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:60'],
            'payment_method' => ['nullable', Rule::in(config('store.payment_methods'))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $query = Sale::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $term = '%'.Like::escape($search).'%';
                $query->where(fn ($query) => $query->where('transaction_number', 'like', $term)->orWhere('customer_name', 'like', $term));
            })
            ->when($filters['payment_method'] ?? null, fn ($query, $method) => $query->where('payment_method', $method))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('transaction_date', '<=', $date));

        $totals = (clone $query)->selectRaw('COUNT(*) as transactions, COALESCE(SUM(total_amount), 0) as total')->first();

        $transactions = $query->with('items')->latest('transaction_date')->latest('id')->paginate(15)->withQueryString()
            ->through(fn (Sale $sale) => [
                'id' => $sale->id,
                'transaction_number' => $sale->transaction_number,
                'transaction_date' => $sale->transaction_date->toIso8601String(),
                'customer_name' => $sale->customer_name ?? 'Walk-In Customer',
                'payment_method' => $sale->payment_method,
                'total_amount' => (float) $sale->total_amount,
                'items' => $sale->items->map(fn ($item) => [
                    'product_name' => $item->product_name, 'sku' => $item->sku, 'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price, 'line_total' => (float) $item->line_total,
                ])->values(),
            ]);

        return Inertia::render('SalesManagement', [
            'transactions' => $transactions,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'payment_method' => $filters['payment_method'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
            'summary' => [
                'transactions' => (int) $totals->transactions,
                'total' => (float) $totals->total,
                'average' => $totals->transactions > 0 ? round($totals->total / $totals->transactions, 2) : 0,
            ],
            'paymentMethods' => config('store.payment_methods'),
        ]);
    }
}
