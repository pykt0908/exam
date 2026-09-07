<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subject_user', function (Blueprint $table) {
            $table->boolean('is_eligible')->default(true)->after('user_id');
            $table->string('ineligible_reason')->nullable()->after('is_eligible');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_exam_eligible')->default(true)->after('classroom_id');
            $table->string('ineligible_reason')->nullable()->after('is_exam_eligible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subject_user', function (Blueprint $table) {
            $table->dropColumn(['is_eligible', 'ineligible_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_exam_eligible', 'ineligible_reason']);
        });
    }
};
