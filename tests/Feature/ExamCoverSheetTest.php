<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamCoverSheetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher1;
    private User $teacher2;
    private User $student1;
    private User $student2;
    private Classroom $classroom1;
    private Classroom $classroom2;
    private Subject $subject;
    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $dept = Department::create(['name' => 'แผนกเทคโนโลยีสารสนเทศ']);

        $this->admin = User::create([
            'name' => 'ผู้ดูแลระบบ',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->teacher1 = User::create([
            'name' => 'อาจารย์พงษ์พันธุ์ ประกอบผล',
            'email' => 'teacher1@test.com',
            'teacher_code' => 'T001',
            'password' => bcrypt('password'),
            'role' => 'teacher',
            'department_id' => $dept->id,
        ]);

        $this->teacher2 = User::create([
            'name' => 'อาจารย์ สมหญิง',
            'email' => 'teacher2@test.com',
            'teacher_code' => 'T002',
            'password' => bcrypt('password'),
            'role' => 'teacher',
            'department_id' => $dept->id,
        ]);

        $this->classroom1 = Classroom::create(['name' => 'ปวส.1/2(1/69)ม.6 เทคโน']);
        $this->classroom2 = Classroom::create(['name' => 'ปวส.1/1 เทคโน']);

        $this->student1 = User::create([
            'name' => 'นายกรกฎ ชื่นอุรา',
            'email' => 'student1@test.com',
            'student_code' => '69319100216',
            'password' => bcrypt('password'),
            'role' => 'student',
            'classroom_id' => $this->classroom1->id,
        ]);

        $this->student2 = User::create([
            'name' => 'นางสาวนวพร พรมเปี่ยม',
            'email' => 'student2@test.com',
            'student_code' => '69319100221',
            'password' => bcrypt('password'),
            'role' => 'student',
            'classroom_id' => $this->classroom2->id,
        ]);

        $this->subject = Subject::create([
            'code' => '31910-0004',
            'name' => 'ระบบปฏิบัติการและบำรุงรักษาคอมพิวเตอร์',
            'department_id' => $dept->id,
        ]);

        $this->subject->teachers()->attach($this->teacher1->id);
        $this->subject->students()->attach([$this->student1->id, $this->student2->id]);

        $this->exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'สอบปลายภาค ภาคเรียนที่ 1/2569',
            'duration_minutes' => 60,
            'passing_percentage' => 50,
            'total_score' => 100,
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_access_exam_cover_sheet(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.exams.cover-sheet', $this->exam->id));

        $response->assertStatus(200);
        $response->assertSee('images/logo.png');
        $response->assertSee('วิทยาลัยเทคโนโลยีศรีราชา');
        $response->assertSee('สอบปลายภาค ภาคเรียนที่ 1/2569');
        $response->assertSee('31910-0004');
        $response->assertSee('ระบบปฏิบัติการและบำรุงรักษาคอมพิวเตอร์');
        $response->assertSee('ปวส.1/2(1/69)ม.6 เทคโน');
        $response->assertSee('นายกรกฎ ชื่นอุรา');
        $response->assertSee('69319100216');
    }

    public function test_assigned_teacher_can_access_cover_sheet(): void
    {
        $response = $this->actingAs($this->teacher1)->get(route('admin.exams.cover-sheet', $this->exam->id));

        $response->assertStatus(200);
        $response->assertSee('อาจารย์พงษ์พันธุ์ ประกอบผล');
    }

    public function test_unassigned_teacher_cannot_access_cover_sheet(): void
    {
        $response = $this->actingAs($this->teacher2)->get(route('admin.exams.cover-sheet', $this->exam->id));

        $response->assertStatus(403);
    }

    public function test_student_cannot_access_cover_sheet(): void
    {
        $response = $this->actingAs($this->student1)->get(route('admin.exams.cover-sheet', $this->exam->id));

        $response->assertStatus(403);
    }

    public function test_can_filter_cover_sheet_by_specific_classroom(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.exams.cover-sheet', [
            $this->exam->id,
            'classroom_id' => $this->classroom1->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('ปวส.1/2(1/69)ม.6 เทคโน');
        $response->assertSee('นายกรกฎ ชื่นอุรา');
        // Student 2 in classroom 2 should not be displayed when scoped to classroom 1
        $response->assertDontSee('นางสาวนวพร พรมเปี่ยม');
    }

    public function test_exam_create_and_edit_page_renders_preset_dropdowns(): void
    {
        $createResponse = $this->actingAs($this->admin)->get(route('admin.exams.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertSee('exam_type_preset', false);
        $createResponse->assertSee('semester_preset', false);
        $createResponse->assertSee('academic_year_preset', false);

        $questionsResponse = $this->actingAs($this->admin)->get(route('admin.exams.questions.index', $this->exam->id));
        $questionsResponse->assertStatus(200);
        $questionsResponse->assertSee('edit_exam_type_preset', false);
        $questionsResponse->assertSee('edit_semester_preset', false);
        $questionsResponse->assertSee('edit_academic_year_preset', false);
    }

    public function test_exams_index_displays_cover_sheet_button(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.exams.index'));
        $response->assertStatus(200);
        $response->assertSee(route('admin.exams.cover-sheet', $this->exam->id));
        $response->assertSee('ใบปะหน้า');
    }

    public function test_ineligible_students_display_zero_when_none_and_shows_names_when_ineligible(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.exams.cover-sheet', [
            $this->exam->id,
            'classroom_id' => $this->classroom1->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('ไม่มีสิทธิ์สอบ:');

        // Mark student 1 as ineligible
        $this->student1->update(['is_exam_eligible' => false]);

        $responseIneligible = $this->actingAs($this->admin)->get(route('admin.exams.cover-sheet', [
            $this->exam->id,
            'classroom_id' => $this->classroom1->id,
        ]));
        $responseIneligible->assertStatus(200);
        $responseIneligible->assertSee('นายกรกฎ ชื่นอุรา (69319100216)');
    }

    public function test_cover_sheet_auto_populates_morning_or_afternoon_session(): void
    {
        // Classroom 1 has shift 'morning' (default when no 'บ่าย')
        $this->classroom1->update(['shift' => 'morning']);

        $responseMorning = $this->actingAs($this->admin)->get(route('admin.exams.cover-sheet', [
            $this->exam->id,
            'classroom_id' => $this->classroom1->id,
        ]));
        $responseMorning->assertStatus(200);
        $responseMorning->assertSee('data-default-session="เช้า"', false);

        // Update classroom 1 to afternoon
        $this->classroom1->update(['shift' => 'afternoon']);

        $responseAfternoon = $this->actingAs($this->admin)->get(route('admin.exams.cover-sheet', [
            $this->exam->id,
            'classroom_id' => $this->classroom1->id,
        ]));
        $responseAfternoon->assertStatus(200);
        $responseAfternoon->assertSee('data-default-session="บ่าย"', false);
    }
}
