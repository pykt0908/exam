<?php

namespace Tests\Feature;

use App\Models\Choice;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Subject $subject;
    private Classroom $classroom;
    private Exam $exam;
    private ExamSection $section1;
    private ExamSection $section2;

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

        $this->exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'ข้อสอบทดสอบการจัดลำดับ',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'passing_percentage' => 50,
            'total_score' => 20.00,
            'is_active' => true,
            'approval_status' => 'draft',
            'shuffle_questions' => false,
        ]);

        $this->section1 = ExamSection::create([
            'exam_id' => $this->exam->id,
            'title' => 'ตอนที่ 1',
            'sort_order' => 1,
        ]);

        $this->section2 = ExamSection::create([
            'exam_id' => $this->exam->id,
            'title' => 'ตอนที่ 2',
            'sort_order' => 2,
        ]);
    }

    public function test_questions_auto_assign_sequential_sort_order_on_creation()
    {
        $q1 = $this->actingAs($this->teacher)->postJson(route('admin.exams.questions.store', $this->exam->id), [
            'question_text' => 'ข้อแรก',
            'score' => 1.0,
            'type' => 'choice',
            'exam_section_id' => $this->section1->id,
            'choices' => ['A', 'B'],
            'correct_choice' => 0,
        ]);
        $q1->assertJson(['success' => true]);

        $q2 = $this->actingAs($this->teacher)->postJson(route('admin.exams.questions.store', $this->exam->id), [
            'question_text' => 'ข้อสอง',
            'score' => 1.0,
            'type' => 'choice',
            'exam_section_id' => $this->section1->id,
            'choices' => ['A', 'B'],
            'correct_choice' => 1,
        ]);
        $q2->assertJson(['success' => true]);

        $question1 = Question::where('question_text', 'ข้อแรก')->first();
        $question2 = Question::where('question_text', 'ข้อสอง')->first();

        $this->assertEquals(1, $question1->sort_order);
        $this->assertEquals(2, $question2->sort_order);
    }

    public function test_teacher_can_reorder_questions()
    {
        // Create 3 questions
        $q1 = Question::create([
            'exam_id' => $this->exam->id,
            'exam_section_id' => $this->section1->id,
            'question_text' => 'Question 1',
            'score' => 1.0,
            'sort_order' => 1,
            'type' => 'choice',
        ]);
        $q2 = Question::create([
            'exam_id' => $this->exam->id,
            'exam_section_id' => $this->section1->id,
            'question_text' => 'Question 2',
            'score' => 1.0,
            'sort_order' => 2,
            'type' => 'choice',
        ]);
        $q3 = Question::create([
            'exam_id' => $this->exam->id,
            'exam_section_id' => $this->section1->id,
            'question_text' => 'Question 3',
            'score' => 1.0,
            'sort_order' => 3,
            'type' => 'choice',
        ]);

        // Reorder: Move Q3 to first position, Q1 to second, Q2 to third
        $response = $this->actingAs($this->teacher)->postJson(route('admin.exams.questions.reorder', $this->exam->id), [
            'order' => [
                ['id' => $q3->id, 'sort_order' => 1, 'exam_section_id' => $this->section1->id],
                ['id' => $q1->id, 'sort_order' => 2, 'exam_section_id' => $this->section1->id],
                ['id' => $q2->id, 'sort_order' => 3, 'exam_section_id' => $this->section1->id],
            ]
        ]);

        $response->assertJson(['success' => true]);

        $this->assertEquals(1, $q3->fresh()->sort_order);
        $this->assertEquals(2, $q1->fresh()->sort_order);
        $this->assertEquals(3, $q2->fresh()->sort_order);

        // Verify index view gets them in new sort order
        $indexResponse = $this->actingAs($this->teacher)->get(route('admin.exams.questions.index', $this->exam->id));
        $sections = $indexResponse->viewData('sections');
        $section1Questions = $sections->firstWhere('id', $this->section1->id)->questions;

        $this->assertEquals($q3->id, $section1Questions->get(0)->id);
        $this->assertEquals($q1->id, $section1Questions->get(1)->id);
        $this->assertEquals($q2->id, $section1Questions->get(2)->id);
    }

    public function test_reorder_can_move_question_between_sections()
    {
        $q1 = Question::create([
            'exam_id' => $this->exam->id,
            'exam_section_id' => $this->section1->id,
            'question_text' => 'Question in Section 1',
            'score' => 1.0,
            'sort_order' => 1,
            'type' => 'choice',
        ]);

        // Move q1 to section 2
        $response = $this->actingAs($this->teacher)->postJson(route('admin.exams.questions.reorder', $this->exam->id), [
            'order' => [
                ['id' => $q1->id, 'sort_order' => 1, 'exam_section_id' => $this->section2->id],
            ]
        ]);

        $response->assertJson(['success' => true]);
        $this->assertEquals($this->section2->id, $q1->fresh()->exam_section_id);
    }

    public function test_student_takes_exam_in_custom_sort_order()
    {
        $this->exam->update(['approval_status' => 'approved', 'shuffle_questions' => false]);

        $q1 = Question::create([
            'exam_id' => $this->exam->id,
            'exam_section_id' => $this->section1->id,
            'question_text' => 'First created but sorted last',
            'score' => 1.0,
            'sort_order' => 2,
            'type' => 'choice',
        ]);
        Choice::create(['question_id' => $q1->id, 'choice_text' => 'A', 'is_correct' => true]);

        $q2 = Question::create([
            'exam_id' => $this->exam->id,
            'exam_section_id' => $this->section1->id,
            'question_text' => 'Second created but sorted first',
            'score' => 1.0,
            'sort_order' => 1,
            'type' => 'choice',
        ]);
        Choice::create(['question_id' => $q2->id, 'choice_text' => 'B', 'is_correct' => true]);

        $attempt = ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $this->exam->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'total_questions' => 2,
        ]);

        $response = $this->actingAs($this->student)->get(route('student.exam.take', [$this->exam->id, $attempt->id]));
        $response->assertOk();

        $questionsInView = $response->viewData('questions');
        // $q2 (sort_order = 1) should be first, $q1 (sort_order = 2) should be second
        $this->assertEquals($q2->id, $questionsInView->first()->id);
        $this->assertEquals($q1->id, $questionsInView->last()->id);
    }
}
