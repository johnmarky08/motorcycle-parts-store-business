<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        // Inventory is the inventory clerk's workspace (admins do not manage stock).
        return User::factory()->create(['role' => 'inventory_clerk', 'status' => 'active']);
    }

    private function category(): Category
    {
        return Category::create(['name' => 'Engine']);
    }

    public function test_admin_can_create_update_and_filter_products(): void
    {
        $admin = $this->admin();
        $category = $this->category();

        $this->actingAs($admin)->post('/inventory', [
            'category_id' => $category->id,
            'name' => 'Air Filter',
            'sku' => 'AF-001',
            'selling_price' => 680,
            'stock_quantity' => 8,
            'reorder_threshold' => 10,
        ])->assertRedirect();

        $product = Product::where('sku', 'AF-001')->firstOrFail();
        $this->actingAs($admin)->put("/inventory/{$product->id}", [
            'category_id' => $category->id,
            'name' => 'Premium Air Filter',
            'sku' => 'AF-001',
            'selling_price' => 720,
            'stock_quantity' => 12,
            'reorder_threshold' => 5,
        ])->assertRedirect();

        $this->actingAs($admin)->get('/inventory?search=Premium&status=in_stock')
            ->assertInertia(fn ($page) => $page
                ->where('products.total', 1)
                ->where('products.data.0.name', 'Premium Air Filter'));
    }

    public function test_inventory_listing_supports_best_selling_and_slow_moving_filters(): void
    {
        $admin = $this->admin();
        $category = $this->category();
        $best = Product::create(['category_id' => $category->id, 'name' => 'Best', 'sku' => 'BEST', 'selling_price' => 10, 'stock_quantity' => 5, 'reorder_threshold' => 1]);
        $never = Product::create(['category_id' => $category->id, 'name' => 'Never', 'sku' => 'NEVER', 'selling_price' => 10, 'stock_quantity' => 5, 'reorder_threshold' => 1]);
        $sale = Sale::create(['customer_id' => $admin->id, 'transaction_number' => 'TX-INV-1', 'transaction_date' => now(), 'payment_method' => 'Cash', 'total_amount' => 20]);
        $sale->items()->create(['product_id' => $best->id, 'product_name' => 'Best', 'sku' => 'BEST', 'quantity' => 2, 'unit_price' => 10, 'line_total' => 20]);

        $this->actingAs($admin)->get('/inventory?sort=best_selling')
            ->assertInertia(fn ($page) => $page->where('products.data.0.sku', 'BEST'));
        $this->actingAs($admin)->get('/inventory?status=slow_moving')
            ->assertInertia(fn ($page) => $page
                ->where('products.total', 1)
                ->where('products.data.0.sku', $never->sku));
    }

    public function test_stock_adjustment_is_transactional_and_history_is_available(): void
    {
        $admin = $this->admin();
        $product = Product::create(['category_id' => $this->category()->id, 'name' => 'Chain', 'sku' => 'CHAIN', 'selling_price' => 100, 'stock_quantity' => 5, 'reorder_threshold' => 1]);

        $this->actingAs($admin)->post("/inventory/{$product->id}/stock", [
            'movement_type' => 'received',
            'quantity' => 3,
            'note' => 'Supplier delivery',
        ])->assertRedirect();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 8]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity' => 3, 'balance_after' => 8]);

        $this->actingAs($admin)->post("/inventory/{$product->id}/stock", [
            'movement_type' => 'reduction',
            'quantity' => 99,
            'note' => 'Invalid reduction',
        ])->assertStatus(422);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock_quantity' => 8]);

        $this->actingAs($admin)->get("/inventory/{$product->id}/movements")
            ->assertOk()
            ->assertJsonPath('movements.0.note', 'Supplier delivery');
    }

    public function test_products_with_sales_history_cannot_be_deleted_and_roles_are_enforced(): void
    {
        $admin = $this->admin();
        $clerk = User::factory()->create(['role' => 'inventory_clerk', 'status' => 'active']);
        $sales = User::factory()->create(['role' => 'sales_personnel', 'status' => 'active']);
        $product = Product::create(['category_id' => $this->category()->id, 'name' => 'Brake Pad', 'sku' => 'BRAKE', 'selling_price' => 100, 'stock_quantity' => 5, 'reorder_threshold' => 1]);
        $sale = Sale::create(['customer_id' => $sales->id, 'transaction_number' => 'TX-INV-2', 'transaction_date' => now(), 'payment_method' => 'Cash', 'total_amount' => 100]);
        $sale->items()->create(['product_id' => $product->id, 'product_name' => 'Brake Pad', 'sku' => 'BRAKE', 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);

        $this->actingAs($sales)->get('/inventory')->assertForbidden();
        $this->actingAs($clerk)->delete("/inventory/{$product->id}")->assertStatus(422);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_invalid_product_values_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/inventory', [
            'category_id' => 999,
            'name' => 'Invalid',
            'sku' => 'INVALID',
            'selling_price' => 10,
            'stock_quantity' => -1,
            'reorder_threshold' => 1,
        ])->assertSessionHasErrors(['category_id', 'stock_quantity']);
    }
}
