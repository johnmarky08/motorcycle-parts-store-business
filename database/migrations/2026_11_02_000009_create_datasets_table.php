<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('type', 30)->index();   // sales_history | product_demand
            $table->string('file_path');           // relative to storage/app/ml
            $table->string('original_filename')->nullable();
            $table->unsignedInteger('records')->default(0);
            $table->unsignedSmallInteger('columns')->default(0);
            $table->date('coverage_start')->nullable();
            $table->date('coverage_end')->nullable();
            $table->unsignedInteger('missing_values')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->string('status', 20)->default('validated')->index(); // validated | warning | invalid
            $table->string('target', 60)->nullable();
            $table->json('features')->nullable();
            $table->json('schema')->nullable();
            $table->json('summary')->nullable();
            $table->boolean('is_system')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('datasets');
    }
};
