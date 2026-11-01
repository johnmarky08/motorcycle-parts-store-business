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
            $table->string('dataset_id');
            $table->string('algorithm');
            $table->string('target');
            $table->json('features');
            $table->unsignedInteger('record_count');
            $table->json('metrics');
            $table->string('artifact_path');
            $table->timestamp('trained_at');
            $table->timestamps();
            $table->index(['dataset_id', 'algorithm']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ml_models');
    }
};
