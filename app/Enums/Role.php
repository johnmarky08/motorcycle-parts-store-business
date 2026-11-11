<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Instructor = 'instructor';
    case InventoryClerk = 'inventory_clerk';
    case SalesPersonnel = 'sales_personnel';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Instructor => 'Instructor',
            self::InventoryClerk => 'Inventory Clerk',
            self::SalesPersonnel => 'Sales Personnel',
            self::User => 'User',
        };
    }

    /**
     * Sidebar navigation for the role. Server-side middleware enforces the same access policy.
     *
     * @return list<array{label: string, icon: string, href: string}>
     */
    public function navigation(): array
    {
        $dashboard = ['label' => 'Dashboard', 'icon' => 'grid', 'href' => '/dashboard'];
        $users = ['label' => 'User Management', 'icon' => 'users', 'href' => '/user-management'];
        $prediction = ['label' => 'Prediction', 'icon' => 'chart', 'href' => '/prediction'];

        return match ($this) {
            self::Admin => [$dashboard, $users, $prediction],
            self::Instructor => [
                $dashboard,
                $users,
                $prediction,
                ['label' => 'Knowledge Analysis', 'icon' => 'analysis', 'href' => '/knowledge-analysis'],
            ],
            self::InventoryClerk => [
                $dashboard,
                ['label' => 'Inventory Management', 'icon' => 'inventory', 'href' => '/inventory'],
            ],
            self::SalesPersonnel => [
                $dashboard,
                ['label' => 'Sales Management', 'icon' => 'sales', 'href' => '/sales'],
            ],
            self::User => [
                ['label' => 'Shop', 'icon' => 'shop', 'href' => '/shop'],
                ['label' => 'My Orders', 'icon' => 'orders', 'href' => '/orders'],
            ],
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(?array $only = null): array
    {
        $cases = array_filter(self::cases(), fn (self $r) => $only === null || in_array($r->value, $only, true));

        return array_values(array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], $cases));
    }
}
