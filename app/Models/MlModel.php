<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MlModel extends Model
{
    protected $hidden = [
        'artifact_path',
    ];

    protected $fillable = [
        'dataset_id',
        'algorithm',
        'target',
        'features',
        'feature_ranges',
        'record_count',
        'metrics',
        'training_metrics',
        'artifact_path',
        'trained_at',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'feature_ranges' => 'array',
            'metrics' => 'array',
            'training_metrics' => 'array',
            'trained_at' => 'datetime',
        ];
    }
}
