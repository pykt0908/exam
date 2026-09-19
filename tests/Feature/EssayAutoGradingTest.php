<?php

namespace Tests\Feature;

use App\Models\Choice;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EssayAutoGradingTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $teacher;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $classroom = \App\Models\Classroom::create(['name' => 'ปวช.1/1']);

        $this->teacher = User::create([
            'name' => 'ครูสมชาย',
            'teacher_code' => 'T101',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $this->student = User::create([
            'name' => 'นักเรียนสมหมาย',
            'student_code' => 'S101',
            'password' => bcrypt('password'),
            'role' => 'student',
            'classroom_id' => $classroom->id,
            'is_exam_eligible' => true,
        ]);

        $this->subject = Subject::create([
            'code' => 'TEST101',
            'name' => 'วิชาทดสอบ',
            'is_active' => true,
        ]);

        $this->subject->teachers()->attach($this->teacher->id);
        $this->subject->students()->attach($this->student->id);
    }

    public function test_essay_without_teacher_answer_shows_pending_grading()
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'ข้อสอบมีข้อเขียนที่ไม่มีเฉลย',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'passing_percentage' => 60,
            'total_score' => 15.00,
            'is_active' => true,
            'approval_status' => 'approved',
            'show_score' => true,
            'show_answers' => true,
            'allow_review' => true,
        ]);

        $choiceQ = Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'ข้อปรนัย 1',
            'type' => 'choice',
            'score' => 10.0,
        ]);
        $correctChoice = Choice::create([
            'question_id' => $choiceQ->id,
            'choice_text' => 'ถูก',
            'is_correct' => true,
        ]);

        $essayQ = Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'ข้อเขียนที่ครูไม่ได้ใส่เฉลย',
            'type' => 'essay',
            'score' => 5.0,
            'essay_answer' => null, // ไม่มีเฉลย
        ]);

        $attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'total_questions' => 2,
        ]);

        // Student answers choice correctly
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $choiceQ->id,
            'choice_id' => $correctChoice->id,
            'is_correct' => true,
        ]);

        // Student answers essay
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $essayQ->id,
            'answer_text' => 'คำตอบของนักศึกษา',
            'is_correct' => false,
        ]);

        // Student submits exam
        $response = $this->actingAs($this->student)
            ->post(route('student.exam.submit', $attempt->id));

        $response->assertRedirect(route('student.exam.result', $attempt->id));

        $attempt->refresh();
        $this->assertEquals('pending_grading', $attempt->grading_status);
        $this->assertTrue($attempt->isPendingGrading());
        $this->assertNull($attempt->is_passed);

        // View result page
        $resultResponse = $this->actingAs($this->student)
            ->get(route('student.exam.result', $attempt->id));

        $resultResponse->assertStatus(200);
        $resultResponse->assertSee('รอตรวจข้อเขียน');
        $resultResponse->assertSee('คะแนนเบื้องต้นเฉพาะข้อปรนัย');
    }

    public function test_essay_with_teacher_answer_auto_grades_and_shows_essay_and_total_scores()
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'ข้อสอบมีข้อเขียนที่ครูใส่เฉลยไว้',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'passing_percentage' => 60,
            'total_score' => 15.00,
            'is_active' => true,
            'approval_status' => 'approved',
            'show_score' => true,
            'show_answers' => true,
            'allow_review' => true,
        ]);

        $choiceQ = Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'ข้อปรนัย 1',
            'type' => 'choice',
            'score' => 10.0,
        ]);
        $correctChoice = Choice::create([
            'question_id' => $choiceQ->id,
            'choice_text' => 'ถูก',
            'is_correct' => true,
        ]);

        $essayQ = Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'ข้อเขียนที่ครูใส่เฉลย',
            'type' => 'essay',
            'score' => 5.0,
            'essay_answer' => 'Model View Controller', // มีเฉลย
        ]);

        $attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'total_questions' => 2,
        ]);

        // Student answers choice correctly
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $choiceQ->id,
            'choice_id' => $correctChoice->id,
            'is_correct' => true,
        ]);

        // Student answers essay matching teacher key (case-insensitive)
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $essayQ->id,
            'answer_text' => '  model view controller  ',
            'is_correct' => true,
        ]);

        // Student submits exam
        $response = $this->actingAs($this->student)
            ->post(route('student.exam.submit', $attempt->id));

        $response->assertRedirect(route('student.exam.result', $attempt->id));

        $attempt->refresh();
        $this->assertEquals('graded', $attempt->grading_status);
        $this->assertFalse($attempt->isPendingGrading());
        $this->assertEquals(15.00, $attempt->score);
        $this->assertTrue($attempt->is_passed);

        // View result page
        $resultResponse = $this->actingAs($this->student)
            ->get(route('student.exam.result', $attempt->id));

        $resultResponse->assertStatus(200);
        $resultResponse->assertSee('คะแนนข้อปรนัย:');
        $resultResponse->assertSee('คะแนนข้อเขียน:');
        $resultResponse->assertSee('คะแนนรวมทั้งหมด:');
        $resultResponse->assertSee('ผ่าน');
        $resultResponse->assertDontSee('รอตรวจข้อเขียน (ยังไม่ระบุผลการสอบผ่าน/ไม่ผ่าน');
    }

    public function test_essay_with_teacher_answer_incorrect_scores_zero_and_shows_breakdown()
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'ข้อสอบข้อเขียนตอบไม่ตรงเฉลย',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'passing_percentage' => 60,
            'total_score' => 15.00,
            'is_active' => true,
            'approval_status' => 'approved',
            'show_score' => true,
            'show_answers' => true,
            'allow_review' => true,
        ]);

        $choiceQ = Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'ข้อปรนัย 1',
            'type' => 'choice',
            'score' => 10.0,
        ]);
        $correctChoice = Choice::create([
            'question_id' => $choiceQ->id,
            'choice_text' => 'ถูก',
            'is_correct' => true,
        ]);

        $essayQ = Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'ข้อเขียนที่ครูใส่เฉลย',
            'type' => 'essay',
            'score' => 5.0,
            'essay_answer' => 'PHP Hypertext Preprocessor',
        ]);

        $attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'total_questions' => 2,
        ]);

        // Student answers choice correctly (10 pts)
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $choiceQ->id,
            'choice_id' => $correctChoice->id,
            'is_correct' => true,
        ]);

        // Student answers essay incorrectly
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $essayQ->id,
            'answer_text' => 'Private Home Page',
            'is_correct' => false,
        ]);

        $this->actingAs($this->student)
            ->post(route('student.exam.submit', $attempt->id));

        $attempt->refresh();
        $this->assertEquals('graded', $attempt->grading_status);
        $this->assertEquals(10.00, $attempt->score); // 10 choice + 0 essay = 10 / 15 (66.67% >= 60%)
        $this->assertTrue($attempt->is_passed);

        $resultResponse = $this->actingAs($this->student)
            ->get(route('student.exam.result', $attempt->id));

        $resultResponse->assertStatus(200);
        $resultResponse->assertSee('คะแนนข้อเขียน:');
        $resultResponse->assertSee('คะแนนรวมทั้งหมด:');
        $resultResponse->assertSee('ไม่ตรงตามเฉลย');
    }
}
