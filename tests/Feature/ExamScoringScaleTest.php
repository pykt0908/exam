<?php

namespace Tests\Feature;

use App\Models\Choice;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamScoringScaleTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Subject $subject;
    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classroom = Classroom::create(['name' => 'ปวช.2/1']);

        $this->teacher = User::create([
            'name' => 'Teacher Somchai',
            'teacher_code' => 'T2001',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $this->student = User::create([
            'name' => 'Student Somsri',
            'student_code' => 'S69001',
            'password' => bcrypt('password'),
            'role' => 'student',
            'classroom_id' => $this->classroom->id,
            'is_exam_eligible' => true,
        ]);

        $this->subject = Subject::create([
            'code' => 'TEC101',
            'name' => 'เทคโนโลยีคอมพิวเตอร์',
        ]);

        $this->subject->teachers()->attach($this->teacher->id);
        $this->subject->students()->attach($this->student->id);
    }

    public function test_proportional_score_scaling_45_questions_15_total_score()
    {
        // 45 questions, 15 total marks. 30 correct should be exactly 10.00 marks.
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'ข้อสอบ 45 ข้อ 15 คะแนน',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'passing_percentage' => 60,
            'total_score' => 15.00,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $questions = [];
        for ($i = 1; $i <= 45; $i++) {
            $q = Question::create([
                'exam_id' => $exam->id,
                'question_text' => "คำถามข้อที่ {$i}",
                'type' => 'choice',
                'score' => 1.0,
            ]);
            $correctChoice = Choice::create([
                'question_id' => $q->id,
                'choice_text' => 'ตัวเลือกที่ถูกต้อง',
                'is_correct' => true,
            ]);
            $wrongChoice = Choice::create([
                'question_id' => $q->id,
                'choice_text' => 'ตัวเลือกที่ผิด',
                'is_correct' => false,
            ]);
            $questions[] = ['q' => $q, 'correct' => $correctChoice, 'wrong' => $wrongChoice];
        }

        $attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'total_questions' => 45,
        ]);

        // Student answers first 30 correctly, last 15 incorrectly
        for ($i = 0; $i < 45; $i++) {
            $isCorrect = ($i < 30);
            $choiceId = $isCorrect ? $questions[$i]['correct']->id : $questions[$i]['wrong']->id;
            StudentAnswer::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $questions[$i]['q']->id,
                'choice_id' => $choiceId,
                'is_correct' => $isCorrect,
            ]);
        }

        $response = $this->actingAs($this->student)
            ->post(route('student.exam.submit', $attempt->id));

        $response->assertRedirect(route('student.exam.result', $attempt->id));

        $attempt->refresh();
        $this->assertEquals(10.00, $attempt->score, '30/45 questions correct must scale to exactly 10.00 marks');
        $this->assertEquals(30.00, $attempt->raw_score);
        $this->assertEquals(45.00, $attempt->total_raw_score);
        $this->assertTrue($attempt->is_passed, '10/15 is 66.67% which is >= 60% passing percentage');
        $this->assertTrue($attempt->isScaled());
    }

    public function test_proportional_score_scaling_full_score()
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'ข้อสอบ 45 ข้อ เต็ม 15 คะแนน',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'passing_percentage' => 60,
            'total_score' => 15.00,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        for ($i = 1; $i <= 45; $i++) {
            $q = Question::create([
                'exam_id' => $exam->id,
                'question_text' => "คำถามข้อที่ {$i}",
                'type' => 'choice',
                'score' => 1.0,
            ]);
            $correctChoice = Choice::create([
                'question_id' => $q->id,
                'choice_text' => 'ถูก',
                'is_correct' => true,
            ]);
        }

        $attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'total_questions' => 45,
        ]);

        foreach ($exam->questions as $q) {
            StudentAnswer::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $q->id,
                'choice_id' => $q->choices->first()->id,
                'is_correct' => true,
            ]);
        }

        $this->actingAs($this->student)
            ->post(route('student.exam.submit', $attempt->id));

        $attempt->refresh();
        $this->assertEquals(15.00, $attempt->score, '45/45 questions correct must scale to exactly 15.00 marks without loss');
        $this->assertEquals(45.00, $attempt->raw_score);
        $this->assertEquals(45.00, $attempt->total_raw_score);
        $this->assertTrue($attempt->is_passed);
    }

    public function test_mixed_choice_and_essay_grading_with_proportional_scaling()
    {
        // 40 choice (1 raw pt each = 40 pts) + 1 essay (10 raw pts) = 50 total raw pts
        // Exam target score = 20.00
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'ข้อสอบผสม ปรนัย + อัตนัย รวม 50 คะแนนดิบ สเกลเป็น 20 คะแนน',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'passing_percentage' => 50,
            'total_score' => 20.00,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        // 40 choices
        for ($i = 1; $i <= 40; $i++) {
            $q = Question::create([
                'exam_id' => $exam->id,
                'question_text' => "ปรนัย {$i}",
                'type' => 'choice',
                'score' => 1.0,
            ]);
            Choice::create([
                'question_id' => $q->id,
                'choice_text' => 'ถูก',
                'is_correct' => true,
            ]);
        }

        // 1 essay
        $essayQ = Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'อธิบายกระบวนการพัฒนาซอฟต์แวร์',
            'type' => 'essay',
            'score' => 10.0,
        ]);

        $attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'total_questions' => 41,
        ]);

        // Student gets 32 out of 40 choices correct
        $choiceQuestions = $exam->questions->where('type', 'choice')->values();
        for ($i = 0; $i < 40; $i++) {
            StudentAnswer::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $choiceQuestions[$i]->id,
                'choice_id' => $choiceQuestions[$i]->choices->first()->id,
                'is_correct' => ($i < 32),
            ]);
        }

        // Student answers essay
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $essayQ->id,
            'answer_text' => 'ขั้นตอนการพัฒนาซอฟต์แวร์ประกอบด้วย...',
            'is_correct' => false,
        ]);

        // Student submits
        $this->actingAs($this->student)
            ->post(route('student.exam.submit', $attempt->id));

        $attempt->refresh();
        $this->assertEquals('pending_grading', $attempt->grading_status);
        $this->assertNull($attempt->is_passed);

        // Teacher grades the essay: gives 8 out of 10 raw points
        // Total raw score = 32 choice + 8 essay = 40 out of 50 raw points
        // Scaled final score = (40 / 50) * 20 = 16.00
        $gradeResponse = $this->actingAs($this->teacher)
            ->put(route('admin.grading.update', $attempt->id), [
                'scores' => [
                    $essayQ->id => 8.0,
                ],
                'comments' => [
                    $essayQ->id => 'ตอบได้ดีมาก ครอบคลุมประเด็นสำคัญ',
                ],
                'action' => 'save',
            ]);

        $gradeResponse->assertRedirect();

        $attempt->refresh();
        $this->assertEquals('graded', $attempt->grading_status);
        $this->assertEquals(40.00, $attempt->raw_score);
        $this->assertEquals(50.00, $attempt->total_raw_score);
        $this->assertEquals(16.00, $attempt->score, '(40 / 50) * 20 must equal 16.00 marks');
        $this->assertTrue($attempt->is_passed);
    }

    public function test_recalculate_scores_smart_division()
    {
        // 1. Clean division: 20 questions, 100 total score -> 5.0 each
        $examClean = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => '20 ข้อ 100 คะแนน',
            'duration_minutes' => 60,
            'total_score' => 100.00,
            'approval_status' => 'draft',
        ]);
        for ($i = 1; $i <= 20; $i++) {
            Question::create([
                'exam_id' => $examClean->id,
                'question_text' => "ข้อ {$i}",
                'type' => 'choice',
                'score' => 1.0,
            ]);
        }

        $resClean = $this->actingAs($this->teacher)
            ->post(route('admin.exams.recalculate-scores', $examClean->id));

        $resClean->assertSessionHas('success');
        $resClean->assertSessionHas('recalculate_info');
        $this->assertEquals(5.0, Question::where('exam_id', $examClean->id)->first()->score);

        // 2. Unclean division: 45 questions, 15 total score -> sets 1.0 raw point each
        $examUnclean = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => '45 ข้อ 15 คะแนน',
            'duration_minutes' => 60,
            'total_score' => 15.00,
            'approval_status' => 'draft',
        ]);
        for ($i = 1; $i <= 45; $i++) {
            Question::create([
                'exam_id' => $examUnclean->id,
                'question_text' => "ข้อ {$i}",
                'type' => 'choice',
                'score' => 0.5,
            ]);
        }

        $resUnclean = $this->actingAs($this->teacher)
            ->post(route('admin.exams.recalculate-scores', $examUnclean->id));

        $resUnclean->assertSessionHas('success');
        $resUnclean->assertSessionHas('recalculate_info');
        // Unclean should reset to 1.0 raw score
        $this->assertEquals(1.0, Question::where('exam_id', $examUnclean->id)->first()->score);
    }
}
