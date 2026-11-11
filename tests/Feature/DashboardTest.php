<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_calculates_completed_sales_inventory_and_product_rankings(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $category = Category::create(['name' => 'Engine']);
        $best = Product::create([
            'category_id' => $category->id,
            'name' => 'Best Part',
            'sku' => 'BEST-1',
            'selling_price' => 100,
            'stock_quantity' => 20,
            'reorder_threshold' => 5,
        ]);
        $low = Product::create([
            'category_id' => $category->id,
            'name' => 'Low Part',
            'sku' => 'LOW-1',
            'selling_price' => 50,
            'stock_quantity' => 3,
            'reorder_threshold' => 5,
        ]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Empty Part',
            'sku' => 'EMPTY-1',
            'selling_price' => 75,
            'stock_quantity' => 0,
            'reorder_threshold' => 5,
        ]);
        $slow = Product::create([
            'category_id' => $category->id,
            'name' => 'Slow Part',
            'sku' => 'SLOW-1',
            'selling_price' => 80,
            'stock_quantity' => 7,
            'reorder_threshold' => 2,
        ]);

        $completed = Sale::create([
            'customer_id' => $admin->id,
            'transaction_number' => 'TX-TEST-1',
            'transaction_date' => now()->subDays(3),
            'payment_method' => 'Cash',
            'total_amount' => 250,
        ]);
        $completed->items()->createMany([
            ['product_id' => $best->id, 'product_name' => $best->name, 'sku' => $best->sku, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200],
            ['product_id' => $low->id, 'product_name' => $low->name, 'sku' => $low->sku, 'quantity' => 1, 'unit_price' => 50, 'line_total' => 50],
        ]);


        $this->actingAs($admin)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('dashboard.total_sales', 250)
                ->where('dashboard.transactions', 1)
                ->where('dashboard.products', 4)
                ->where('dashboard.low_stock', 1)
                ->where('dashboard.out_of_stock', 1)
                ->where('dashboard.inventory_health.healthy', 2)
                ->where('dashboard.best_sellers.0.sku', 'BEST-1')
                ->where('dashboard.best_sellers.0.units_sold', 2)
                ->where('dashboard.slow_moving.0.sku', $slow->sku)
                ->where('dashboard.slow_moving.0.days_without_sale', null));
    }

    public function test_dashboard_is_empty_safe_and_available_to_each_role(): void
    {
        foreach (['admin', 'instructor', 'inventory_clerk', 'sales_personnel'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($user)->get('/dashboard')
                ->assertSuccessful()
                ->assertInertia(fn ($page) => $page
                    ->where('dashboard.total_sales', 0)
                    ->where('dashboard.products', 0)
                    ->where('dashboard.transactions', 0)
                    ->where('dashboard.sales_trend', [])
                    ->where('dashboard.best_sellers', [])
                    ->where('dashboard.slow_moving', []));
        }
    }
}
