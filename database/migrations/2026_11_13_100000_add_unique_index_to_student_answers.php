<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. สร้างตาราง archive เพื่อเก็บแถวส่วนเกินก่อนสร้าง Unique Index
        if (!Schema::hasTable('student_answers_duplicates_archive')) {
            $driver = DB::getDriverName();
            if ($driver === 'sqlite') {
                Schema::create('student_answers_duplicates_archive', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('exam_attempt_id');
                    $table->unsignedBigInteger('question_id');
                    $table->unsignedBigInteger('choice_id')->nullable();
                    $table->text('answer_text')->nullable();
                    $table->boolean('is_correct')->default(false);
                    $table->float('score_awarded')->nullable();
                    $table->text('teacher_feedback')->nullable();
                    $table->timestamps();
                });
            } else {
                DB::statement('CREATE TABLE student_answers_duplicates_archive LIKE student_answers');
            }
        }

        // 2. ค้นหากลุ่มแถวซ้ำตามคู่ (exam_attempt_id, question_id)
        $dupGroups = DB::table('student_answers')
            ->select('exam_attempt_id', 'question_id')
            ->groupBy('exam_attempt_id', 'question_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupGroups as $g) {
            $rows = DB::table('student_answers')
                ->where('exam_attempt_id', $g->exam_attempt_id)
                ->where('question_id', $g->question_id)
                ->orderByRaw('score_awarded IS NULL ASC') // ให้ความสำคัญกับแถวที่ได้รับการตรวจ/บันทึกคะแนนแล้วก่อน
                ->orderByDesc('id') // หากเท่ากัน ให้เลือกแถวล่าสุด
                ->get();

            // แถวแรก ($rows->first()) คือแถวที่เก็บไว้ (Keeper)
            // แถวที่เหลือ ($rows->slice(1)) คือแถวส่วนเกิน ให้ย้ายไป archive แล้วลบออกจากตารางหลัก
            $extraRows = $rows->slice(1);
            $extraIds = $extraRows->pluck('id')->toArray();

            if (!empty($extraIds)) {
                $archiveData = $extraRows->map(function ($r) {
                    return (array) $r;
                })->toArray();

                DB::table('student_answers_duplicates_archive')->insert($archiveData);
                DB::table('student_answers')->whereIn('id', $extraIds)->delete();
            }
        }

        // 3. สร้าง Composite Unique Index บน (exam_attempt_id, question_id) เพื่อป้องกันการเกิดแถวซ้ำ 100%
        Schema::table('student_answers', function (Blueprint $table) {
            $table->unique(['exam_attempt_id', 'question_id'], 'student_answers_attempt_question_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_answers', function (Blueprint $table) {
            $table->dropUnique('student_answers_attempt_question_unique');
        });
    }
};
