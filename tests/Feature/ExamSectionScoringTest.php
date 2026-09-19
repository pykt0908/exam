<?php

namespace Tests\Feature;

use App\Models\Choice;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamSectionScoringTest extends TestCase
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

    public function test_teacher_can_create_and_update_section_with_total_score()
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'ข้อสอบทดสอบตอน',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'passing_percentage' => 60,
            'total_score' => 20.00,
            'is_active' => true,
            'approval_status' => 'draft',
        ]);

        // Create section with total_score
        $response = $this->actingAs($this->teacher)
            ->post(route('admin.exams.sections.store', $exam->id), [
                'title' => 'ตอนที่ 1 ปรนัย',
                'instruction' => 'จงเลือกข้อที่ถูกที่สุด',
                'total_score' => 15.0,
            ]);

        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('exam_sections', [
            'exam_id' => $exam->id,
            'title' => 'ตอนที่ 1 ปรนัย',
            'total_score' => 15.0,
        ]);

        $section = ExamSection::where('exam_id', $exam->id)->first();

        // Update section total_score
        $response = $this->actingAs($this->teacher)
            ->put(route('admin.exams.sections.update', [$exam->id, $section->id]), [
                'title' => 'ตอนที่ 1 ปรนัย (แก้ไข)',
                'instruction' => 'คำชี้แจงใหม่',
                'total_score' => 16.0,
            ]);

        $response->assertJson(['success' => true]);
        $section->refresh();
        $this->assertEquals(16.0, $section->total_score);
        $this->assertEquals('ตอนที่ 1 ปรนัย (แก้ไข)', $section->title);
    }

    public function test_section_level_scoring_multiple_choice_and_essay()
    {
        // 1. Setup Exam: Total Score = 20
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'ข้อสอบปลายภาค 2 ตอน รวม 20 คะแนน',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'passing_percentage' => 60,
            'total_score' => 20.00,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        // Section 1: ปรนัย 45 ข้อ เป้าหมาย 15 คะแนน
        $section1 = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'ตอนที่ 1: ปรนัย 45 ข้อ',
            'instruction' => 'เลือกข้อที่ถูกต้องที่สุด',
            'total_score' => 15.0,
            'sort_order' => 1,
        ]);

        // Section 2: อัตนัย 1 ข้อ เป้าหมาย 5 คะแนน
        $section2 = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'ตอนที่ 2: อัตนัย 1 ข้อ',
            'instruction' => 'อธิบายกระบวนการโดยละเอียด',
            'total_score' => 5.0,
            'sort_order' => 2,
        ]);

        // Create 45 multiple choice questions in Section 1 (1 pt raw each)
        $mcqQuestions = [];
        for ($i = 1; $i <= 45; $i++) {
            $q = Question::create([
                'exam_id' => $exam->id,
                'exam_section_id' => $section1->id,
                'question_text' => "ข้อที่ {$i}",
                'type' => 'choice',
                'score' => 1.0,
            ]);
            $correct = Choice::create([
                'question_id' => $q->id,
                'choice_text' => 'ถูก',
                'is_correct' => true,
            ]);
            $wrong = Choice::create([
                'question_id' => $q->id,
                'choice_text' => 'ผิด',
                'is_correct' => false,
            ]);
            $mcqQuestions[] = ['q' => $q, 'correct' => $correct, 'wrong' => $wrong];
        }

        // Create 1 essay question in Section 2 (5 pt raw)
        $essayQ = Question::create([
            'exam_id' => $exam->id,
            'exam_section_id' => $section2->id,
            'question_text' => 'จงอธิบายสถาปัตยกรรม MVC',
            'type' => 'essay',
            'score' => 5.0,
        ]);

        // 2. Student starts exam
        $attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'total_questions' => 46,
        ]);

        // Student answers 30 MCQ correctly, 15 incorrectly
        for ($i = 0; $i < 45; $i++) {
            $isCorrect = ($i < 30);
            StudentAnswer::create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $mcqQuestions[$i]['q']->id,
                'choice_id' => $isCorrect ? $mcqQuestions[$i]['correct']->id : $mcqQuestions[$i]['wrong']->id,
                'is_correct' => $isCorrect,
            ]);
        }

        // Student writes answer for essay
        $essayAnswer = StudentAnswer::create([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $essayQ->id,
            'essay_answer' => 'MVC คือรูปแบบสถาปัตยกรรมซอฟต์แวร์...',
        ]);

        // Test sectionScore AJAX endpoint for Section 1
        $section1Response = $this->actingAs($this->student)
            ->postJson(route('student.exam.sectionScore', $attempt->id), [
                'section_id' => $section1->id,
            ]);
        $section1Response->assertJson([
            'success' => true,
            'earned' => 10.0, // 30/45 * 15 = 10.0
            'total' => 15.0,
            'raw_earned' => 30.0,
            'raw_total' => 45.0,
        ]);

        // 3. Student submits exam
        $submitResponse = $this->actingAs($this->student)
            ->post(route('student.exam.submit', $attempt->id));

        $submitResponse->assertRedirect(route('student.exam.result', $attempt->id));

        $attempt->refresh();
        $this->assertEquals('completed', $attempt->status);
        $this->assertEquals('pending_grading', $attempt->grading_status);
        $this->assertNull($attempt->is_passed, 'Pass status must remain null while waiting for essay grading');
        // Initial score with only MCQ graded: 30/45 * 15 = 10.00
        $this->assertEquals(10.00, $attempt->score);
        $this->assertEquals(30.00, $attempt->raw_score);
        $this->assertEquals(50.00, $attempt->total_raw_score);

        // 4. Teacher grades the essay: gives 4.0 out of 5.0
        $gradingResponse = $this->actingAs($this->teacher)
            ->put(route('admin.grading.update', $attempt->id), [
                'scores' => [
                    $essayQ->id => 4.0,
                ],
                'comments' => [
                    $essayQ->id => 'อธิบายได้ดี แต่ขาดตัวอย่าง',
                ],
                'action' => 'save',
            ]);

        $gradingResponse->assertRedirect(route('admin.grading.show', $attempt->id));

        $attempt->refresh();
        $this->assertEquals('graded', $attempt->grading_status);
        // Section 1: (30/45) * 15 = 10.00
        // Section 2: (4/5) * 5 = 4.00
        // Final Score: 10.00 + 4.00 = 14.00
        $this->assertEquals(14.00, $attempt->score);
        $this->assertEquals(34.00, $attempt->raw_score);
        $this->assertEquals(50.00, $attempt->total_raw_score);
        // 14 / 20 = 70% >= 60% -> passed
        $this->assertTrue($attempt->is_passed);
        $this->assertTrue($attempt->isScaled());
    }

    public function test_recalculate_scores_with_section_target_scores()
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'ข้อสอบทดสอบคำนวณคะแนนแยกตอน',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'passing_percentage' => 50,
            'total_score' => 20.00,
            'is_active' => true,
            'approval_status' => 'draft',
        ]);

        // Section 1: 10 questions, target 10 points -> cleanly divides to 1.0 each
        $section1 = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'ตอนที่ 1',
            'total_score' => 10.0,
            'sort_order' => 1,
        ]);
        for ($i = 1; $i <= 10; $i++) {
            Question::create([
                'exam_id' => $exam->id,
                'exam_section_id' => $section1->id,
                'question_text' => "ตอนที่ 1 ข้อ {$i}",
                'type' => 'choice',
                'score' => 99.0, // dummy initial score
            ]);
        }

        // Section 2: 45 questions, target 10 points -> unclean division -> sets to 1.0 raw score
        $section2 = ExamSection::create([
            'exam_id' => $exam->id,
            'title' => 'ตอนที่ 2',
            'total_score' => 10.0,
            'sort_order' => 2,
        ]);
        for ($i = 1; $i <= 45; $i++) {
            Question::create([
                'exam_id' => $exam->id,
                'exam_section_id' => $section2->id,
                'question_text' => "ตอนที่ 2 ข้อ {$i}",
                'type' => 'choice',
                'score' => 99.0, // dummy initial score
            ]);
        }

        $response = $this->actingAs($this->teacher)
            ->post(route('admin.exams.recalculate-scores', $exam->id));

        $response->assertRedirect(route('admin.exams.questions.index', $exam->id));

        // Section 1 questions should be 1.0 each
        $this->assertEquals(1.0, Question::where('exam_section_id', $section1->id)->first()->score);
        // Section 2 questions should be 1.0 raw score each (for proportional scaling)
        $this->assertEquals(1.0, Question::where('exam_section_id', $section2->id)->first()->score);
    }
}
