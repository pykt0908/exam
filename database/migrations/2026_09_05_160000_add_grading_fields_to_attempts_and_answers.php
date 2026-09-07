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
        Schema::table('student_answers', function (Blueprint $table) {
            $table->decimal('score_awarded', 8, 2)->nullable()->after('is_correct');
            $table->text('teacher_feedback')->nullable()->after('score_awarded');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->string('grading_status')->default('graded')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_answers', function (Blueprint $table) {
            $table->dropColumn(['score_awarded', 'teacher_feedback']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('grading_status');
        });
    }
};
