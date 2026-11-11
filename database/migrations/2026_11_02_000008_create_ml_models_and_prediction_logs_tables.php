<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_models', function (Blueprint $table) {
            $table->id();
            $table->string('name', 140);
            $table->string('task', 20)->index();        // sales | demand
            $table->string('algorithm', 40)->index();   // simple_linear_regression | random_forest_regression
            $table->string('version', 30);
            $table->foreignId('dataset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');                // relative to storage/app/ml
            $table->char('file_sha256', 64);
            $table->unsignedInteger('file_size')->default(0);
            $table->string('target', 60)->nullable();
            $table->json('features')->nullable();
            $table->json('params')->nullable();
            $table->json('notes')->nullable();
            $table->string('eval_protocol')->nullable();
            $table->decimal('split', 3, 2)->default(0.80);
            $table->unsignedInteger('train_points')->default(0);
            $table->unsignedInteger('test_points')->default(0);
            $table->unsignedInteger('dataset_records')->default(0);
            $table->decimal('mae', 14, 4)->nullable();
            $table->decimal('rmse', 14, 4)->nullable();
            $table->decimal('mape', 10, 4)->nullable();
            $table->decimal('wape', 10, 4)->nullable();
            $table->decimal('r2', 10, 4)->nullable();
            $table->date('trained_through')->nullable();
            $table->string('status', 20)->default('ready');
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('trained_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('trained_at')->nullable();
            $table->timestamps();

            $table->unique(['task', 'algorithm', 'version']);
        });

        Schema::create('prediction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ml_model_id')->nullable()->constrained('ml_models')->nullOnDelete();
            $table->string('workflow', 20)->index();
            $table->json('input');
            $table->json('result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediction_logs');
        Schema::dropIfExists('ml_models');
    }
};
