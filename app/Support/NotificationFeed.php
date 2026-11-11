<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\MlModel;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;

/** Builds the header notification list from live data (nothing is stored). */
class NotificationFeed
{
    /** @return list<array{id: string, type: string, title: string, description: string, timestamp: string}> */
    public static function for(User $user): array
    {
        $items = [];

        if ($user->hasRole(Role::Admin, Role::InventoryClerk)) {
            $out = Product::query()->outOfStock()->orderBy('name')->limit(3)->get(['id', 'name', 'sku']);
            foreach ($out as $product) {
                $items[] = [
                    'id' => "out:{$product->id}",
                    'type' => 'critical',
                    'title' => 'Product Is Out Of Stock',
                    'description' => "{$product->name} · {$product->sku} has no remaining stock.",
                    'timestamp' => 'Needs Restocking',
                ];
            }
            $low = Product::query()->lowStock()->count();
            if ($low > 0) {
                $items[] = [
                    'id' => "low:{$low}",
                    'type' => 'stock',
                    'title' => 'Low-Stock Products Need Attention',
                    'description' => $low.' '.($low === 1 ? 'product is' : 'products are').' at or below the reorder threshold.',
                    'timestamp' => 'Current',
                ];
            }
        }

        if ($user->hasRole(Role::SalesPersonnel)) {
            foreach (Sale::latest('transaction_date')->limit(3)->get(['id', 'transaction_number', 'customer_name', 'total_amount', 'transaction_date']) as $sale) {
                $items[] = [
                    'id' => "sale:{$sale->id}",
                    'type' => 'sales',
                    'title' => 'New Order Received',
                    'description' => "{$sale->transaction_number} · {$sale->customer_name} · ₱".number_format((float) $sale->total_amount, 2),
                    'timestamp' => $sale->transaction_date->diffForHumans(),
                ];
            }
        }

        if ($user->hasRole(Role::Instructor)) {
            foreach (MlModel::latest('trained_at')->limit(2)->get() as $model) {
                $items[] = [
                    'id' => "model:{$model->id}",
                    'type' => 'sales',
                    'title' => 'Model Ready',
                    'description' => $model->name.' v'.$model->version.' is available for predictions.',
                    'timestamp' => $model->trained_at?->diffForHumans() ?? '',
                ];
            }
        }

        return $items;
    }
}
