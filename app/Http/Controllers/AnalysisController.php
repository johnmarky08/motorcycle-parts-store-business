<?php

namespace App\Http\Controllers;

use App\Models\MlModel;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessStartFailedException;

class AnalysisController extends Controller
{
    private const DATASETS = [
        'product_demand' => [
            'name' => 'Product Demand Dataset',
            'description' => 'Synthetic daily demand data generated for model development; not actual store transactions.',
            'target' => 'units_sold',
            'features' => ['day_index', 'stock_quantity', 'unit_price', 'product_index', 'promotion', 'day_of_week', 'recent_demand'],
        ],
        'sales_history' => [
            'name' => 'Sales History Dataset',
            'description' => 'Synthetic sales history generated for model development; not actual store transactions.',
            'target' => 'total_amount',
            'features' => ['day_index', 'product_index', 'promotion', 'day_of_week', 'discount_rate', 'customer_factor'],
        ],
    ];

    public function datasets(): JsonResponse
    {
        return response()->json([
            'datasets' => collect(self::DATASETS)->map(function ($dataset, $id) {
                $result = $this->runPython(['validate', '--dataset', $id]);
                return [...$dataset, 'id' => $id, ...$result];
            })->values(),
            'algorithms' => [
                ['id' => 'simple_linear_regression', 'name' => 'Simple Linear Regression'],
                ['id' => 'random_forest_regression', 'name' => 'Random Forest Regression'],
            ],
        ]);
    }

    public function models(): JsonResponse
    {
        return response()->json(['models' => MlModel::query()->latest('trained_at')->get()]);
    }

    public function businessFeatures(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dataset_id' => ['required', 'in:product_demand,sales_history'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $products = Product::query()->orderBy('id')->get(['id', 'name', 'sku', 'selling_price', 'stock_quantity']);
        if ($data['dataset_id'] === 'sales_history') {
            return response()->json([
                'dataset_id' => $data['dataset_id'],
                'products' => $products,
                'features' => null,
                'available' => false,
                'message' => 'Sales predictions require stored discount and customer-factor data, which the current sales schema does not provide.',
            ]);
        }

        if (! isset($data['product_id'])) {
            return response()->json([
                'dataset_id' => $data['dataset_id'],
                'products' => $products,
                'features' => null,
                'available' => true,
                'message' => null,
            ]);
        }

        $product = $products->firstWhere('id', $data['product_id']);
        $productIndex = $products->search(fn (Product $item) => $item->id === $product->id);
        if ($productIndex > 5) {
            throw ValidationException::withMessages([
                'product_id' => 'This product cannot be mapped to the trained model because its product index is outside the model range.',
            ]);
        }

        $recentDemand = (float) $product->saleItems()
            ->whereHas('sale', fn ($query) => $query
                ->where('status', 'completed')
                ->where('transaction_date', '>=', now()->subDays(30)))
            ->sum('quantity');

        return response()->json([
            'dataset_id' => $data['dataset_id'],
            'products' => $products,
            'available' => true,
            'message' => null,
            'features' => [
                'day_index' => max(1199, now()->diffInDays(\Carbon\Carbon::create(2026, 4, 14))),
                'stock_quantity' => (int) $product->stock_quantity,
                'unit_price' => (float) $product->selling_price,
                'product_index' => $productIndex,
                'promotion' => 0,
                'day_of_week' => now()->dayOfWeekIso - 1,
                'recent_demand' => $recentDemand,
            ],
        ]);
    }

    public function insights(): JsonResponse
    {
        $completed = fn ($query) => $query->where('sales.status', 'completed');
        $best = Product::query()
            ->leftJoin('sale_items', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where($completed)
            ->select('products.id', 'products.name', 'products.sku')
            ->selectRaw('COALESCE(SUM(sale_items.quantity), 0) as units_sold')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('units_sold')
            ->limit(10)->get();
        $slow = Product::query()
            ->leftJoin('sale_items', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('sales', function ($join) {
                $join->on('sale_items.sale_id', '=', 'sales.id')
                    ->where('sales.status', 'completed');
            })
            ->where('products.stock_quantity', '>', 0)
            ->select('products.id', 'products.name', 'products.sku', 'products.stock_quantity')
            ->selectRaw('MAX(sales.transaction_date) as last_sale_at')
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.stock_quantity')
            ->havingRaw('MAX(sales.transaction_date) IS NULL OR MAX(sales.transaction_date) < ?', [now()->subDays(90)])
            ->orderBy('last_sale_at')
            ->limit(10)->get();
        return response()->json(['best_selling' => $best, 'slow_moving' => $slow]);
    }

    public function train(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dataset_id' => ['required', 'in:product_demand,sales_history'],
            'algorithm' => ['required', 'in:simple_linear_regression,random_forest_regression'],
        ]);
        $existing = MlModel::query()->where($data)->latest('trained_at')->first();
        $artifact = storage_path('app/private/ml/' . $data['dataset_id'] . '-' . $data['algorithm'] . '-' . now()->format('YmdHis') . '.joblib');
        $result = $this->runPython(['train', '--dataset', $data['dataset_id'], '--algorithm', $data['algorithm'], '--artifact', $artifact]);
        if ($existing && ($result['metrics']['r2'] ?? -PHP_FLOAT_MAX) < ($existing->metrics['r2'] ?? -PHP_FLOAT_MAX)) {
            @unlink($artifact);
            return response()->json(['message' => 'Training completed, but the existing model has better R².', 'model' => $existing], 422);
        }
        $model = MlModel::create([
            ...$result,
            'artifact_path' => $artifact,
        ]);
        return response()->json(['model' => $model], 201);
    }

    public function predict(Request $request): JsonResponse
    {
        $data = $request->validate([
            'model_id' => ['required', 'integer', 'exists:ml_models,id'],
            'features' => ['required', 'array'],
        ]);
        $model = MlModel::findOrFail($data['model_id']);
        abort_unless(is_file($model->artifact_path), 422, 'The saved model artifact is missing.');
        $features = $this->validatedFeatures($model->dataset_id, $data['features']);
        return response()->json([
            'model' => $model,
            'result' => $this->runPython([
                'predict',
                '--dataset', $model->dataset_id,
                '--artifact', $model->artifact_path,
                '--features', json_encode($features, JSON_THROW_ON_ERROR),
            ]),
        ]);
    }

    private function validatedFeatures(string $datasetId, array $features): array
    {
        $expected = match ($datasetId) {
            'product_demand' => self::DATASETS['product_demand']['features'],
            'sales_history' => self::DATASETS['sales_history']['features'],
            default => abort(422, 'Unsupported model dataset.'),
        };
        $received = array_keys($features);
        $missing = array_values(array_diff($expected, $received));
        $unexpected = array_values(array_diff($received, $expected));
        if ($missing || $unexpected) {
            abort(422, 'Prediction features must exactly match the selected dataset.');
        }
        foreach ($expected as $feature) {
            if (! is_int($features[$feature]) && ! is_float($features[$feature]) && ! (is_string($features[$feature]) && is_numeric($features[$feature]))) {
                abort(422, 'Prediction features must be numeric.');
            }
        }
        $numeric = array_map('floatval', $features);
        $bounds = [
            'day_index' => [0, null], 'stock_quantity' => [0, null], 'unit_price' => [0, null],
            'product_index' => [0, 5], 'promotion' => [0, 1], 'day_of_week' => [0, 6],
            'recent_demand' => [0, null], 'discount_rate' => [0, 1], 'customer_factor' => [0, null],
        ];
        foreach ($numeric as $feature => $value) {
            [$minimum, $maximum] = $bounds[$feature];
            if (($minimum !== null && $value < $minimum) || ($maximum !== null && $value > $maximum)) {
                abort(422, "Prediction feature {$feature} is outside its valid range.");
            }
        }
        return $numeric;
    }

    private function runPython(array $arguments): array
    {
        $python = env('ML_PYTHON', 'python');
        $process = new Process([$python, base_path('ml/pipeline.py'), ...$arguments], base_path());
        $process->setTimeout(120);
        if (PHP_OS_FAMILY === 'Windows') {
            $process->setOptions(['create_new_console' => true]);
            $process->setEnv([
                'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
                'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
            ]);
        }
        try {
            $process->run();
        } catch (ProcessStartFailedException $exception) {
            Log::error('ML process could not start', [
                'python' => $python,
                'arguments' => $arguments,
                'error' => $exception->getMessage(),
            ]);
            abort(503, 'The analysis service is unavailable.');
        }

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());
        $output = json_decode($stdout, true);
        if (! $process->isSuccessful()) {
            Log::error('ML process failed', [
                'python' => $python,
                'exit_code' => $process->getExitCode(),
                'arguments' => $arguments,
                'stderr' => $this->sanitizeProcessOutput($stderr),
                'stdout' => $this->sanitizeProcessOutput($stdout),
            ]);
            abort(422, is_array($output) && isset($output['error']) ? $output['error'] : 'The analysis request could not be completed.');
        }
        if (! is_array($output)) {
            Log::error('ML process returned invalid JSON', [
                'python' => $python,
                'exit_code' => $process->getExitCode(),
                'arguments' => $arguments,
                'stdout' => $this->sanitizeProcessOutput($stdout),
                'stderr' => $this->sanitizeProcessOutput($stderr),
            ]);
            abort(503, 'The analysis service returned an invalid response.');
        }
        return $output;
    }

    private function sanitizeProcessOutput(string $output): string
    {
        return str_replace(base_path(), '[project]', mb_substr($output, 0, 2000));
    }
}
