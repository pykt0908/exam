<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamStudentOverride;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_detailed_student_profile_with_all_metrics()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@test.com',
        ]);

        $classroom = Classroom::create(['name' => 'ปวช.1 คอมพิวเตอร์ธุรกิจ 1']);

        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'สมชาย รักเรียน',
            'student_code' => 'STD001',
            'citizen_id' => '1234567890123',
            'classroom_id' => $classroom->id,
            'is_exam_eligible' => true,
        ]);

        $subject = Subject::create([
            'code' => 'BC101',
            'name' => 'การเขียนโปรแกรมคอมพิวเตอร์เบื้องต้น',
        ]);

        $student->enrolledSubjects()->attach($subject->id, [
            'is_eligible' => true,
        ]);

        $exam = Exam::create([
            'subject_id' => $subject->id,
            'teacher_id' => $admin->id,
            'title' => 'สอบกลางภาคการเขียนโปรแกรม',
            'duration_minutes' => 60,
            'total_score' => 100,
            'passing_percentage' => 50,
            'status' => 'approved',
        ]);

        // Create a completed attempt
        $attempt = ExamAttempt::create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'attempt_number' => 1,
            'started_at' => now()->subMinutes(30),
            'completed_at' => now()->subMinutes(5),
            'score' => 85.00,
            'raw_score' => 85.00,
            'total_raw_score' => 100.00,
            'total_questions' => 20,
            'is_passed' => true,
            'status' => 'completed',
            'grading_status' => 'graded',
            'focus_escape_count' => 2,
        ]);

        // Create an override
        ExamStudentOverride::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'extra_attempts' => 1,
            'reason' => 'ระบบอินเทอร์เน็ตขัดข้อง',
            'granted_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $student->id));

        $response->assertStatus(200);
        // Required info: ชื่อนามสกุล, รหัส, กลุ่มเรียน, เลขบัตร, และประวัติการสอบ พร้อม filter
        $response->assertSee('สมชาย รักเรียน');
        $response->assertSee('STD001');
        $response->assertSee('ปวช.1 คอมพิวเตอร์ธุรกิจ 1');
        $response->assertSee('1234567890123');
        $response->assertSee('ประวัติการสอบ');
        $response->assertSee('การเขียนโปรแกรมคอมพิวเตอร์เบื้องต้น');
        $response->assertSee('BC101');
        $response->assertSee('สอบกลางภาคการเขียนโปรแกรม');
        $response->assertSee('รอบที่ 1');
        $response->assertSee('85.00');
        $response->assertSee('2 ครั้ง'); // focus escape
        $response->assertSee('filter-status-btn');
        $response->assertSee('filter-subject');

        // Unnecessary clutter must not be present
        $response->assertDontSee('แก้ไขข้อมูลนักศึกษา');
        $response->assertDontSee('วิชาที่ลงทะเบียน (1)');
        $response->assertDontSee('สิทธิ์สอบพิเศษ (1)');
    }

    public function test_admin_can_view_student_with_ineligible_status()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'สมหญิง ติดการเงิน',
            'is_exam_eligible' => false,
            'ineligible_reason' => 'ค้างชำระค่าธรรมเนียมการเรียน',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $student->id));

        $response->assertStatus(200);
        $response->assertSee('สมหญิง ติดการเงิน');
        $response->assertSee('ระงับสิทธิ์สอบ');
        $response->assertSee('ค้างชำระค่าธรรมเนียมการเรียน');
    }
}
