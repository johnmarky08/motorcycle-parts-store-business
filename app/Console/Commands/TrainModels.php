<?php

namespace App\Console\Commands;

use App\Models\Dataset;
use App\Models\MlModel;
use App\Services\Ml\MlException;
use App\Services\Ml\MlService;
use Illuminate\Console\Command;

class TrainModels extends Command
{
    protected $signature = 'ml:train {--tune : Tune Random Forest hyperparameters (slower)} {--seasonal : Apply the seasonal index to Simple Linear Regression}';

    protected $description = 'Train and save Simple Linear Regression and Random Forest models for every built-in dataset';

    public function handle(MlService $ml): int
    {
        $datasets = Dataset::where('is_system', true)->get();
        if ($datasets->isEmpty()) {
            $this->error('No datasets found. Run: php artisan db:seed');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($datasets as $dataset) {
            foreach (array_keys(MlModel::ALGORITHMS) as $algorithm) {
                $this->line("Training {$algorithm} on {$dataset->name} ...");
                try {
                    $model = $ml->train($dataset, $algorithm, [
                        'split' => 0.8, 'tune' => (bool) $this->option('tune'), 'seasonal' => (bool) $this->option('seasonal'),
                    ], null);
                    $this->info(sprintf('  saved v%s  MAE %s  RMSE %s  MAPE %s%%  R2 %s', $model->version, $model->mae, $model->rmse, $model->mape, $model->r2));
                } catch (MlException $e) {
                    $this->error('  '.$e->getMessage());
                    $failed = true;
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
