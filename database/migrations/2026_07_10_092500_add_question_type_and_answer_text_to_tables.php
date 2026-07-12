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
        Schema::table('questions', function (Blueprint $table) {
            $table->string('type')->default('choice')->after('exam_id');
            $table->text('essay_answer')->nullable()->after('score');
        });

        Schema::table('student_answers', function (Blueprint $table) {
            $table->text('answer_text')->nullable()->after('choice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['type', 'essay_answer']);
        });

        Schema::table('student_answers', function (Blueprint $table) {
            $table->dropColumn('answer_text');
        });
    }
};
