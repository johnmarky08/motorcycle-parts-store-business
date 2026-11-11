<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'category_id' => Category::firstOrCreate(['name' => 'Brake System'])->id,
            'name' => 'Brake Pad', 'sku' => 'BP-'.Str::random(5), 'selling_price' => 500, 'stock_quantity' => 10, 'reorder_threshold' => 2,
        ], $attributes));
    }

    public function test_shop_lists_every_product_with_category_and_picture(): void
    {
        $customer = User::factory()->customer()->create();
        $this->product(['name' => 'Chain Set', 'sku' => 'CH-1']);

        $this->actingAs($customer)->get('/shop')->assertInertia(fn ($page) => $page
            ->component('Shop')
            ->has('products', 1)
            ->where('products.0.category', 'Brake System')
            ->where('products.0.image', fn ($image) => str_starts_with($image, '/images/'))
            ->has('cart.items', 0));
    }

    public function test_cart_add_edit_remove_and_stock_limits(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->product(['stock_quantity' => 3]);

        $this->actingAs($customer)->post('/cart', ['product_id' => $product->id, 'quantity' => 2])->assertSessionHas('status');
        $this->actingAs($customer)->post('/cart', ['product_id' => $product->id, 'quantity' => 5])->assertSessionHas('error');
        $this->assertSame(2, CartItem::first()->quantity);

        $this->actingAs($customer)->patch("/cart/{$product->id}", ['quantity' => 3])->assertSessionHasNoErrors();
        $this->assertSame(3, CartItem::first()->quantity);
        $this->actingAs($customer)->patch("/cart/{$product->id}", ['quantity' => 4])->assertSessionHas('error');

        $this->actingAs($customer)->delete("/cart/{$product->id}");
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_creates_the_sale_with_the_customer_and_reduces_stock(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Maria Santos']);
        $product = $this->product(['selling_price' => 500, 'stock_quantity' => 10]);
        CartItem::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 3]);

        $this->actingAs($customer)->post('/checkout', ['payment_method' => 'GCash', 'idempotency_key' => (string) Str::uuid(), 'total' => 1])
            ->assertRedirect('/orders')->assertSessionHas('status');

        $sale = Sale::firstOrFail();
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertSame('Maria Santos', $sale->customer_name);
        $this->assertSame(1500.0, (float) $sale->total_amount); // price comes from the database
        $this->assertSame(7, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'movement_type' => 'sale', 'quantity' => -3, 'balance_after' => 7]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_is_idempotent_and_refuses_an_empty_or_oversold_cart(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->product(['stock_quantity' => 2]);
        $key = (string) Str::uuid();

        $this->actingAs($customer)->post('/checkout', ['payment_method' => 'Cash', 'idempotency_key' => $key])->assertSessionHas('error'); // empty cart

        CartItem::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 2]);
        $this->actingAs($customer)->post('/checkout', ['payment_method' => 'Cash', 'idempotency_key' => $key])->assertRedirect('/orders');
        $this->actingAs($customer)->post('/checkout', ['payment_method' => 'Cash', 'idempotency_key' => $key])->assertRedirect('/orders');
        $this->assertDatabaseCount('sales', 1);

        // stock changed after the item was put in the cart
        $other = User::factory()->customer()->create();
        CartItem::create(['user_id' => $other->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($other)->post('/checkout', ['payment_method' => 'Cash', 'idempotency_key' => (string) Str::uuid()])->assertSessionHas('error');
        $this->assertDatabaseCount('sales', 1);
        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_invalid_payment_method_is_rejected(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)->post('/checkout', ['payment_method' => 'Bitcoin', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('payment_method');
    }

    public function test_customers_only_see_their_own_orders_and_can_filter_by_date_range(): void
    {
        $me = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $mine = Sale::create(['customer_id' => $me->id, 'customer_name' => $me->name, 'transaction_number' => 'TX-MINE', 'transaction_date' => '2026-05-10 10:00:00', 'payment_method' => 'Cash', 'total_amount' => 10]);
        Sale::create(['customer_id' => $other->id, 'customer_name' => $other->name, 'transaction_number' => 'TX-OTHER', 'transaction_date' => '2026-05-10 10:00:00', 'payment_method' => 'Cash', 'total_amount' => 10]);
        Sale::create(['customer_id' => $me->id, 'customer_name' => $me->name, 'transaction_number' => 'TX-OLD', 'transaction_date' => '2026-01-01 10:00:00', 'payment_method' => 'Cash', 'total_amount' => 10]);

        $this->actingAs($me)->get('/orders')->assertInertia(fn ($page) => $page->has('orders.data', 2));
        $this->actingAs($me)->get('/orders?date_from=2026-05-01&date_to=2026-05-31')->assertInertia(fn ($page) => $page
            ->has('orders.data', 1)->where('orders.data.0.transaction_number', 'TX-MINE'));
    }

    public function test_sales_personnel_see_all_transactions_with_customer_names_but_cannot_create_any(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Ana Reyes']);
        $seller = User::factory()->salesPersonnel()->create();
        Sale::create(['customer_id' => $customer->id, 'customer_name' => 'Ana Reyes', 'transaction_number' => 'TX-1', 'transaction_date' => '2026-05-10 10:00:00', 'payment_method' => 'Cash', 'total_amount' => 10]);

        $this->actingAs($seller)->get('/sales')->assertInertia(fn ($page) => $page
            ->has('transactions.data', 1)->where('transactions.data.0.customer_name', 'Ana Reyes'));
        $this->actingAs($seller)->get('/sales?search=ana')->assertInertia(fn ($page) => $page->has('transactions.data', 1));
        $this->actingAs($seller)->get('/sales?date_from=2026-06-01')->assertInertia(fn ($page) => $page->has('transactions.data', 0));
        $this->actingAs($seller)->post('/sales', [])->assertStatus(405);
    }
}
