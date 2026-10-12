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
        Schema::table('exam_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_attempts', 'raw_score')) {
                $table->decimal('raw_score', 8, 2)->nullable()->after('score');
            }
            if (!Schema::hasColumn('exam_attempts', 'total_raw_score')) {
                $table->decimal('total_raw_score', 8, 2)->nullable()->after('raw_score');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn(['raw_score', 'total_raw_score']);
        });
    }
};
