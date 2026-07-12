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
            $table->integer('max_attempts')->default(1)->after('passing_percentage');
            $table->boolean('shuffle_questions')->default(false)->after('max_attempts');
            $table->boolean('shuffle_choices')->default(false)->after('shuffle_questions');
            $table->boolean('force_fullscreen')->default(false)->after('shuffle_choices');
            $table->integer('max_focus_escapes')->default(0)->after('force_fullscreen'); // 0 = unlimited/no warning
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['max_attempts', 'shuffle_questions', 'shuffle_choices', 'force_fullscreen', 'max_focus_escapes']);
        });
    }
};
