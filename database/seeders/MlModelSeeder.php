<?php

namespace Database\Seeders;

use App\Models\Dataset;
use App\Models\MlModel;
use App\Models\User;
use App\Services\Ml\MlException;
use App\Services\Ml\MlService;
use Illuminate\Database\Seeder;

/**
 * Trains the four starter models (SLR and Random Forest for sales and for demand) so predictions work
 * right after seeding. Needs Python; if it is missing the seeder only warns - run `php artisan ml:train` later.
 */
class MlModelSeeder extends Seeder
{
    public function run(MlService $ml): void
    {
        if (MlModel::query()->exists()) {
            return;
        }

        $instructor = User::where('role', 'instructor')->first();
        foreach (Dataset::where('is_system', true)->get() as $dataset) {
            foreach (array_keys(MlModel::ALGORITHMS) as $algorithm) {
                try {
                    $ml->train($dataset, $algorithm, ['split' => 0.8, 'tune' => false, 'seasonal' => true], $instructor);
                    $this->command?->info("Trained {$algorithm} on {$dataset->name}");
                } catch (MlException $e) {
                    $this->command?->warn("Skipped {$algorithm} on {$dataset->name}: {$e->getMessage()}");
                    $this->command?->warn('Install Python packages (pip install -r ml/requirements.txt) and run: php artisan ml:train');

                    return;
                }
            }
        }
    }
}
