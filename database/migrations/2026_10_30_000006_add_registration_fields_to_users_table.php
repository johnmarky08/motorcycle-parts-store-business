<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('requested_role')->nullable()->after('role');
            $table->string('affiliation')->nullable()->after('requested_role');
            $table->string('registration_status_token')->nullable()->unique()->after('affiliation');
            $table->timestamp('submitted_at')->nullable()->after('registration_status_token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['status', 'submitted_at']);
        });

        DB::table('users')->where('status', 'inactive')->update(['status' => 'suspended']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status', 'submitted_at']);
            $table->dropUnique(['registration_status_token']);
            $table->dropColumn(['requested_role', 'affiliation', 'registration_status_token', 'submitted_at']);
        });
    }
};
