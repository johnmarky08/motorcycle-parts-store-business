<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Default accounts: credentials come from .env (SEED_*), with fallbacks in config/store.php.
        foreach (config('store.seed') as $role => $account) {
            User::updateOrCreate(
                ['email' => strtolower($account['email'])],
                [
                    'name' => $account['name'],
                    'password' => $account['password'],
                    'role' => $role,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
        }

        // Extra staff so the user list looks realistic.
        // Hash each distinct password once (bcrypt is deliberately slow); an already hashed value is stored as is.
        $password = Hash::make(config('store.seed.sales_personnel.password'));
        $staff = [
            ['Angela Garcia', 'angela.garcia@oneprompt.test', 'sales_personnel', 'active'],
            ['Paolo Mendoza', 'paolo.mendoza@oneprompt.test', 'inventory_clerk', 'active'],
            ['Jessa Villanueva', 'jessa.villanueva@oneprompt.test', 'sales_personnel', 'active'],
            ['Ramon Bautista', 'ramon.bautista@oneprompt.test', 'inventory_clerk', 'suspended'],
        ];
        foreach ($staff as [$name, $email, $role, $status]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name, 'password' => $password, 'role' => $role, 'status' => $status, 'email_verified_at' => now(),
            ]);
        }

        // Customers: their names appear on the seeded sales history and they can sign in to shop.
        $first = ['Juan', 'Maria', 'Jose', 'Ana', 'Mark', 'Grace', 'Paolo', 'Kristine', 'Ramon', 'Liza', 'Dennis', 'Carmela', 'Rico', 'Bea', 'Noel', 'Joy'];
        $last = ['Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Mendoza', 'Bautista', 'Ocampo', 'Navarro', 'Aquino', 'Castillo', 'Ramos', 'Torres'];
        $customerPassword = Hash::make(config('store.seed.user.password'));
        for ($i = 0; $i < 48; $i++) {
            $name = $first[$i % count($first)].' '.$last[intdiv($i * 5 + $i % 7, 4) % count($last)];
            $email = strtolower(str_replace(' ', '.', $name)).($i >= 16 ? $i : '').'@customer.oneprompt.test';
            User::updateOrCreate(['email' => $email], [
                'name' => $name, 'password' => $customerPassword, 'role' => 'user', 'status' => 'active', 'email_verified_at' => now(),
            ]);
        }
    }
}
