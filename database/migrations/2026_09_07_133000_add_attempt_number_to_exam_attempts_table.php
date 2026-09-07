<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->unsignedInteger('attempt_number')->default(1)->after('exam_id');
        });

        // Backfill attempt_number for existing attempts ordered by (user_id, exam_id, started_at, id)
        $attempts = DB::table('exam_attempts')
            ->orderBy('user_id')
            ->orderBy('exam_id')
            ->orderBy('started_at')
            ->orderBy('id')
            ->get(['id', 'user_id', 'exam_id']);

        $currentUser = null;
        $currentExam = null;
        $counter = 1;

        foreach ($attempts as $att) {
            if ($att->user_id !== $currentUser || $att->exam_id !== $currentExam) {
                $currentUser = $att->user_id;
                $currentExam = $att->exam_id;
                $counter = 1;
            }

            DB::table('exam_attempts')
                ->where('id', $att->id)
                ->update(['attempt_number' => $counter]);

            $counter++;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('attempt_number');
        });
    }
};
