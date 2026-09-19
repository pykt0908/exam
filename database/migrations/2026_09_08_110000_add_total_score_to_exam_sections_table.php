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
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->decimal('total_score', 8, 2)->nullable()->after('instruction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_sections', function (Blueprint $table) {
            $table->dropColumn('total_score');
        });
    }
};
