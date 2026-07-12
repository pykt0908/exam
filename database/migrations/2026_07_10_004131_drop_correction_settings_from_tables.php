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
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['allow_reedit', 'max_edits']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('edits_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->boolean('allow_reedit')->default(false)->after('allow_review');
            $table->integer('max_edits')->default(0)->after('allow_reedit');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->integer('edits_count')->default(0)->after('status');
        });
    }
};
