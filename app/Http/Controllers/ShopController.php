<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** The customer storefront: catalog, cart and checkout (role "user"). */
class ShopController extends Controller
{
    public function index(Request $request): Response
    {
        // Closures: a cart update reloads only "cart", "status" and "error", so the catalogue is not rebuilt.
        $products = fn () => Product::query()->with('category:id,name')->orderBy('name')->limit(2000)->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category?->name,
                'description' => $product->description,
                'price' => (float) $product->selling_price,
                'stock' => $product->stock_quantity,
                'image' => $product->image_url,
            ]);

        return Inertia::render('Shop', [
            'products' => $products,
            'categories' => fn () => Category::orderBy('name')->get(['id', 'name']),
            'cart' => fn () => $this->cartPayload($request),
            'paymentMethods' => config('store.payment_methods'),
        ]);
    }

    public function addToCart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        $product = Product::findOrFail($data['product_id']);
        $quantity = (int) ($data['quantity'] ?? 1);

        if ($product->stock_quantity < 1) {
            return back()->with('error', "{$product->name} is out of stock.");
        }

        $item = CartItem::firstOrNew(['user_id' => $request->user()->id, 'product_id' => $product->id]);
        $wanted = ($item->exists ? $item->quantity : 0) + $quantity;

        if ($wanted > $product->stock_quantity) {
            return back()->with('error', "Only {$product->stock_quantity} units of {$product->name} are available.");
        }

        $item->quantity = $wanted;
        $item->save();

        return back()->with('status', "{$product->name} was added to your cart.");
    }

    public function updateCart(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000']]);

        if ($data['quantity'] > $product->stock_quantity) {
            return back()->with('error', "Only {$product->stock_quantity} units of {$product->name} are available.");
        }

        CartItem::where('user_id', $request->user()->id)->where('product_id', $product->id)->update(['quantity' => $data['quantity']]);

        return back();
    }

    public function removeFromCart(Request $request, Product $product): RedirectResponse
    {
        CartItem::where('user_id', $request->user()->id)->where('product_id', $product->id)->delete();

        return back();
    }

    public function clearCart(Request $request): RedirectResponse
    {
        CartItem::where('user_id', $request->user()->id)->delete();

        return back()->with('status', 'Your cart was cleared.');
    }

    public function checkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(config('store.payment_methods'))],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $user = $request->user();

        // A double click or a retried request must not create the order twice.
        if (Sale::where('idempotency_key', $data['idempotency_key'])->where('customer_id', $user->id)->exists()) {
            return redirect('/orders')->with('status', 'This order was already placed.');
        }

        try {
            $sale = DB::transaction(function () use ($data, $user) {
                $lines = CartItem::where('user_id', $user->id)->orderBy('product_id')->get();
                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
                }

                $products = Product::whereIn('id', $lines->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $total = 0.0;
                $items = [];

                foreach ($lines as $line) {
                    $product = $products[$line->product_id];
                    if ($line->quantity > $product->stock_quantity) {
                        throw ValidationException::withMessages([
                            'cart' => "Only {$product->stock_quantity} units of {$product->name} are available. Please update your cart.",
                        ]);
                    }
                    // Prices always come from the database, never from the browser.
                    $lineTotal = round((float) $product->selling_price * $line->quantity, 2);
                    $total += $lineTotal;
                    $items[] = [$product, $line->quantity, $lineTotal];
                }

                $now = now();
                $sale = Sale::create([
                    'customer_id' => $user->id,
                    'customer_name' => $user->name,
                    'transaction_number' => 'TMP-'.bin2hex(random_bytes(8)),
                    'idempotency_key' => $data['idempotency_key'],
                    'transaction_date' => $now,
                    'payment_method' => $data['payment_method'],
                    'total_amount' => round($total, 2),
                ]);
                $sale->update(['transaction_number' => sprintf('TX-%s-%05d', $now->format('Ymd'), $sale->id)]);

                foreach ($items as [$product, $quantity, $lineTotal]) {
                    $sale->items()->create([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'sku' => $product->sku,
                        'quantity' => $quantity,
                        'unit_price' => $product->selling_price,
                        'line_total' => $lineTotal,
                    ]);
                    $product->decrement('stock_quantity', $quantity);
                    StockMovement::create([
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                        'movement_type' => 'sale',
                        'quantity' => -$quantity,
                        'balance_after' => $product->stock_quantity,
                        'note' => $sale->transaction_number,
                    ]);
                }

                CartItem::where('user_id', $user->id)->delete();

                return $sale;
            });
        } catch (ValidationException $exception) {
            return back()->with('error', collect($exception->errors())->flatten()->first());
        } catch (UniqueConstraintViolationException) {
            // Two simultaneous submissions with the same key: the other one already created the order.
            return redirect('/orders')->with('status', 'This order was already placed.');
        }

        Log::channel('security')->info('order.placed', ['sale_id' => $sale->id, 'user_id' => $user->id, 'total' => $sale->total_amount]);

        return redirect('/orders')->with('status', "Order {$sale->transaction_number} was placed successfully. Thank you for shopping with us!");
    }

    /** @return array{items: list<array<string, mixed>>, subtotal: float, count: int} */
    private function cartPayload(Request $request): array
    {
        $items = CartItem::with('product.category:id,name')->where('user_id', $request->user()->id)->orderBy('id')->get()
            ->filter(fn (CartItem $item) => $item->product !== null)
            ->map(fn (CartItem $item) => [
                'product_id' => $item->product_id,
                'name' => $item->product->name,
                'sku' => $item->product->sku,
                'image' => $item->product->image_url,
                'unit_price' => (float) $item->product->selling_price,
                'quantity' => $item->quantity,
                'stock' => $item->product->stock_quantity,
                'line_total' => round((float) $item->product->selling_price * $item->quantity, 2),
            ])->values();

        return ['items' => $items->all(), 'subtotal' => round($items->sum('line_total'), 2), 'count' => (int) $items->sum('quantity')];
    }
}
