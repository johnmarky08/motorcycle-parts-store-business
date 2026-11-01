<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MlModel;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_validate_datasets(): void
    {
        $user = User::factory()->create(['role' => 'instructor', 'status' => 'active']);

        $response = $this->actingAs($user)->getJson('/analysis/datasets');

        $response->assertOk()
            ->assertJsonPath('datasets.0.valid', true)
            ->assertJsonPath('datasets.0.record_count', 1200)
            ->assertJsonCount(2, 'algorithms');
    }

    public function test_unauthorized_role_cannot_use_analysis_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'inventory_clerk', 'status' => 'active']);

        $this->actingAs($user)->getJson('/analysis/datasets')->assertForbidden();
        $this->actingAs($user)->postJson('/analysis/train', [
            'dataset_id' => 'product_demand',
            'algorithm' => 'simple_linear_regression',
        ])->assertForbidden();
    }

    public function test_authorized_user_can_train_persist_and_predict_with_a_saved_model(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $training = $this->actingAs($user)->postJson('/analysis/train', [
            'dataset_id' => 'product_demand',
            'algorithm' => 'simple_linear_regression',
        ]);

        $training->assertCreated()
            ->assertJsonMissingPath('model.artifact_path')
            ->assertJsonPath('model.record_count', 1200)
            ->assertJsonStructure(['model' => ['metrics' => ['mae', 'mse', 'rmse', 'r2']]]);

        $model = MlModel::query()->latest('id')->firstOrFail();
        $this->assertFileExists($model->artifact_path);

        $this->actingAs($user)->postJson('/analysis/predict', [
            'model_id' => $model->id,
            'features' => [
                'day_index' => 600, 'stock_quantity' => 30, 'unit_price' => 1250,
                'product_index' => 0, 'promotion' => 0, 'day_of_week' => 2, 'recent_demand' => 10,
            ],
        ])->assertOk()
            ->assertJsonPath('result.dataset_id', 'product_demand')
            ->assertJsonStructure(['result' => ['prediction', 'target', 'features']]);

        @unlink($model->artifact_path);
    }

    public function test_prediction_rejects_bad_features_and_missing_or_corrupted_artifacts(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $model = MlModel::create([
            'dataset_id' => 'product_demand',
            'algorithm' => 'simple_linear_regression',
            'target' => 'units_sold',
            'features' => ['day_index', 'stock_quantity'],
            'record_count' => 1200,
            'metrics' => ['mae' => 1, 'mse' => 1, 'rmse' => 1, 'r2' => 0],
            'artifact_path' => storage_path('app/private/ml/missing.joblib'),
            'trained_at' => now(),
        ]);

        $this->actingAs($user)->postJson('/analysis/predict', [
            'model_id' => $model->id,
            'features' => [
                'day_index' => 1, 'stock_quantity' => 2, 'unit_price' => 1250,
                'product_index' => 0, 'promotion' => 0, 'day_of_week' => 2, 'recent_demand' => 10,
            ],
        ])->assertStatus(422)->assertJsonPath('message', 'The saved model artifact is missing.');

        $corrupt = storage_path('app/private/ml/corrupt.joblib');
        file_put_contents($corrupt, 'not a model');
        $model->update(['artifact_path' => $corrupt]);
        $this->actingAs($user)->postJson('/analysis/predict', [
            'model_id' => $model->id,
            'features' => [
                'day_index' => 1, 'stock_quantity' => 2, 'unit_price' => 1250,
                'product_index' => 0, 'promotion' => 0, 'day_of_week' => 2, 'recent_demand' => 10,
            ],
        ])->assertStatus(422);
        $this->actingAs($user)->postJson('/analysis/predict', [
            'model_id' => $model->id,
            'features' => ['day_index' => 1, 'stock_quantity' => 2, 'extra' => 3],
        ])->assertStatus(422);
        @unlink($corrupt);
    }

    public function test_training_validates_dataset_and_algorithm_allowlists(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($user)->postJson('/analysis/train', [
            'dataset_id' => 'unknown',
            'algorithm' => 'simple_linear_regression',
        ])->assertUnprocessable();
        $this->actingAs($user)->postJson('/analysis/train', [
            'dataset_id' => 'product_demand',
            'algorithm' => 'unknown',
        ])->assertUnprocessable();
    }

    public function test_product_demand_features_are_mapped_from_current_business_records(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $category = Category::create(['name' => 'Brakes']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Brake Pad',
            'sku' => 'TEST-BP-001',
            'selling_price' => 1250,
            'stock_quantity' => 24,
            'reorder_threshold' => 5,
        ]);
        $sale = Sale::create([
            'user_id' => $user->id,
            'transaction_number' => 'TX-TEST-001',
            'transaction_date' => now()->subDays(5),
            'status' => 'completed',
            'payment_method' => 'cash',
            'total_amount' => 2500,
        ]);
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 2,
            'unit_price' => 1250,
            'line_total' => 2500,
        ]);

        $this->actingAs($user)->getJson('/analysis/business-features?dataset_id=product_demand&product_id=' . $product->id)
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('features.stock_quantity', 24)
            ->assertJsonPath('features.unit_price', 1250)
            ->assertJsonPath('features.recent_demand', 2)
            ->assertJsonPath('features.promotion', 0);
    }

    public function test_sales_business_features_explain_missing_schema_fields(): void
    {
        $user = User::factory()->create(['role' => 'instructor', 'status' => 'active']);

        $this->actingAs($user)->getJson('/analysis/business-features?dataset_id=sales_history')
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('features', null)
            ->assertJsonPath('message', 'Sales predictions require stored discount and customer-factor data, which the current sales schema does not provide.');
    }
}
