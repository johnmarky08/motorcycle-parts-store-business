<?php

namespace App\Services\Datasets;

use App\Models\Dataset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DatasetRegistry
{
    public function __construct(private DatasetInspector $inspector)
    {
    }

    private function dir(): string
    {
        return storage_path('app/ml/datasets');
    }

    /** Copies a dataset shipped with the project into protected storage and records it. */
    public function registerSystemDataset(string $source, string $type, string $name, string $description): ?Dataset
    {
        if (! is_file($source)) {
            return null;
        }
        File::ensureDirectoryExists($this->dir());
        $target = $this->dir().'/'.basename($source);
        File::copy($source, $target);

        $stats = $this->inspector->inspect($target, $type);

        return Dataset::updateOrCreate(['slug' => Str::slug($name)], [
            'name' => $name,
            'description' => $description,
            'type' => $type,
            'file_path' => 'datasets/'.basename($source),
            'original_filename' => basename($source),
            'is_system' => true,
        ] + $stats);
    }

    /** @throws DatasetException */
    public function registerUpload(UploadedFile $file, string $type, string $name, ?User $user): Dataset
    {
        File::ensureDirectoryExists($this->dir().'/uploads');
        $stored = 'uploads/'.Str::uuid()->toString().'.csv';
        $absolute = $this->dir().'/'.$stored;
        File::put($absolute, file_get_contents($file->getRealPath()));

        try {
            $stats = $this->inspector->inspect($absolute, $type);
        } catch (DatasetException $e) {
            File::delete($absolute);
            throw $e;
        }

        $dataset = Dataset::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => 'Uploaded '.Dataset::TYPES[$type]['label'].' dataset.',
            'type' => $type,
            'file_path' => 'datasets/'.$stored,
            'original_filename' => Str::limit(preg_replace('/[^\w.\- ]+/u', '', $file->getClientOriginalName()), 120, ''),
            'is_system' => false,
            'uploaded_by' => $user?->id,
        ] + $stats);

        Log::channel('security')->info('dataset.uploaded', ['dataset_id' => $dataset->id, 'records' => $dataset->records, 'user_id' => $user?->id]);

        return $dataset;
    }

    public function delete(Dataset $dataset): void
    {
        if ($dataset->is_system) {
            throw new DatasetException('The built-in datasets cannot be removed.');
        }
        File::delete($dataset->absolutePath());
        $dataset->delete();
    }
}
