<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Classroom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_dashboard_with_all_metrics()
    {
        $admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $classroom = Classroom::create(['name' => 'ปวช.1/1']);

        $student = User::create([
            'name' => 'Somchai Student',
            'email' => 'student@test.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'student_code' => 'STD001',
            'classroom_id' => $classroom->id,
        ]);

        $subject = Subject::create([
            'code' => 'SUB101',
            'name' => 'Computer Science',
        ]);

        $exam = Exam::create([
            'subject_id' => $subject->id,
            'title' => 'Final Exam CS',
            'duration_minutes' => 60,
            'total_score' => 100,
            'passing_percentage' => 50,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'attempt_number' => 1,
            'score' => 80,
            'is_passed' => true,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('<h1 class="text-dark font-weight-bold">หน้าหลัก</h1>', false);
        $response->assertSee('ระบบบริหารจัดการข้อสอบออนไลน์');
        $response->assertSee('วิทยาลัยเทคโนโลยีศรีราชา');
        $response->assertSee('images/logo.png');
        $response->assertSee('images/bg.jpg');
        $response->assertSee('คู่มือการใช้งานสำหรับครู');
        $response->assertSee('manual_teacher.pdf');
        $response->assertSee('คู่มือสำหรับนักเรียน');
        $response->assertSee('manual_student.pdf');
    }
}
