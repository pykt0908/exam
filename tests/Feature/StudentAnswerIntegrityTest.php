<?php

namespace Tests\Feature;

use App\Models\Choice;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentAnswerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected Subject $subject;
    protected Exam $exam;
    protected ExamAttempt $attempt;
    protected Question $choiceQuestion;
    protected Choice $choiceA;
    protected Choice $choiceB;
    protected Question $essayQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        $dept = Department::create(['name' => 'สาขาวิชาเทคโนโลยีธุรกิจดิจิทัล']);
        $classroom = Classroom::create(['name' => 'ปวช. 1/1', 'shift' => 'morning']);

        $this->student = User::create([
            'name' => 'สมชาย มั่นคง',
            'email' => 'somchai@exam.local',
            'student_code' => '67209010001',
            'password' => bcrypt('password123'),
            'role' => 'student',
            'classroom_id' => $classroom->id,
            'department_id' => $dept->id,
            'is_exam_eligible' => true,
        ]);

        $this->subject = Subject::create([
            'code' => '20204-2001',
            'name' => 'ระบบจัดการฐานข้อมูล',
            'department_id' => $dept->id,
        ]);

        $this->student->enrolledSubjects()->attach($this->subject->id, [
            'is_eligible' => true,
            'ineligible_reason' => null,
        ]);

        $this->exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'สอบกลางภาค',
            'duration_minutes' => 60,
            'is_active' => true,
            'approval_status' => 'approved',
            'total_score' => 20,
        ]);

        $this->choiceQuestion = Question::create([
            'exam_id' => $this->exam->id,
            'type' => 'choice',
            'question_text' => 'ข้อใดคือ DBMS?',
            'score' => 10,
            'sort_order' => 1,
        ]);

        $this->choiceA = Choice::create([
            'question_id' => $this->choiceQuestion->id,
            'choice_text' => 'MySQL',
            'is_correct' => true,
        ]);

        $this->choiceB = Choice::create([
            'question_id' => $this->choiceQuestion->id,
            'choice_text' => 'Photoshop',
            'is_correct' => false,
        ]);

        $this->essayQuestion = Question::create([
            'exam_id' => $this->exam->id,
            'type' => 'essay',
            'question_text' => 'จงบอกความหมายของ Primary Key แบบสั้นๆ',
            'essay_answer' => 'คีย์หลัก',
            'score' => 10,
            'sort_order' => 2,
        ]);

        $this->attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'attempt_number' => 1,
            'started_at' => now(),
            'total_questions' => 2,
            'status' => 'in_progress',
        ]);
    }

    public function test_unique_index_prevents_duplicate_rows()
    {
        StudentAnswer::create([
            'exam_attempt_id' => $this->attempt->id,
            'question_id' => $this->choiceQuestion->id,
            'choice_id' => $this->choiceA->id,
            'is_correct' => true,
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        // Attempt direct insert of duplicate attempt_id + question_id
        StudentAnswer::create([
            'exam_attempt_id' => $this->attempt->id,
            'question_id' => $this->choiceQuestion->id,
            'choice_id' => $this->choiceB->id,
            'is_correct' => false,
        ]);
    }

    public function test_save_answer_twice_keeps_single_row_with_latest_value()
    {
        $this->actingAs($this->student);

        // First answer choice A
        $res1 = $this->postJson(route('student.exam.saveAnswer', $this->attempt->id), [
            'question_id' => $this->choiceQuestion->id,
            'choice_id' => $this->choiceA->id,
        ]);
        $res1->assertStatus(200);

        // Rapid second answer choice B
        $res2 = $this->postJson(route('student.exam.saveAnswer', $this->attempt->id), [
            'question_id' => $this->choiceQuestion->id,
            'choice_id' => $this->choiceB->id,
        ]);
        $res2->assertStatus(200);

        $answers = StudentAnswer::where('exam_attempt_id', $this->attempt->id)
            ->where('question_id', $this->choiceQuestion->id)
            ->get();

        $this->assertCount(1, $answers, 'ต้องมีเพียงแถวเดียวเท่านั้น');
        $this->assertEquals($this->choiceB->id, $answers->first()->choice_id, 'ต้องได้ค่าที่ส่งล่าสุด (Choice B)');
        $this->assertFalse((bool)$answers->first()->is_correct);

        // Now test essay question with rapid updates
        $this->postJson(route('student.exam.saveAnswer', $this->attempt->id), [
            'question_id' => $this->essayQuestion->id,
            'answer_text' => 'ข้อความแรก',
        ]);
        $this->postJson(route('student.exam.saveAnswer', $this->attempt->id), [
            'question_id' => $this->essayQuestion->id,
            'answer_text' => 'คีย์หลัก',
        ]);

        $essayAnswers = StudentAnswer::where('exam_attempt_id', $this->attempt->id)
            ->where('question_id', $this->essayQuestion->id)
            ->get();

        $this->assertCount(1, $essayAnswers);
        $this->assertEquals('คีย์หลัก', $essayAnswers->first()->answer_text);
        $this->assertTrue((bool)$essayAnswers->first()->is_correct);
    }

    public function test_submit_uses_form_answers_when_autosave_missing()
    {
        $this->actingAs($this->student);

        // Assume autosave never ran due to network disconnect
        $this->assertEquals(0, StudentAnswer::where('exam_attempt_id', $this->attempt->id)->count());

        // Student hits submit button which carries on-screen form data
        $response = $this->post(route('student.exam.submit', $this->attempt->id), [
            'answers' => [
                $this->choiceQuestion->id => $this->choiceA->id,
            ],
            'text_answers' => [
                $this->essayQuestion->id => 'คีย์หลัก',
            ],
        ]);

        $response->assertRedirect(route('student.exam.result', $this->attempt->id));

        $this->attempt->refresh();
        $this->assertEquals('completed', $this->attempt->status);
        $this->assertEquals(20.0, (float)$this->attempt->score, 'ทั้งสองข้อต้องได้รับการคำนวณคะแนนเต็ม 20');
        $this->assertTrue((bool)$this->attempt->is_passed);
    }

    public function test_submit_does_not_delete_autosaved_answer_missing_from_form()
    {
        $this->actingAs($this->student);

        // Choice was saved via autosave earlier
        $this->postJson(route('student.exam.saveAnswer', $this->attempt->id), [
            'question_id' => $this->choiceQuestion->id,
            'choice_id' => $this->choiceA->id,
        ]);

        // Submit form with only essay answered, choice not in request
        $this->post(route('student.exam.submit', $this->attempt->id), [
            'answers' => [],
            'text_answers' => [
                $this->essayQuestion->id => 'คีย์หลัก',
            ],
        ]);

        $this->attempt->refresh();
        $choiceAns = StudentAnswer::where('exam_attempt_id', $this->attempt->id)
            ->where('question_id', $this->choiceQuestion->id)
            ->first();

        $this->assertNotNull($choiceAns, 'คำตอบปรนัยที่บันทึกไว้ต้องไม่สูญหาย');
        $this->assertEquals($this->choiceA->id, $choiceAns->choice_id);
        $this->assertEquals(20.0, (float)$this->attempt->score);
    }

    public function test_submit_rejects_foreign_question_and_choice_ids()
    {
        $this->actingAs($this->student);

        // Create question on another exam
        $otherExam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'ข้อสอบวิชาอื่น',
            'duration_minutes' => 60,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
        $foreignQuestion = Question::create([
            'exam_id' => $otherExam->id,
            'type' => 'choice',
            'question_text' => 'คำถามข้อสอบอื่น',
            'score' => 10,
        ]);
        $foreignChoice = Choice::create([
            'question_id' => $foreignQuestion->id,
            'choice_text' => 'Choice อื่น',
            'is_correct' => true,
        ]);

        $this->post(route('student.exam.submit', $this->attempt->id), [
            'answers' => [
                $foreignQuestion->id => $foreignChoice->id,
            ],
        ]);

        $this->assertDatabaseMissing('student_answers', [
            'exam_attempt_id' => $this->attempt->id,
            'question_id' => $foreignQuestion->id,
        ]);
    }

    public function test_double_submit_is_idempotent()
    {
        $this->actingAs($this->student);

        $this->post(route('student.exam.submit', $this->attempt->id), [
            'answers' => [$this->choiceQuestion->id => $this->choiceA->id],
        ]);

        $this->attempt->refresh();
        $firstScore = $this->attempt->score;
        $this->assertEquals('completed', $this->attempt->status);

        // Second submit request
        $res2 = $this->post(route('student.exam.submit', $this->attempt->id), [
            'answers' => [$this->choiceQuestion->id => $this->choiceB->id],
        ]);

        $res2->assertRedirect(route('student.exam.result', $this->attempt->id));

        $this->attempt->refresh();
        $this->assertEquals($firstScore, $this->attempt->score, 'คะแนนต้องคงเดิม ไม่ถูกคำนวณซ้ำหรือเปลี่ยน');
    }

    public function test_every_question_has_non_null_score_after_submit_without_essay_pending()
    {
        $this->actingAs($this->student);

        $this->post(route('student.exam.submit', $this->attempt->id), [
            'answers' => [$this->choiceQuestion->id => $this->choiceA->id],
            'text_answers' => [$this->essayQuestion->id => 'คีย์หลัก'],
        ]);

        $answers = StudentAnswer::where('exam_attempt_id', $this->attempt->id)->get();
        foreach ($answers as $ans) {
            $this->assertNotNull($ans->score_awarded, "Question {$ans->question_id} score_awarded must not be null");
        }
    }

    public function test_save_answer_blocked_when_subject_eligibility_revoked()
    {
        $this->actingAs($this->student);

        // Revoke subject eligibility
        $this->student->enrolledSubjects()->updateExistingPivot($this->subject->id, [
            'is_eligible' => false,
            'ineligible_reason' => 'ขาดสอบเก็บคะแนน',
        ]);

        $res = $this->postJson(route('student.exam.saveAnswer', $this->attempt->id), [
            'question_id' => $this->choiceQuestion->id,
            'choice_id' => $this->choiceA->id,
        ]);

        $res->assertStatus(403);
        $res->assertJson([
            'ineligible' => true,
        ]);
    }
}
