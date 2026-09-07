<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\Choice;
use App\Models\ExamAttempt;
use App\Models\ExamApprovalLog;
use App\Models\ExamStudentOverride;
use App\Models\Classroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_duplicate_approved_exam_and_it_resets_to_draft(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $subject = Subject::create([
            'code' => 'BC-101',
            'name' => 'Computer Programming',
        ]);

        $originalExam = Exam::create([
            'subject_id' => $subject->id,
            'title' => 'สอบปลายภาค วิทยาการคำนวณ',
            'description' => 'ข้อสอบวัดความรู้ปลายภาค',
            'duration_minutes' => 90,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(5),
            'passing_percentage' => 60,
            'total_score' => 50,
            'is_active' => true,
            'max_attempts' => 2,
            'shuffle_questions' => true,
            'shuffle_choices' => true,
            'force_fullscreen' => true,
            'max_focus_escapes' => 3,
            'passcode' => 'SECRET123',
            'show_score' => true,
            'show_answers' => false,
            'allow_review' => true,
            'approval_status' => 'approved',
            'submitted_at' => now()->subDays(5),
            'dept_approved_by' => $admin->id,
            'dept_approved_at' => now()->subDays(4),
            'eval_approved_by' => $admin->id,
            'eval_approved_at' => now()->subDays(3),
            'academic_approved_by' => $admin->id,
            'academic_approved_at' => now()->subDays(2),
        ]);

        // Create Section
        $section = ExamSection::create([
            'exam_id' => $originalExam->id,
            'title' => 'ตอนที่ 1: ปรนัย',
            'instruction' => 'เลือกคำตอบที่ถูกต้องที่สุด',
            'sort_order' => 1,
        ]);

        // Create Question 1 (Choice)
        $q1 = Question::create([
            'exam_id' => $originalExam->id,
            'exam_section_id' => $section->id,
            'type' => 'choice',
            'question_text' => 'ข้อใดคือภาษาโปรแกรม?',
            'score' => 25,
        ]);

        Choice::create([
            'question_id' => $q1->id,
            'choice_text' => 'PHP',
            'is_correct' => true,
        ]);

        Choice::create([
            'question_id' => $q1->id,
            'choice_text' => 'HTML',
            'is_correct' => false,
        ]);

        // Create Question 2 (Essay)
        Question::create([
            'exam_id' => $originalExam->id,
            'exam_section_id' => null,
            'type' => 'essay',
            'question_text' => 'จงอธิบายหลักการทำงานของ MVC',
            'score' => 25,
            'essay_answer' => 'Model View Controller',
        ]);

        // Action: Duplicate the exam
        $response = $this->actingAs($admin)->post(route('admin.exams.duplicate', $originalExam->id));

        $response->assertRedirect(route('admin.exams.index'));
        $response->assertSessionHas('success');

        // Verify newly created duplicate exam in database
        $this->assertDatabaseCount('exams', 2);

        $newExam = Exam::where('id', '!=', $originalExam->id)->first();
        $this->assertNotNull($newExam);
        $this->assertEquals('สอบปลายภาค วิทยาการคำนวณ (สำเนา)', $newExam->title);
        $this->assertEquals($originalExam->subject_id, $newExam->subject_id);
        $this->assertEquals($originalExam->duration_minutes, $newExam->duration_minutes);
        $this->assertEquals($originalExam->total_score, $newExam->total_score);
        $this->assertEquals($originalExam->passing_percentage, $newExam->passing_percentage);
        $this->assertEquals('SECRET123', $newExam->passcode);
        $this->assertTrue($newExam->shuffle_questions);
        $this->assertTrue($newExam->force_fullscreen);

        // Verification of requirement: MUST RE-SUBMIT APPROVAL (status is draft and inactive)
        $this->assertEquals('draft', $newExam->approval_status);
        $this->assertFalse($newExam->is_active);
        $this->assertNull($newExam->submitted_at);
        $this->assertNull($newExam->dept_approved_by);
        $this->assertNull($newExam->dept_approved_at);
        $this->assertNull($newExam->eval_approved_by);
        $this->assertNull($newExam->academic_approved_by);
        $this->assertNull($newExam->rejected_by);

        // Verify sections duplicated
        $this->assertDatabaseCount('exam_sections', 2);
        $newSection = $newExam->sections()->first();
        $this->assertNotNull($newSection);
        $this->assertEquals('ตอนที่ 1: ปรนัย', $newSection->title);
        $this->assertNotEquals($section->id, $newSection->id);

        // Verify questions duplicated with proper section mapping
        $this->assertDatabaseCount('questions', 4);
        $this->assertEquals(2, $newExam->questions()->count());

        $newQ1 = $newExam->questions()->where('type', 'choice')->first();
        $this->assertNotNull($newQ1);
        $this->assertEquals('ข้อใดคือภาษาโปรแกรม?', $newQ1->question_text);
        $this->assertEquals($newSection->id, $newQ1->exam_section_id);
        $this->assertEquals(2, $newQ1->choices()->count());
        $this->assertTrue($newQ1->choices()->where('choice_text', 'PHP')->first()->is_correct);

        $newQ2 = $newExam->questions()->where('type', 'essay')->first();
        $this->assertNotNull($newQ2);
        $this->assertEquals('จงอธิบายหลักการทำงานของ MVC', $newQ2->question_text);
        $this->assertNull($newQ2->exam_section_id);
    }

    public function test_assigned_teacher_can_duplicate_their_exam(): void
    {
        $teacher = User::create([
            'name' => 'Kru Somsak',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $subject = Subject::create([
            'code' => 'BC-102',
            'name' => 'Database Systems',
        ]);
        $subject->teachers()->attach($teacher->id);

        $exam = Exam::create([
            'subject_id' => $subject->id,
            'title' => 'สอบกลางภาค ฐานข้อมูล',
            'duration_minutes' => 60,
            'passing_percentage' => 50,
            'total_score' => 30,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($teacher)->post(route('admin.exams.duplicate', $exam->id));

        $response->assertRedirect(route('admin.exams.index'));
        $this->assertDatabaseHas('exams', [
            'title' => 'สอบกลางภาค ฐานข้อมูล (สำเนา)',
            'approval_status' => 'draft',
            'is_active' => false,
        ]);
    }

    public function test_unassigned_teacher_cannot_duplicate_exam(): void
    {
        $teacher = User::create([
            'name' => 'Kru Somsak',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $otherSubject = Subject::create([
            'code' => 'BC-999',
            'name' => 'Other Subject',
        ]);

        $exam = Exam::create([
            'subject_id' => $otherSubject->id,
            'title' => 'ข้อสอบวิชาอื่น',
            'duration_minutes' => 60,
            'passing_percentage' => 50,
            'total_score' => 30,
            'approval_status' => 'approved',
        ]);

        $response = $this->actingAs($teacher)->post(route('admin.exams.duplicate', $exam->id));

        $response->assertStatus(403);
        $this->assertDatabaseCount('exams', 1);
    }

    public function test_duplicate_does_not_copy_student_attempts_or_overrides_or_logs(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $classroom = Classroom::create(['name' => 'ปวช. 1/1']);

        $student = User::create([
            'name' => 'Student Somchai',
            'email' => 'student@test.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'student_code' => '66001',
            'classroom_id' => $classroom->id,
        ]);

        $subject = Subject::create([
            'code' => 'BC-103',
            'name' => 'Network Essentials',
        ]);

        $exam = Exam::create([
            'subject_id' => $subject->id,
            'title' => 'สอบ Network',
            'duration_minutes' => 60,
            'passing_percentage' => 50,
            'total_score' => 100,
            'approval_status' => 'approved',
        ]);

        // Add attempt
        ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'attempt_number' => 1,
            'score' => 85,
            'status' => 'completed',
            'is_passed' => true,
        ]);

        // Add override
        ExamStudentOverride::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'extra_attempts' => 2,
        ]);

        // Add approval log
        ExamApprovalLog::create([
            'exam_id' => $exam->id,
            'user_id' => $admin->id,
            'stage' => 'academic',
            'action' => 'approved',
            'note' => 'อนุมัติเรียบร้อย',
        ]);

        $this->actingAs($admin)->post(route('admin.exams.duplicate', $exam->id));

        $newExam = Exam::where('id', '!=', $exam->id)->first();
        $this->assertNotNull($newExam);

        // New exam must have 0 attempts, 0 overrides, 0 logs
        $this->assertEquals(0, $newExam->examAttempts()->count());
        $this->assertEquals(0, $newExam->studentOverrides()->count());
        $this->assertEquals(0, $newExam->approvalLogs()->count());
    }
}
