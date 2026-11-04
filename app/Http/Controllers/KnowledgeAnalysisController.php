<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrainModelRequest;
use App\Http\Requests\UploadDatasetRequest;
use App\Models\Dataset;
use App\Models\MlModel;
use App\Services\Datasets\DatasetException;
use App\Services\Datasets\DatasetInspector;
use App\Services\Datasets\DatasetRegistry;
use App\Services\Ml\MlException;
use App\Services\Ml\MlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeAnalysisController extends Controller
{
    public function index(Request $request, DatasetInspector $inspector): Response
    {
        $request->validate([
            'dataset' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $datasets = Dataset::orderByDesc('is_system')->orderBy('name')->get();
        $selected = $datasets->firstWhere('id', (int) $request->query('dataset')) ?? $datasets->first();

        $preview = null;
        if ($selected && is_file($selected->absolutePath())) {
            $preview = $inspector->preview($selected->absolutePath(), (int) $request->query('page', 1), 15, (string) $request->query('q', ''));
        }

        return Inertia::render('KnowledgeAnalysis', [
            'datasets' => $datasets->map(fn (Dataset $d) => $this->datasetPayload($d))->values(),
            'selectedDatasetId' => $selected?->id,
            'preview' => $preview,
            'search' => (string) $request->query('q', ''),
            'models' => MlModel::with('dataset:id,name')->latest('trained_at')->latest('id')->limit(100)->get()
                ->map(fn (MlModel $m) => $this->modelPayload($m))->values(),
            'metricDescriptions' => [
                'mae' => 'Mean Absolute Error: the average size of the prediction mistakes, in the same unit as the target (₱ for sales, units for demand). Lower is better.',
                'rmse' => 'Root Mean Squared Error: like MAE but large mistakes weigh more. Always at least as big as MAE; a big gap means a few large misses.',
                'mape' => 'Mean Absolute Percentage Error: the average mistake as a percent of the actual value. Only periods with a non-zero actual value are counted.',
                'wape' => 'Weighted Absolute Percentage Error: total absolute error divided by total actual value. More stable than MAPE when many actual values are small.',
                'r2' => 'R²: share of the variation explained by the model (1 is perfect, 0 is no better than predicting the average, negative is worse than the average).',
            ],
        ]);
    }

    public function train(TrainModelRequest $request, MlService $ml): RedirectResponse
    {
        $data = $request->validated();
        $dataset = Dataset::findOrFail($data['dataset_id']);

        try {
            $model = $ml->train($dataset, $data['algorithm'], [
                'split' => (float) $data['split'],
                'tune' => $request->boolean('tune'),
                'seasonal' => $request->boolean('seasonal'),
            ], $request->user());
        } catch (MlException $e) {
            return back()->with('error', $e->getMessage());
        }

        $mape = $model->mape === null ? 'n/a' : number_format($model->mape, 1).'%';

        return back()->with('status', "{$model->algorithmLabel()} trained and saved as v{$model->version} (MAPE {$mape}).");
    }

    public function upload(UploadDatasetRequest $request, DatasetRegistry $registry): RedirectResponse
    {
        $data = $request->validated();

        try {
            $dataset = $registry->registerUpload($request->file('file'), $data['type'], $data['name'], $request->user());
        } catch (DatasetException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect('/knowledge-analysis?dataset='.$dataset->id)->with('status', "Dataset \"{$dataset->name}\" was validated and added ({$dataset->records} records).");
    }

    public function destroyDataset(Dataset $dataset, DatasetRegistry $registry): RedirectResponse
    {
        try {
            $registry->delete($dataset);
        } catch (DatasetException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect('/knowledge-analysis')->with('status', 'Dataset removed.');
    }

    public function activate(Request $request, MlModel $mlModel): RedirectResponse
    {
        DB::transaction(function () use ($mlModel) {
            MlModel::where('task', $mlModel->task)->where('algorithm', $mlModel->algorithm)->update(['is_active' => false]);
            $mlModel->update(['is_active' => true]);
        });
        Log::channel('security')->info('ml.model_activated', ['model_id' => $mlModel->id, 'user_id' => $request->user()->id]);

        return back()->with('status', "{$mlModel->name} v{$mlModel->version} is now the model used for predictions.");
    }

    /** @return array<string, mixed> */
    private function datasetPayload(Dataset $d): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'description' => $d->description,
            'type' => $d->type,
            'type_label' => Dataset::TYPES[$d->type]['label'] ?? $d->type,
            'task' => $d->task(),
            'records' => $d->records,
            'columns' => $d->columns,
            'coverage' => $d->coverage_start && $d->coverage_end ? $d->coverage_start->format('M j, Y').' – '.$d->coverage_end->format('M j, Y') : 'n/a',
            'status' => $d->status,
            'missing' => $d->missing_values,
            'duplicates' => $d->duplicate_rows,
            'invalid' => $d->invalid_rows,
            'target' => $d->target,
            'features' => $d->features ?? [],
            'schema' => $d->schema ?? [],
            'summary' => $d->summary,
            'is_system' => $d->is_system,
            'trainable' => $d->isTrainable(),
            'filename' => $d->original_filename,
        ];
    }

    /** @return array<string, mixed> */
    private function modelPayload(MlModel $m): array
    {
        return [
            'id' => $m->id,
            'name' => $m->name,
            'task' => $m->task,
            'algorithm' => $m->algorithm,
            'algorithm_label' => $m->algorithmLabel(),
            'version' => $m->version,
            'dataset' => $m->dataset?->name ?? 'Removed dataset',
            'trained_at' => $m->trained_at?->format('M j, Y g:i A'),
            'status' => $m->status,
            'is_active' => $m->is_active,
            'mae' => $m->mae, 'rmse' => $m->rmse, 'mape' => $m->mape, 'wape' => $m->wape, 'r2' => $m->r2,
            'split' => $m->split,
            'train_points' => $m->train_points,
            'test_points' => $m->test_points,
            'dataset_records' => $m->dataset_records,
            'features' => $m->features ?? [],
            'params' => $m->params ?? [],
            'notes' => $m->notes ?? [],
            'target' => $m->target,
            'eval_protocol' => $m->eval_protocol,
            'trained_through' => $m->trained_through?->format('M j, Y'),
        ];
    }
}
