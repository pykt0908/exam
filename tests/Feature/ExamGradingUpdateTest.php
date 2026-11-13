<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamGradingUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Subject $subject;
    private Exam $exam;
    private ExamAttempt $attempt;
    private Question $essayQ1;
    private Question $essayQ2;

    protected function setUp(): void
    {
        parent::setUp();

        $classroom = Classroom::create(['name' => 'ปวช.1/1']);

        $this->teacher = User::create([
            'name' => 'อาจารย์ผู้ตรวจ',
            'teacher_code' => 'T999',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $this->student = User::create([
            'name' => 'นักศึกษา ก',
            'student_code' => 'S999',
            'password' => bcrypt('password'),
            'role' => 'student',
            'classroom_id' => $classroom->id,
            'is_exam_eligible' => true,
        ]);

        $this->subject = Subject::create([
            'code' => 'TEST999',
            'name' => 'วิชาทดสอบการตรวจ',
            'is_active' => true,
        ]);

        $this->subject->teachers()->attach($this->teacher->id);
        $this->subject->students()->attach($this->student->id);

        $this->exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'ข้อสอบอัตนัย',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'passing_percentage' => 50,
            'total_score' => 10.00,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $this->essayQ1 = Question::create([
            'exam_id' => $this->exam->id,
            'question_text' => 'ข้อเขียน 1',
            'type' => 'essay',
            'score' => 5.0,
        ]);

        $this->essayQ2 = Question::create([
            'exam_id' => $this->exam->id,
            'question_text' => 'ข้อเขียน 2',
            'type' => 'essay',
            'score' => 5.0,
        ]);

        $this->attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(30),
            'status' => 'completed',
            'grading_status' => 'pending_grading',
            'total_questions' => 2,
        ]);

        StudentAnswer::create([
            'exam_attempt_id' => $this->attempt->id,
            'question_id' => $this->essayQ1->id,
            'answer_text' => 'คำตอบข้อ 1',
            'is_correct' => false,
            'score_awarded' => null,
        ]);

        StudentAnswer::create([
            'exam_attempt_id' => $this->attempt->id,
            'question_id' => $this->essayQ2->id,
            'answer_text' => 'คำตอบข้อ 2',
            'is_correct' => false,
            'score_awarded' => null,
        ]);
    }

    public function test_teacher_can_save_decimal_scores()
    {
        $response = $this->actingAs($this->teacher)
            ->put(route('admin.grading.update', $this->attempt->id), [
                'scores' => [
                    $this->essayQ1->id => 1.25,
                    $this->essayQ2->id => 3.75,
                ],
                'comments' => [
                    $this->essayQ1->id => 'ตรวจแล้ว ให้ 1.25',
                ],
                'action' => 'save',
            ]);

        $response->assertRedirect(route('admin.grading.show', $this->attempt->id));
        $response->assertSessionHas('success');

        $this->attempt->refresh();
        $this->assertEquals('graded', $this->attempt->grading_status);
        $this->assertEquals(5.00, $this->attempt->score);
        $this->assertTrue($this->attempt->is_passed);

        $ans1 = StudentAnswer::where('exam_attempt_id', $this->attempt->id)
            ->where('question_id', $this->essayQ1->id)
            ->first();
        $this->assertEquals(1.25, $ans1->score_awarded);
        $this->assertEquals('ตรวจแล้ว ให้ 1.25', $ans1->teacher_feedback);
    }

    public function test_teacher_can_update_score_to_zero_or_clear_it()
    {
        // 1. Give initial score of 4.0
        $this->actingAs($this->teacher)
            ->put(route('admin.grading.update', $this->attempt->id), [
                'scores' => [
                    $this->essayQ1->id => 4.0,
                    $this->essayQ2->id => 4.0,
                ],
                'action' => 'save',
            ]);

        $this->attempt->refresh();
        $this->assertEquals(8.0, $this->attempt->score);

        // 2. Clear score (sending null/empty string) -> should reset to 0
        $this->actingAs($this->teacher)
            ->put(route('admin.grading.update', $this->attempt->id), [
                'scores' => [
                    $this->essayQ1->id => null,
                    $this->essayQ2->id => 2.5,
                ],
                'action' => 'save',
            ]);

        $this->attempt->refresh();
        $this->assertEquals(2.5, $this->attempt->score);

        $ans1 = StudentAnswer::where('exam_attempt_id', $this->attempt->id)
            ->where('question_id', $this->essayQ1->id)
            ->first();
        $this->assertEquals(0.0, $ans1->score_awarded);
    }

    public function test_grading_page_renders_with_step_any_and_no_required_constraint()
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('admin.grading.show', $this->attempt->id));

        $response->assertStatus(200);
        $response->assertSee('step="any"', false);
        $response->assertDontSee('step="0.1"', false);
    }
}
