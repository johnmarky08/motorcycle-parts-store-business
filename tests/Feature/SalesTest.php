<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    private function setupProduct(int $stock = 5): array
    {
        $user = User::factory()->create(['role' => 'sales_personnel', 'status' => 'active']);
        $category = Category::create(['name' => 'Engine']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Spark Plug', 'sku' => 'SP-001', 'selling_price' => 100, 'stock_quantity' => $stock, 'reorder_threshold' => 1]);

        return [$user, $product];
    }

    public function test_sales_personnel_can_complete_sale_with_server_calculated_total_and_stock_movement(): void
    {
        [$user, $product] = $this->setupProduct();
        $key = (string) Str::uuid();

        $this->actingAs($user)->post('/sales', [
            'idempotency_key' => $key,
            'payment_method' => 'Cash',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertRedirect();

        $this->assertDatabaseHas('sales', ['idempotency_key' => $key, 'total_amount' => 200, 'status' => 'completed']);
        $this->assertDatabaseHas('sale_items', ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 3]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'movement_type' => 'sale', 'quantity' => -2, 'balance_after' => 3]);
    }

    public function test_insufficient_stock_rolls_back_and_duplicate_key_is_rejected(): void
    {
        [$user, $product] = $this->setupProduct(1);
        $key = (string) Str::uuid();
        $payload = ['idempotency_key' => $key, 'payment_method' => 'Cash', 'items' => [['product_id' => $product->id, 'quantity' => 2]]];

        $this->actingAs($user)->post('/sales', $payload)->assertStatus(422);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 1]);

        $payload['items'][0]['quantity'] = 1;
        $this->actingAs($user)->post('/sales', $payload)->assertRedirect();
        $this->actingAs($user)->post('/sales', $payload)->assertStatus(422);
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_sales_history_filters_and_detail_are_protected(): void
    {
        [$user, $product] = $this->setupProduct();
        $sale = Sale::create(['user_id' => $user->id, 'transaction_number' => 'TX-SALE-1', 'idempotency_key' => (string) Str::uuid(), 'transaction_date' => now(), 'status' => 'completed', 'payment_method' => 'Cash', 'total_amount' => 100]);
        $sale->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);
        $other = User::factory()->create(['role' => 'instructor', 'status' => 'active']);

        $this->actingAs($user)->get('/sales?search=TX-SALE-1')->assertInertia(fn ($page) => $page->where('transactions.total', 1));
        $this->actingAs($user)->get("/sales/{$sale->id}")->assertSuccessful();
        $this->actingAs($other)->get('/sales')->assertForbidden();
        $this->actingAs($other)->get("/sales/{$sale->id}")->assertForbidden();
    }
}
