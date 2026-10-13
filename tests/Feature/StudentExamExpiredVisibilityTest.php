<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentExamExpiredVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $classroom = Classroom::create([
            'name' => 'ปวช. 1/1 ช่างยนต์',
            'level' => 'ปวช.',
            'year' => 1,
        ]);

        $this->student = User::factory()->create([
            'role' => 'student',
            'classroom_id' => $classroom->id,
            'is_exam_eligible' => true,
        ]);

        $this->subject = Subject::create([
            'code' => '20101-2001',
            'name' => 'งานเครื่องยนต์แก๊สโซลีน',
        ]);

        $this->student->enrolledSubjects()->attach($this->subject->id);
    }

    public function test_active_exam_is_visible_on_student_dashboard(): void
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'สอบกลางภาควิชาเครื่องยนต์',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'is_active' => true,
            'approval_status' => 'approved',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($this->student)->get(route('student.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('สอบกลางภาควิชาเครื่องยนต์');
        $response->assertSee('พร้อมสอบ');
    }

    public function test_expired_exam_is_hidden_from_student_dashboard(): void
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'สอบย่อยที่หมดเวลาแล้ว',
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'is_active' => true,
            'approval_status' => 'approved',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->student)->get(route('student.dashboard'));
        $response->assertStatus(200);
        $response->assertDontSee('สอบย่อยที่หมดเวลาแล้ว');
        $response->assertDontSee('หมดเวลาสอบแล้ว');
    }

    public function test_expired_exam_with_retake_permission_remains_visible(): void
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'สอบซ่อมพิเศษหลังหมดเวลา',
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'is_active' => true,
            'approval_status' => 'approved',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);

        // Student took it once and failed
        ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'attempt_number' => 1,
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDays(2)->addMinutes(25),
            'score' => 4,
            'is_passed' => false,
            'status' => 'completed',
        ]);

        // Teacher grants extra attempt
        $exam->studentOverrides()->create([
            'user_id' => $this->student->id,
            'extra_attempts' => 1,
        ]);

        $response = $this->actingAs($this->student)->get(route('student.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('สอบซ่อมพิเศษหลังหมดเวลา');
        $response->assertSee('สอบซ่อมได้');
    }

    public function test_expired_exam_passed_by_student_remains_visible_for_review(): void
    {
        $exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'สอบเก็บคะแนนที่ผ่านแล้ว',
            'duration_minutes' => 30,
            'max_attempts' => 1,
            'is_active' => true,
            'approval_status' => 'approved',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(2),
            'allow_review' => true,
        ]);

        // Student took it and passed
        ExamAttempt::create([
            'user_id' => $this->student->id,
            'exam_id' => $exam->id,
            'attempt_number' => 1,
            'started_at' => now()->subDays(3),
            'completed_at' => now()->subDays(3)->addMinutes(25),
            'score' => 9,
            'is_passed' => true,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->student)->get(route('student.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('สอบเก็บคะแนนที่ผ่านแล้ว');
        $response->assertSee('สอบผ่านแล้ว');
    }
}
