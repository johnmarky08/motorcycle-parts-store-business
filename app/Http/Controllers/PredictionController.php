<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\PredictionRequest;
use App\Models\Category;
use App\Models\MlModel;
use App\Models\PredictionLog;
use App\Models\Product;
use App\Services\Ml\MlException;
use App\Services\Ml\MlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PredictionController extends Controller
{
    public function index(Request $request): Response
    {
        $available = MlModel::active()->get(['task', 'algorithm', 'version', 'trained_through'])
            ->map(fn ($m) => [
                'task' => $m->task, 'algorithm' => $m->algorithm, 'version' => $m->version,
                'trained_through' => $m->trained_through?->toDateString(),
            ])->all();

        return Inertia::render('Prediction', [
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku', 'stock_quantity'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'stock' => $p->stock_quantity]),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'availableModels' => $available,
            'canSeeMetrics' => $request->user()->hasRole(Role::Instructor),
        ]);
    }

    public function run(PredictionRequest $request, MlService $ml): JsonResponse
    {
        $data = $request->validated();
        $workflow = $data['workflow'];
        $task = $workflow === 'sales' ? 'sales' : 'demand';

        $algorithms = $data['model'] === 'both' ? array_keys(MlModel::ALGORITHMS) : [$data['model']];
        $models = [];
        foreach ($algorithms as $algorithm) {
            $found = MlModel::active()->where('task', $task)->where('algorithm', $algorithm)->latest('id')->first();
            if (! $found) {
                return response()->json([
                    'message' => 'No trained '.MlModel::ALGORITHMS[$algorithm].' model is available for this analysis. Ask an instructor to train one.',
                    'code' => 'no_model',
                ], 409);
            }
            $models[] = $found;
        }

        $params = [];
        $products = [];
        switch ($workflow) {
            case 'demand':
                $product = Product::findOrFail($data['product_id']);
                $params = ['product_id' => $product->id, 'horizon_days' => (int) $data['horizon_days'], 'stock' => $product->stock_quantity];
                break;
            case 'sales':
                $params = ['horizon_days' => (int) $data['horizon_days'], 'history_months' => (int) ($data['history_months'] ?? 6)];
                break;
            case 'slow':
            case 'best':
                $params = ['history_months' => (int) ($data['history_months'] ?? 6), 'period_days' => (int) ($data['period_days'] ?? 30)];
                $products = Product::query()->with('category:id,name')
                    ->when($data['category_id'] ?? null, fn ($q, $c) => $q->where('category_id', $c))
                    ->get()->map(fn ($p) => [
                        'id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'category' => $p->category?->name,
                        'stock' => $p->stock_quantity, 'price' => (float) $p->selling_price,
                    ])->all();
                if ($products === []) {
                    return response()->json(['message' => 'No products match this scope.', 'code' => 'empty_scope'], 422);
                }
                break;
        }

        try {
            $response = $ml->predict($models, $workflow, $params, $products);
        } catch (MlException $e) {
            $status = in_array($e->errorCode, ['insufficient_data', 'unknown_product'], true) ? 422 : 500;

            return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode], $status);
        }

        PredictionLog::create([
            'user_id' => $request->user()->id,
            'ml_model_id' => $models[0]->id,
            'workflow' => $workflow,
            'input' => $data,
            'result' => $this->logSummary($workflow, $response['result']),
        ]);

        $seeMetrics = $request->user()->hasRole(Role::Instructor);
        $describe = fn (MlModel $model) => [
            'algorithm' => $model->algorithm,
            'algorithm_label' => $model->algorithmLabel(),
            'version' => $model->version,
            'trained_through' => $model->trained_through?->toDateString(),
        ] + ($seeMetrics ? ['metrics' => ['mae' => $model->mae, 'rmse' => $model->rmse, 'mape' => $model->mape, 'wape' => $model->wape, 'r2' => $model->r2]] : []);

        $modelInfo = count($models) === 1
            ? $describe($models[0])
            : ['algorithm' => 'both', 'algorithm_label' => 'Both Models (Combined)', 'version' => null, 'trained_through' => $models[0]->trained_through?->toDateString(),
                'components' => array_map($describe, $models)];

        // For "both", every model's own result is returned next to the combined one.
        $components = [];
        foreach ($response['components'] ?? [] as $index => $component) {
            $components[] = ['algorithm' => $component['algorithm'], 'algorithm_label' => MlModel::ALGORITHMS[$component['algorithm']] ?? $component['algorithm'], 'result' => $component['result']];
        }

        return response()->json([
            'workflow' => $workflow,
            'generated_at' => now()->toIso8601String(),
            'model' => $modelInfo,
            'result' => $response['result'],
            'components' => $components,
        ]);
    }

    /** Keep the audit log small: store the headline numbers, not the full series. */
    private function logSummary(string $workflow, array $result): array
    {
        return match ($workflow) {
            'demand' => ['predicted_units' => $result['predicted_units'] ?? null, 'product' => $result['product']['name'] ?? null],
            'sales' => ['predicted_total' => $result['predicted_total'] ?? null],
            'slow' => ['flagged_total' => $result['flagged_total'] ?? null],
            'best' => ['top' => $result['items'][0]['name'] ?? null],
            default => [],
        };
    }
}
