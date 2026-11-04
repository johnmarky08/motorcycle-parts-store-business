<?php

namespace App\Models;

use Database\Factories\MlModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MlModel extends Model
{
    /** @use HasFactory<MlModelFactory> */
    use HasFactory;

    public const ALGORITHMS = [
        'simple_linear_regression' => 'Simple Linear Regression',
        'random_forest_regression' => 'Random Forest Regression',
    ];

    protected $fillable = [
        'name', 'task', 'algorithm', 'version', 'dataset_id', 'file_path', 'file_sha256', 'file_size', 'target',
        'features', 'params', 'notes', 'eval_protocol', 'split', 'train_points', 'test_points', 'dataset_records',
        'mae', 'rmse', 'mape', 'wape', 'r2', 'trained_through', 'status', 'is_active', 'trained_by', 'trained_at',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'params' => 'array',
            'notes' => 'array',
            'split' => 'float',
            'mae' => 'float',
            'rmse' => 'float',
            'mape' => 'float',
            'wape' => 'float',
            'r2' => 'float',
            'is_active' => 'boolean',
            'trained_through' => 'date',
            'trained_at' => 'datetime',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trained_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'ready');
    }

    public function algorithmLabel(): string
    {
        return self::ALGORITHMS[$this->algorithm] ?? $this->algorithm;
    }

    public function absolutePath(): string
    {
        return storage_path('app/ml/'.$this->file_path);
    }
}
