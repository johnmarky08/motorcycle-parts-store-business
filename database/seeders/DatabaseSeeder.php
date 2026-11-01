<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $categoryNames = ['Brake System', 'Engine', 'Electrical', 'Drivetrain', 'Tires'];
        $categories = collect($categoryNames)->mapWithKeys(fn (string $name) => [$name => Category::firstOrCreate(['name' => $name])]);

        Product::updateOrCreate(['sku' => 'BP-KN-004'], [
            'category_id' => $categories['Brake System']->id,
            'name' => 'Kawasaki Ninja Brake Pad',
            'description' => 'Front brake pad set for Kawasaki Ninja models.',
            'selling_price' => 1250,
            'stock_quantity' => 128,
            'reorder_threshold' => 10,
        ]);
        Product::updateOrCreate(['sku' => 'AF-YM-021'], [
            'category_id' => $categories['Engine']->id,
            'name' => 'Yamaha MT-15 Air Filter',
            'description' => 'Replacement air filter for Yamaha MT-15.',
            'selling_price' => 680,
            'stock_quantity' => 8,
            'reorder_threshold' => 10,
        ]);
        Product::updateOrCreate(['sku' => 'DB-HC-018'], [
            'category_id' => $categories['Drivetrain']->id,
            'name' => 'Honda Click Drive Belt',
            'description' => 'CVT drive belt for Honda Click scooters.',
            'selling_price' => 1450,
            'stock_quantity' => 0,
            'reorder_threshold' => 10,
        ]);

        User::updateOrCreate(['email' => 'john@example.com'], [
            'name' => 'John Dela Cruz',
            'role' => 'admin',
            'status' => 'active',
            'password' => 'password',
        ]);

        User::updateOrCreate(['email' => 'angela@example.com'], [
            'name' => 'Angela Garcia',
            'role' => 'instructor',
            'status' => 'active',
            'password' => 'password',
        ]);

        User::updateOrCreate(['email' => 'maria@example.com'], [
            'name' => 'Maria Santos',
            'role' => 'inventory_clerk',
            'status' => 'active',
            'password' => 'password',
        ]);

        User::updateOrCreate(['email' => 'carlo@example.com'], [
            'name' => 'Carlo Reyes',
            'role' => 'sales_personnel',
            'status' => 'active',
            'password' => 'password',
        ]);
    }
}
