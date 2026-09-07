<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private Subject $subject1;
    private Subject $subject2;
    private Classroom $classA;
    private Classroom $classB;
    private Exam $exam1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->teacher = User::create([
            'name' => 'Teacher User',
            'teacher_code' => 'T1001',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $this->classA = Classroom::create(['name' => 'ปวช.1/1']);
        $this->classB = Classroom::create(['name' => 'ปวช.1/2']);

        $this->subject1 = Subject::create(['code' => 'SUB001', 'name' => 'วิชาเขียนโปรแกรม 1']);
        $this->subject2 = Subject::create(['code' => 'SUB002', 'name' => 'วิชาบัญชีเบื้องต้น']);

        // Teacher teaches subject1
        $this->subject1->teachers()->attach($this->teacher->id);

        // Create an exam under subject1
        $this->exam1 = Exam::create([
            'title' => 'สอบกลางภาคการเขียนโปรแกรม',
            'subject_id' => $this->subject1->id,
            'user_id' => $this->teacher->id,
            'duration_minutes' => 60,
            'total_score' => 10,
            'pass_percentage' => 50,
            'status' => 'published',
            'approval_stage' => 'approved',
        ]);

        Question::create([
            'exam_id' => $this->exam1->id,
            'question_text' => 'ข้อ 1',
            'type' => 'multiple_choice',
            'score' => 10,
        ]);

        // Student in Class A
        $studentA = User::create([
            'name' => 'สมหมาย ห้องหนึ่ง',
            'student_code' => 'STD001',
            'citizen_id' => '1111111111111',
            'password' => bcrypt('password'),
            'role' => 'student',
            'classroom_id' => $this->classA->id,
        ]);
        $this->subject1->students()->attach($studentA->id);

        // Attempt for student A
        ExamAttempt::create([
            'exam_id' => $this->exam1->id,
            'user_id' => $studentA->id,
            'status' => 'completed',
            'score' => 8,
            'is_passed' => true,
            'started_at' => now()->subHours(1),
            'completed_at' => now(),
        ]);

        // Student in Class B
        $studentB = User::create([
            'name' => 'สมศรี ห้องสอง',
            'student_code' => 'STD002',
            'citizen_id' => '2222222222222',
            'password' => bcrypt('password'),
            'role' => 'student',
            'classroom_id' => $this->classB->id,
        ]);
        $this->subject1->students()->attach($studentB->id);

        // Attempt for student B
        ExamAttempt::create([
            'exam_id' => $this->exam1->id,
            'user_id' => $studentB->id,
            'status' => 'completed',
            'score' => 4,
            'is_passed' => false,
            'started_at' => now()->subHours(1),
            'completed_at' => now(),
        ]);
    }

    public function test_admin_sees_prompt_to_select_subject_initially(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('กรุณาเลือกรายวิชาเพื่อดูรายงานสรุปผลคะแนนและสถิติ');
    }

    public function test_admin_can_view_subject_report_with_all_classrooms(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', [
            'subject_id' => $this->subject1->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('วิชาเขียนโปรแกรม 1');
        $response->assertSee('ทุกห้องเรียน (แสดงทั้งหมด)');
        // Both students from Class A and Class B should be listed
        $response->assertSee('สมหมาย ห้องหนึ่ง');
        $response->assertSee('สมศรี ห้องสอง');
        $response->assertSee('ปวช.1/1');
        $response->assertSee('ปวช.1/2');
    }

    public function test_admin_can_filter_report_by_specific_classroom(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', [
            'subject_id' => $this->subject1->id,
            'classroom_id' => $this->classA->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('วิชาเขียนโปรแกรม 1');
        $response->assertSee('ห้อง ปวช.1/1');
        // Only student from Class A should be shown
        $response->assertSee('สมหมาย ห้องหนึ่ง');
        $response->assertDontSee('สมศรี ห้องสอง');
    }

    public function test_teacher_defaults_to_their_only_subject(): void
    {
        // Teacher teaches only subject1, so it automatically selects subject1
        $response = $this->actingAs($this->teacher)->get(route('admin.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('วิชาเขียนโปรแกรม 1');
        $response->assertSee('สมหมาย ห้องหนึ่ง');
        $response->assertSee('สมศรี ห้องสอง');
    }

    public function test_teacher_cannot_view_report_of_unassigned_subject(): void
    {
        // Teacher does NOT teach subject2
        $response = $this->actingAs($this->teacher)->get(route('admin.reports.index', [
            'subject_id' => $this->subject2->id,
        ]));

        $response->assertStatus(403);
    }

    public function test_teacher_cannot_filter_by_exam_of_unassigned_subject(): void
    {
        // Create an exam under subject2 (which teacher does NOT teach)
        $exam2 = Exam::create([
            'title' => 'สอบกลางภาควิชาบัญชี',
            'subject_id' => $this->subject2->id,
            'duration_minutes' => 60,
            'total_score' => 20,
            'pass_percentage' => 50,
            'approval_stage' => 'approved',
        ]);

        $response = $this->actingAs($this->teacher)->get(route('admin.reports.index', [
            'exam_id' => $exam2->id,
        ]));

        $response->assertStatus(403);
    }

    public function test_admin_can_view_any_subject_report(): void
    {
        // Admin can view subject1
        $res1 = $this->actingAs($this->admin)->get(route('admin.reports.index', [
            'subject_id' => $this->subject1->id,
        ]));
        $res1->assertStatus(200);

        // Admin can also view subject2
        $res2 = $this->actingAs($this->admin)->get(route('admin.reports.index', [
            'subject_id' => $this->subject2->id,
        ]));
        $res2->assertStatus(200);
    }

    public function test_teacher_dropdown_only_shows_assigned_subjects(): void
    {
        $subject3 = Subject::create(['code' => 'SUB003', 'name' => 'วิชาเคมีเบื้องต้น']);
        // Teacher teaches subject1, but not subject2 or subject3
        $response = $this->actingAs($this->teacher)->get(route('admin.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('วิชาเขียนโปรแกรม 1');
        $response->assertDontSee('วิชาเคมีเบื้องต้น');
    }

    public function test_teacher_without_assigned_subjects_sees_helpful_message(): void
    {
        $unassignedTeacher = User::create([
            'name' => 'Unassigned Teacher',
            'teacher_code' => 'T9999',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $response = $this->actingAs($unassignedTeacher)->get(route('admin.reports.index'));

        $response->assertStatus(200);
        $response->assertSee('ไม่พบรายวิชาที่รับผิดชอบ');
    }

    public function test_report_displays_student_attempt_rounds_and_scores(): void
    {
        $studentA = User::where('student_code', 'STD001')->first();

        // Student A takes round 2
        ExamAttempt::create([
            'exam_id' => $this->exam1->id,
            'user_id' => $studentA->id,
            'attempt_number' => 2,
            'status' => 'completed',
            'score' => 10,
            'is_passed' => true,
            'started_at' => now()->subMinutes(30),
            'completed_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', [
            'subject_id' => $this->subject1->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('รอบที่ 1');
        $response->assertSee('รอบที่ 2');
        $response->assertSee('คะแนนแต่ละรอบ:');
        $response->assertSee('ประวัติ 2 รอบ');
    }

    public function test_subject_with_multiple_exams_displays_matrix_gradebook_columns(): void
    {
        // Add 2 more exams to subject1 (total 3 exams)
        $exam2 = Exam::create([
            'title' => 'แบบทดสอบกลางภาค',
            'subject_id' => $this->subject1->id,
            'duration_minutes' => 60,
            'total_score' => 20,
            'pass_percentage' => 50,
            'approval_stage' => 'approved',
        ]);

        $exam3 = Exam::create([
            'title' => 'แบบทดสอบปลายภาค',
            'subject_id' => $this->subject1->id,
            'duration_minutes' => 90,
            'total_score' => 30,
            'pass_percentage' => 50,
            'approval_stage' => 'approved',
        ]);

        $studentA = User::where('student_code', 'STD001')->first();

        // Student A takes Exam 2
        ExamAttempt::create([
            'exam_id' => $exam2->id,
            'user_id' => $studentA->id,
            'attempt_number' => 1,
            'status' => 'completed',
            'score' => 15,
            'is_passed' => true,
            'started_at' => now()->subMinutes(60),
            'completed_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.index', [
            'subject_id' => $this->subject1->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('ชื่อ-นามสกุล');
        $response->assertSee('สอบกลางภาคการเขียนโปรแกรม');
        $response->assertSee('แบบทดสอบกลางภาค');
        $response->assertSee('แบบทดสอบปลายภาค');
        $response->assertDontSee('คะแนนรวม');
        $response->assertDontSee('เต็ม 60 คะแนน');
        $response->assertSee('สมหมาย ห้องหนึ่ง');
        $response->assertSee('ส่งออก Excel');
    }

    public function test_admin_and_teacher_can_export_report_to_excel(): void
    {
        // Admin export
        $adminResponse = $this->actingAs($this->admin)->get(route('admin.reports.export', [
            'subject_id' => $this->subject1->id,
            'classroom_id' => $this->classA->id,
        ]));

        $adminResponse->assertStatus(200);
        $adminResponse->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', $adminResponse->headers->get('content-disposition'));

        // Teacher export for assigned subject
        $teacherResponse = $this->actingAs($this->teacher)->get(route('admin.reports.export', [
            'subject_id' => $this->subject1->id,
        ]));

        $teacherResponse->assertStatus(200);
        $teacherResponse->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_teacher_cannot_export_unassigned_subject(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.reports.export', [
            'subject_id' => $this->subject2->id,
        ]));

        $response->assertStatus(403);
    }
}

