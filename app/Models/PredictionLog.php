<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionLog extends Model
{
    protected $fillable = ['user_id', 'ml_model_id', 'workflow', 'input', 'result'];

    protected function casts(): array
    {
        return ['input' => 'array', 'result' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(MlModel::class, 'ml_model_id');
    }
}
