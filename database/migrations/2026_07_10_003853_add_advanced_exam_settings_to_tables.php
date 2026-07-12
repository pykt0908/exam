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
            $table->boolean('show_score')->default(true)->after('passcode');
            $table->boolean('show_answers')->default(true)->after('show_score');
            $table->boolean('allow_review')->default(true)->after('show_answers');
            $table->boolean('allow_reedit')->default(false)->after('allow_review');
            $table->integer('max_edits')->default(0)->after('allow_reedit');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->integer('edits_count')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['show_score', 'show_answers', 'allow_review', 'allow_reedit', 'max_edits']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('edits_count');
        });
    }
};
