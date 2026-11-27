<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\MlModel;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PredictionAndAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_prediction_without_a_trained_model_is_a_clear_conflict(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)->postJson('/prediction/run', [
            'workflow' => 'demand', 'model' => 'random_forest_regression', 'product_id' => $product->id, 'horizon_days' => 30,
        ])->assertStatus(409)->assertJsonPath('code', 'no_model');
    }

    public function test_prediction_input_is_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/prediction/run', ['workflow' => 'demand', 'model' => 'bogus', 'horizon_days' => 9999])
            ->assertStatus(422)->assertJsonValidationErrors(['model', 'product_id', 'horizon_days']);
    }

    public function test_sales_personnel_cannot_run_predictions(): void
    {
        $this->actingAs(User::factory()->salesPersonnel()->create())
            ->postJson('/prediction/run', ['workflow' => 'sales', 'model' => 'simple_linear_regression', 'horizon_days' => 30])
            ->assertForbidden();
    }

    public function test_prediction_page_hides_metrics_from_admin_but_not_instructor(): void
    {
        MlModel::factory()->active()->create(['task' => 'sales', 'algorithm' => 'simple_linear_regression']);

        $this->actingAs(User::factory()->admin()->create())->get('/prediction')
            ->assertInertia(fn ($page) => $page->component('Prediction')->where('canSeeMetrics', false));
        $this->actingAs(User::factory()->instructor()->create())->get('/prediction')
            ->assertInertia(fn ($page) => $page->component('Prediction')->where('canSeeMetrics', true));
    }

    public function test_knowledge_analysis_lists_datasets_and_models_for_the_instructor(): void
    {
        $dataset = Dataset::factory()->create(['is_system' => true]);
        MlModel::factory()->active()->create(['dataset_id' => $dataset->id]);

        $this->actingAs(User::factory()->instructor()->create())->get('/knowledge-analysis')
            ->assertInertia(fn ($page) => $page->component('KnowledgeAnalysis')->has('datasets', 1)->has('models', 1)->has('metricDescriptions'));
    }

    public function test_upload_rejects_files_with_missing_columns_and_too_few_rows(): void
    {
        $instructor = User::factory()->instructor()->create();
        $file = UploadedFile::fake()->createWithContent('bad.csv', "a,b\n1,2\n");

        $this->actingAs($instructor)->post('/knowledge-analysis/datasets', ['name' => 'Bad data', 'type' => 'sales_history', 'file' => $file])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('datasets', 0);
    }

    public function test_upload_accepts_a_valid_dataset(): void
    {
        $instructor = User::factory()->instructor()->create();
        $rows = ['transaction_number,transaction_date,product_id,product_name,quantity,unit_price,total_amount'];
        for ($i = 1; $i <= 1100; $i++) {
            $rows[] = sprintf('TX-%d,2026-01-%02d 10:00:00,1,Brake Pad,1,100.00,100.00', $i, ($i % 28) + 1);
        }
        $file = UploadedFile::fake()->createWithContent('good.csv', implode("\n", $rows));

        $this->actingAs($instructor)->post('/knowledge-analysis/datasets', ['name' => 'Good data', 'type' => 'sales_history', 'file' => $file])
            ->assertSessionHasNoErrors();

        $dataset = Dataset::firstOrFail();
        $this->assertSame(1100, $dataset->records);
        $this->assertFileExists($dataset->absolutePath());
        File::delete($dataset->absolutePath());
    }

    public function test_only_the_instructor_can_train_or_upload(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/knowledge-analysis/train', [])->assertForbidden();
        $this->actingAs($admin)->post('/knowledge-analysis/datasets', [])->assertForbidden();
    }

    public function test_activating_a_model_deactivates_the_previous_one(): void
    {
        $dataset = Dataset::factory()->create();
        $old = MlModel::factory()->active()->create(['dataset_id' => $dataset->id, 'task' => 'sales', 'algorithm' => 'simple_linear_regression']);
        $new = MlModel::factory()->create(['dataset_id' => $dataset->id, 'task' => 'sales', 'algorithm' => 'simple_linear_regression']);

        $this->actingAs(User::factory()->instructor()->create())->post("/knowledge-analysis/models/{$new->id}/activate")->assertRedirect();

        $this->assertTrue($new->fresh()->is_active);
        $this->assertFalse($old->fresh()->is_active);
    }

    public function test_both_models_need_both_trained_models(): void
    {
        MlModel::factory()->active()->create(['task' => 'sales', 'algorithm' => 'simple_linear_regression']);

        $this->actingAs(User::factory()->instructor()->create())
            ->postJson('/prediction/run', ['workflow' => 'sales', 'model' => 'both', 'horizon_days' => 30])
            ->assertStatus(409)->assertJsonPath('code', 'no_model');
    }

    public function test_prediction_accepts_the_both_option(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/prediction/run', ['workflow' => 'sales', 'model' => 'both', 'horizon_days' => 30])
            ->assertStatus(409); // validation passed; there is simply no model in this empty test database
    }

    public function test_refreshing_datasets_from_orders_needs_enough_orders(): void
    {
        $customer = User::factory()->customer()->create();
        Sale::create(['customer_id' => $customer->id, 'customer_name' => $customer->name, 'transaction_number' => 'TX-1', 'transaction_date' => now(), 'payment_method' => 'Cash', 'total_amount' => 10]);

        $this->actingAs(User::factory()->instructor()->create())->post('/knowledge-analysis/datasets/refresh')->assertSessionHas('error');
        $this->assertDatabaseCount('datasets', 0);
    }

    public function test_admin_cannot_refresh_datasets(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post('/knowledge-analysis/datasets/refresh')->assertForbidden();
    }
}
