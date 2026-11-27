<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'description',
        'image',
        'selling_price',
        'stock_quantity',
        'reorder_threshold',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'reorder_threshold' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** Public URL of the product picture; falls back to the illustration of its category. */
    public function getImageUrlAttribute(): string
    {
        if ($this->image) {
            return '/'.ltrim($this->image, '/');
        }

        return '/images/categories/'.Str::slug($this->category?->name ?? 'general').'.svg';
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', 0);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'reorder_threshold');
    }
}
