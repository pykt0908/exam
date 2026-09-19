<?php

namespace Tests\Feature;

use App\Models\Choice;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectFilterAndExamPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher1;
    private User $teacher2;
    private Department $deptA;
    private Department $deptB;
    private Subject $subjectA;
    private Subject $subjectB;
    private Exam $examA1;
    private Exam $examA2;
    private Exam $examB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deptA = Department::create(['name' => 'แผนกเทคโนโลยีสารสนเทศ']);
        $this->deptB = Department::create(['name' => 'แผนกช่างยนต์']);

        $this->admin = User::create([
            'name' => 'ผู้ดูแลระบบ',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->teacher1 = User::create([
            'name' => 'อาจารย์ สมชาย',
            'email' => 'teacher1@test.com',
            'teacher_code' => 'T001',
            'password' => bcrypt('password'),
            'role' => 'teacher',
            'department_id' => $this->deptA->id,
        ]);

        $this->teacher2 = User::create([
            'name' => 'อาจารย์ สมหญิง',
            'email' => 'teacher2@test.com',
            'teacher_code' => 'T002',
            'password' => bcrypt('password'),
            'role' => 'teacher',
            'department_id' => $this->deptB->id,
        ]);

        $this->subjectA = Subject::create([
            'code' => 'IT-101',
            'name' => 'การเขียนโปรแกรมคอมพิวเตอร์',
            'department_id' => $this->deptA->id,
        ]);
        $this->subjectA->teachers()->attach($this->teacher1->id);

        $this->subjectB = Subject::create([
            'code' => 'AU-201',
            'name' => 'เครื่องยนต์แก๊สโซลีน',
            'department_id' => $this->deptB->id,
        ]);
        $this->subjectB->teachers()->attach($this->teacher2->id);

        // Exams for subjectA
        $this->examA1 = Exam::create([
            'title' => 'สอบกลางภาคการเขียนโปรแกรม',
            'subject_id' => $this->subjectA->id,
            'duration_minutes' => 60,
            'total_score' => 20,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $this->examA2 = Exam::create([
            'title' => 'สอบปลายภาคการเขียนโปรแกรม',
            'subject_id' => $this->subjectA->id,
            'duration_minutes' => 90,
            'total_score' => 30,
            'is_active' => false,
            'approval_status' => 'draft',
        ]);

        // Questions for examA1
        $q1 = Question::create([
            'exam_id' => $this->examA1->id,
            'question_text' => 'ตัวแปร integer ในภาษา C ใช้เก็บข้อมูลชนิดใด?',
            'type' => 'choice',
            'score' => 10,
        ]);
        Choice::create(['question_id' => $q1->id, 'choice_text' => 'จำนวนเต็ม', 'is_correct' => true]);
        Choice::create(['question_id' => $q1->id, 'choice_text' => 'ทศนิยม', 'is_correct' => false]);

        $q2 = Question::create([
            'exam_id' => $this->examA1->id,
            'question_text' => 'จงอธิบายหลักการทำงานของ Loop for',
            'type' => 'essay',
            'essay_answer' => 'การทำงานซ้ำตามจำนวนรอบที่ระบุ',
            'score' => 10,
        ]);

        // Exam for subjectB
        $this->examB = Exam::create([
            'title' => 'สอบเครื่องยนต์พื้นฐาน',
            'subject_id' => $this->subjectB->id,
            'duration_minutes' => 60,
            'total_score' => 20,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
    }

    public function test_subject_index_displays_filters_and_exam_button()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index'));
        $response->assertStatus(200);
        $response->assertSee('หมวดวิชา / สาขาวิชา');
        $response->assertSee('ครูผู้สอน');
        $response->assertSee('รายวิชา');
        $response->assertSee($this->examA1->title);
        $response->assertSee($this->examB->title);
        $response->assertSee('1. เหมือนตอนอนุมัติ');
        $response->assertSee('2. มุมมองตอนทำข้อสอบ');
    }

    public function test_filter_subjects_by_department()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index', [
            'department_id' => $this->deptA->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('IT-101');
        $response->assertSee($this->examA1->title);
        $response->assertDontSee('AU-201');
        $response->assertDontSee($this->examB->title);
    }

    public function test_filter_by_department_scopes_teacher_dropdown()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index', [
            'department_id' => $this->deptA->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee($this->teacher1->name);
        $response->assertDontSee($this->teacher2->name);
    }

    public function test_filter_subjects_by_teacher()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index', [
            'teacher_id' => $this->teacher2->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('AU-201');
        $response->assertSee($this->examB->title);
        $response->assertDontSee('IT-101');
        $response->assertDontSee($this->examA1->title);
    }

    public function test_filter_by_subject()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index', [
            'subject_id' => $this->subjectA->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee($this->examA1->title);
        $response->assertSee($this->examA2->title);
        $response->assertDontSee($this->examB->title);
    }

    public function test_filter_subjects_by_keyword()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index', [
            'q' => 'แก๊สโซลีน',
        ]));
        $response->assertStatus(200);
        $response->assertSee('AU-201');
        $response->assertSee($this->examB->title);
        $response->assertDontSee('IT-101');
        $response->assertDontSee($this->examA1->title);
    }

    public function test_exam_index_filtered_by_subject_from_subject_button()
    {
        // When clicking the button on subjectA row
        $response = $this->actingAs($this->admin)->get(route('admin.exams.index', [
            'subject_id' => $this->subjectA->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee($this->examA1->title);
        $response->assertSee($this->examA2->title);
        $response->assertDontSee($this->examB->title);

        // Preview dropdown buttons present
        $response->assertSee('1. เหมือนตอนอนุมัติ');
        $response->assertSee('2. มุมมองตอนทำข้อสอบ');
        $response->assertSee(route('admin.exams.preview', [$this->examA1->id, 'mode' => 'approval']));
        $response->assertSee(route('admin.exams.preview', [$this->examA1->id, 'mode' => 'take']));
    }

    public function test_exam_preview_mode_approval_shows_paper_and_answer_keys()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.exams.preview', [
            'exam' => $this->examA1->id,
            'mode' => 'approval',
        ]));
        $response->assertStatus(200);
        $response->assertSee($this->examA1->title);
        $response->assertSee('ดูตัวอย่างข้อสอบ (พร้อมเฉลย)');
        $response->assertSee('ตัวแปร integer ในภาษา C ใช้เก็บข้อมูลชนิดใด?');
        $response->assertSee('จำนวนเต็ม');
        $response->assertSee('คำตอบที่ถูกต้อง');
        $response->assertSee('แนวทางคำตอบ / เฉลย (ข้อเขียน)');
        $response->assertSee('การทำงานซ้ำตามจำนวนรอบที่ระบุ');
    }

    public function test_exam_preview_mode_take_shows_simulation_and_does_not_create_attempts()
    {
        $initialAttemptsCount = ExamAttempt::count();

        $response = $this->actingAs($this->admin)->get(route('admin.exams.preview', [
            'exam' => $this->examA1->id,
            'mode' => 'take',
        ]));
        $response->assertStatus(200);
        $response->assertSee('ทดลองทำข้อสอบ (Preview Mode)');
        $response->assertSee('ออกจากโหมดทดลอง');
        $response->assertSee('สลับไปมุมมองตรวจข้อสอบ (พร้อมเฉลย)');
        $response->assertSee('ตัวแปร integer ในภาษา C ใช้เก็บข้อมูลชนิดใด?');

        // Confirm zero attempts were written to the database
        $this->assertEquals($initialAttemptsCount, ExamAttempt::count());
    }

    public function test_assigned_teacher_can_access_preview()
    {
        $response = $this->actingAs($this->teacher1)->get(route('admin.exams.preview', [
            'exam' => $this->examA1->id,
            'mode' => 'take',
        ]));
        $response->assertStatus(200);
    }

    public function test_unassigned_teacher_cannot_access_preview()
    {
        $response = $this->actingAs($this->teacher2)->get(route('admin.exams.preview', [
            'exam' => $this->examA1->id,
            'mode' => 'take',
        ]));
        $response->assertStatus(403);
    }

    public function test_subject_without_exams_is_visible_on_subjects_index()
    {
        $emptySubject = Subject::create([
            'code' => 'EMPTY-101',
            'name' => 'วิชาที่ยังไม่มีข้อสอบ',
            'department_id' => $this->deptA->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index'));
        $response->assertStatus(200);
        $response->assertSee('EMPTY-101');
        $response->assertSee('วิชาที่ยังไม่มีข้อสอบ');
        $response->assertSee('ยังไม่สร้างข้อสอบ');
        $response->assertSee(route('admin.subjects.destroy', $emptySubject->id));
    }

    public function test_filter_by_exam_status_no_exams()
    {
        $emptySubject = Subject::create([
            'code' => 'NOEXAM-99',
            'name' => 'วิชาไร้ข้อสอบ',
            'department_id' => $this->deptA->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.subjects.index', [
            'exam_status' => 'no_exams',
        ]));
        $response->assertStatus(200);
        $response->assertSee('NOEXAM-99');
        $response->assertSee('วิชาไร้ข้อสอบ');
        // Subjects with exams should not be shown
        $response->assertDontSee('IT-101');
        $response->assertDontSee($this->examA1->title);
    }

    public function test_admin_can_delete_subject()
    {
        $subjectToDelete = Subject::create([
            'code' => 'DEL-001',
            'name' => 'วิชาที่ต้องการลบ',
            'department_id' => $this->deptA->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.subjects.destroy', $subjectToDelete->id));
        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('success', 'ลบข้อมูลรายวิชาเรียบร้อยแล้ว');

        $this->assertDatabaseMissing('subjects', [
            'id' => $subjectToDelete->id,
        ]);
    }

    public function test_assigned_teacher_can_delete_subject()
    {
        $subjectToDelete = Subject::create([
            'code' => 'TEACH-DEL-002',
            'name' => 'วิชาที่ครูสอนลบ',
            'department_id' => $this->deptA->id,
        ]);
        $subjectToDelete->teachers()->attach($this->teacher1->id);

        $response = $this->actingAs($this->teacher1)->delete(route('admin.subjects.destroy', $subjectToDelete->id));
        $response->assertRedirect(route('admin.subjects.index'));
        $response->assertSessionHas('success', 'ลบข้อมูลรายวิชาเรียบร้อยแล้ว');

        $this->assertDatabaseMissing('subjects', [
            'id' => $subjectToDelete->id,
        ]);
    }

    public function test_unassigned_teacher_cannot_delete_subject()
    {
        $subjectToDelete = Subject::create([
            'code' => 'PROTECT-003',
            'name' => 'วิชาที่ห้ามครูอื่นลบ',
            'department_id' => $this->deptA->id,
        ]);
        $subjectToDelete->teachers()->attach($this->teacher1->id);

        $response = $this->actingAs($this->teacher2)->delete(route('admin.subjects.destroy', $subjectToDelete->id));
        $response->assertStatus(403);

        $this->assertDatabaseHas('subjects', [
            'id' => $subjectToDelete->id,
        ]);
    }
}
