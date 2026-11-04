<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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

        // Extra staff so the user list and the sales history look realistic.
        $password = config('store.seed.sales_personnel.password');
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

        // Registrations waiting for an administrator decision (shown in User Management).
        $pending = [
            ['Liza Navarro', 'liza.navarro@oneprompt.test', 'sales_personnel', 'LSPU Motor Pool'],
            ['Dennis Ocampo', 'dennis.ocampo@oneprompt.test', 'inventory_clerk', 'San Pablo Parts Depot'],
        ];
        foreach ($pending as [$name, $email, $requested, $affiliation]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name, 'password' => $password, 'role' => 'sales_personnel', 'status' => 'pending',
                'requested_role' => $requested, 'affiliation' => $affiliation,
                'registration_status_token' => Str::random(48), 'submitted_at' => now()->subDays(2),
            ]);
        }
    }
}
