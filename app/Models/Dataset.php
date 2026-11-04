<?php

namespace App\Models;

use Database\Factories\DatasetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    /** @use HasFactory<DatasetFactory> */
    use HasFactory;

    public const TYPES = [
        'sales_history' => ['task' => 'sales', 'target' => 'total_amount', 'label' => 'Sales history'],
        'product_demand' => ['task' => 'demand', 'target' => 'units_sold', 'label' => 'Product demand'],
    ];

    protected $fillable = [
        'name', 'slug', 'description', 'type', 'file_path', 'original_filename', 'records', 'columns',
        'coverage_start', 'coverage_end', 'missing_values', 'duplicate_rows', 'invalid_rows', 'status',
        'target', 'features', 'schema', 'summary', 'is_system', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'coverage_start' => 'date',
            'coverage_end' => 'date',
            'features' => 'array',
            'schema' => 'array',
            'summary' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function models(): HasMany
    {
        return $this->hasMany(MlModel::class);
    }

    public function task(): string
    {
        return self::TYPES[$this->type]['task'];
    }

    public function absolutePath(): string
    {
        return storage_path('app/ml/'.$this->file_path);
    }

    public function isTrainable(): bool
    {
        return $this->status !== 'invalid' && $this->records >= config('store.ml.min_records');
    }
}
