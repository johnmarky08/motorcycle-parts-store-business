<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('administrator_id')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('assigned_role')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['applicant_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_audits');
    }
};
