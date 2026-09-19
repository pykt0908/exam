<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Exam;
use App\Models\ExamApprovalLog;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher1;
    private User $teacher2;
    private Department $dept1;
    private Department $dept2;
    private Subject $subject1;
    private Subject $subject2;
    private Exam $exam1;
    private Exam $exam2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->dept1 = Department::create([
            'name' => 'สาขาวิชาสามัญ',
        ]);

        $this->dept2 = Department::create([
            'name' => 'สาขาเทคโนโลยีธุรกิจดิจิทัล',
        ]);

        $this->teacher1 = User::create([
            'name' => 'ครูสมชาย สอนดี',
            'teacher_code' => 'T101',
            'email' => 'somchai@test.com',
            'password' => bcrypt('password'),
            'role' => 'teacher',
            'department_id' => $this->dept1->id,
        ]);

        $this->teacher2 = User::create([
            'name' => 'ครูสมหญิง ใจดี',
            'teacher_code' => 'T102',
            'email' => 'somying@test.com',
            'password' => bcrypt('password'),
            'role' => 'teacher',
            'department_id' => $this->dept2->id,
        ]);

        $this->subject1 = Subject::create([
            'code' => 'TH101',
            'name' => 'ภาษาไทยเพื่อการสื่อสาร',
            'department_id' => $this->dept1->id,
        ]);

        $this->subject2 = Subject::create([
            'code' => 'IT101',
            'name' => 'การพัฒนาเว็บแอปพลิเคชัน',
            'department_id' => $this->dept2->id,
        ]);

        // Teacher 1 teaches Subject 1
        $this->subject1->teachers()->attach($this->teacher1->id);

        // Teacher 2 teaches Subject 2
        $this->subject2->teachers()->attach($this->teacher2->id);

        // Exam 1 under Subject 1
        $this->exam1 = Exam::create([
            'subject_id' => $this->subject1->id,
            'title' => 'สอบกลางภาคภาษาไทย',
            'duration_minutes' => 60,
            'passing_percentage' => 50,
            'total_score' => 20,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        // Exam 2 under Subject 2
        $this->exam2 = Exam::create([
            'subject_id' => $this->subject2->id,
            'title' => 'สอบกลางภาคการพัฒนาเว็บ',
            'duration_minutes' => 60,
            'passing_percentage' => 60,
            'total_score' => 30,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
    }

    public function test_admin_sees_filter_card_and_all_exams_by_default()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.exams.index'));

        $response->assertStatus(200);
        $response->assertSee('ค้นหาข้อสอบ');
        $response->assertSee('สาขาวิชา');
        $response->assertSee('รายวิชา');
        $response->assertSee('ครูผู้สอน');
        $response->assertSee('สอบกลางภาคภาษาไทย');
        $response->assertSee('สอบกลางภาคการพัฒนาเว็บ');
        $response->assertSee('ครูสมชาย สอนดี');
        $response->assertSee('ครูสมหญิง ใจดี');
    }

    public function test_admin_filtering_by_department_scopes_teachers_subjects_and_exams()
    {
        // Filter by Department 1 (สาขาวิชาสามัญ)
        $response = $this->actingAs($this->admin)->get(route('admin.exams.index', [
            'department_id' => $this->dept1->id,
        ]));

        $response->assertStatus(200);
        // Only Exam 1 from Dept 1 is shown
        $response->assertSee('สอบกลางภาคภาษาไทย');
        $response->assertDontSee('สอบกลางภาคการพัฒนาเว็บ');

        // Scoped teacher and subject dropdown options
        $response->assertSee('ครูสมชาย สอนดี');
        $response->assertDontSee('ครูสมหญิง ใจดี');
        $response->assertSee('ภาษาไทยเพื่อการสื่อสาร');
        $response->assertDontSee('การพัฒนาเว็บแอปพลิเคชัน');
    }

    public function test_admin_cascading_department_to_teacher_to_subject()
    {
        // Filter by Department 2 and Teacher 2
        $response = $this->actingAs($this->admin)->get(route('admin.exams.index', [
            'department_id' => $this->dept2->id,
            'teacher_id' => $this->teacher2->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('สอบกลางภาคการพัฒนาเว็บ');
        $response->assertDontSee('สอบกลางภาคภาษาไทย');
        $response->assertSee('การพัฒนาเว็บแอปพลิเคชัน');
        $response->assertDontSee('ภาษาไทยเพื่อการสื่อสาร');
    }

    public function test_admin_can_filter_exams_by_subject()
    {
        // Filter by Subject 1
        $response = $this->actingAs($this->admin)->get(route('admin.exams.index', [
            'subject_id' => $this->subject1->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('สอบกลางภาคภาษาไทย');
        $response->assertDontSee('สอบกลางภาคการพัฒนาเว็บ');
    }

    public function test_admin_can_filter_exams_by_teacher()
    {
        // Filter by Teacher 1
        $response = $this->actingAs($this->admin)->get(route('admin.exams.index', [
            'teacher_id' => $this->teacher1->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('สอบกลางภาคภาษาไทย');
        $response->assertDontSee('สอบกลางภาคการพัฒนาเว็บ');

        // Filter by Teacher 2
        $response2 = $this->actingAs($this->admin)->get(route('admin.exams.index', [
            'teacher_id' => $this->teacher2->id,
        ]));

        $response2->assertStatus(200);
        $response2->assertSee('สอบกลางภาคการพัฒนาเว็บ');
        $response2->assertDontSee('สอบกลางภาคภาษาไทย');
    }

    public function test_admin_can_filter_by_teacher_who_submitted_via_approval_log()
    {
        // Subject 3 has no assigned teacher in subject_user pivot
        $subject3 = Subject::create([
            'code' => 'MA101',
            'name' => 'คณิตศาสตร์เบื้องต้น',
        ]);

        $exam3 = Exam::create([
            'subject_id' => $subject3->id,
            'title' => 'สอบกลางภาคคณิตศาสตร์',
            'duration_minutes' => 45,
            'passing_percentage' => 50,
            'total_score' => 15,
            'is_active' => false,
            'approval_status' => 'pending_dept',
        ]);

        // Submitted by Teacher 1 in approval log
        ExamApprovalLog::create([
            'exam_id' => $exam3->id,
            'user_id' => $this->teacher1->id,
            'stage' => 'submit',
            'action' => 'submitted',
            'comment' => 'ส่งขออนุมัติ',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.exams.index', [
            'teacher_id' => $this->teacher1->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('สอบกลางภาคภาษาไทย');
        $response->assertSee('สอบกลางภาคคณิตศาสตร์');
        $response->assertDontSee('สอบกลางภาคการพัฒนาเว็บ');
    }

    public function test_admin_filter_shows_empty_message_when_no_match()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.exams.index', [
            'q' => 'วิชาที่ไม่มีจริงในระบบ 999',
        ]));

        $response->assertStatus(200);
        $response->assertSee('ไม่พบข้อสอบที่ตรงกับเงื่อนไขการค้นหา');
        $response->assertSee('ล้างตัวกรองทั้งหมด');
    }

    public function test_teacher_only_sees_exams_of_their_assigned_subjects()
    {
        $response = $this->actingAs($this->teacher1)->get(route('admin.exams.index'));

        $response->assertStatus(200);
        $response->assertSee('สอบกลางภาคภาษาไทย');
        $response->assertDontSee('สอบกลางภาคการพัฒนาเว็บ');
        // Teachers do not see the teacher or department filter dropdowns
        $response->assertDontSee('name="teacher_id"', false);
        $response->assertDontSee('name="department_id"', false);
    }
}
