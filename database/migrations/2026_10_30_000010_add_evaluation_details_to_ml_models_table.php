<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ml_models', function (Blueprint $table) {
            $table->json('training_metrics')->nullable()->after('metrics');
            $table->json('feature_ranges')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('ml_models', function (Blueprint $table) {
            $table->dropColumn(['training_metrics', 'feature_ranges']);
        });
    }
};
