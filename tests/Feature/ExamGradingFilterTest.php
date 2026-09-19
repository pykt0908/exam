<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamGradingFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private User $otherTeacher;
    private Subject $subject1;
    private Subject $subject2;
    private Classroom $classA;
    private Classroom $classB;
    private Classroom $classC;
    private Exam $exam1;
    private Exam $exam2;
    private User $studentA;
    private User $studentB;
    private User $studentC;
    private ExamAttempt $attemptA;
    private ExamAttempt $attemptB;

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
            'email' => 'teacher@test.com',
            'teacher_code' => 'T1001',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $this->otherTeacher = User::create([
            'name' => 'Other Teacher',
            'email' => 'other_teacher@test.com',
            'teacher_code' => 'T1002',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $this->classA = Classroom::create(['name' => 'ปวช.1/1']);
        $this->classB = Classroom::create(['name' => 'ปวช.1/2']);
        $this->classC = Classroom::create(['name' => 'ปวส.2/1']);

        $this->subject1 = Subject::create(['code' => 'SUB001', 'name' => 'วิชาเขียนโปรแกรม']);
        $this->subject2 = Subject::create(['code' => 'SUB002', 'name' => 'วิชาภาษาอังกฤษ']);

        // Teacher teaches subject1, Other Teacher teaches subject2
        $this->subject1->teachers()->attach($this->teacher->id);
        $this->subject2->teachers()->attach($this->otherTeacher->id);

        // Create exams
        $this->exam1 = Exam::create([
            'title' => 'สอบกลางภาคการเขียนโปรแกรม',
            'subject_id' => $this->subject1->id,
            'duration_minutes' => 60,
            'total_score' => 20,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $this->exam2 = Exam::create([
            'title' => 'สอบปลายภาคภาษาอังกฤษ',
            'subject_id' => $this->subject2->id,
            'duration_minutes' => 60,
            'total_score' => 20,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        // Students: studentA in classA, studentB in classB, studentC in classC
        $this->studentA = User::create([
            'name' => 'นักศึกษา A',
            'email' => 'studentA@test.com',
            'student_code' => 'STD001',
            'password' => bcrypt('password'),
            'role' => 'student',
            'classroom_id' => $this->classA->id,
        ]);

        $this->studentB = User::create([
            'name' => 'นักศึกษา B',
            'email' => 'studentB@test.com',
            'student_code' => 'STD002',
            'password' => bcrypt('password'),
            'role' => 'student',
            'classroom_id' => $this->classB->id,
        ]);

        $this->studentC = User::create([
            'name' => 'นักศึกษา C',
            'email' => 'studentC@test.com',
            'student_code' => 'STD003',
            'password' => bcrypt('password'),
            'role' => 'student',
            'classroom_id' => $this->classC->id,
        ]);

        // Enroll studentA and studentB in subject1
        $this->subject1->students()->attach([$this->studentA->id, $this->studentB->id]);
        // Enroll studentC in subject2
        $this->subject2->students()->attach([$this->studentC->id]);

        // Completed attempts
        $this->attemptA = ExamAttempt::create([
            'exam_id' => $this->exam1->id,
            'user_id' => $this->studentA->id,
            'status' => 'completed',
            'grading_status' => 'pending_grading',
            'score' => 15,
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ]);

        $this->attemptB = ExamAttempt::create([
            'exam_id' => $this->exam1->id,
            'user_id' => $this->studentB->id,
            'status' => 'completed',
            'grading_status' => 'graded',
            'score' => 18,
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ]);
    }

    public function test_grading_page_displays_hierarchical_filters()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.grading.index'));
        $response->assertStatus(200);
        $response->assertSee('รายวิชา');
        $response->assertSee('เลือกข้อสอบในวิชา');
        $response->assertSee('กลุ่มเรียน');
        $response->assertSee('ค้นหา');
    }

    public function test_filter_by_subject_scopes_exams_and_eligible_classrooms()
    {
        // When filtering by subject1 (SUB001)
        $response = $this->actingAs($this->admin)->get(route('admin.grading.index', [
            'subject_id' => $this->subject1->id,
        ]));

        $response->assertStatus(200);
        // Only exam1 should be in the exams dropdown for subject1
        $response->assertViewHas('exams', function ($exams) {
            return $exams->contains('id', $this->exam1->id) && !$exams->contains('id', $this->exam2->id);
        });

        // Only classA and classB (enrolled in subject1) should be in the classrooms dropdown
        $response->assertViewHas('classrooms', function ($classrooms) {
            return $classrooms->contains('id', $this->classA->id)
                && $classrooms->contains('id', $this->classB->id)
                && !$classrooms->contains('id', $this->classC->id);
        });
    }

    public function test_filter_by_exam_auto_syncs_subject_and_eligible_classrooms()
    {
        // When exam_id=exam1 is passed without subject_id
        $response = $this->actingAs($this->admin)->get(route('admin.grading.index', [
            'exam_id' => $this->exam1->id,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('subjectId', $this->subject1->id);
        $response->assertViewHas('examId', $this->exam1->id);

        // Classrooms should only contain classA and classB
        $response->assertViewHas('classrooms', function ($classrooms) {
            return $classrooms->contains('id', $this->classA->id)
                && $classrooms->contains('id', $this->classB->id)
                && !$classrooms->contains('id', $this->classC->id);
        });
    }

    public function test_filter_by_classroom_scopes_attempt_list()
    {
        // Filter by exam1 and classA
        $response = $this->actingAs($this->admin)->get(route('admin.grading.index', [
            'grading_status' => 'all',
            'subject_id' => $this->subject1->id,
            'exam_id' => $this->exam1->id,
            'classroom_id' => $this->classA->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('นักศึกษา A');
        $response->assertDontSee('นักศึกษา B');
    }

    public function test_teacher_only_sees_assigned_subjects_and_exams()
    {
        $response = $this->actingAs($this->teacher)->get(route('admin.grading.index'));
        $response->assertStatus(200);

        // Teacher only teaches subject1
        $response->assertViewHas('subjects', function ($subjects) {
            return $subjects->contains('id', $this->subject1->id) && !$subjects->contains('id', $this->subject2->id);
        });

        // Teacher cannot see exam2 (subject2)
        $response->assertViewHas('exams', function ($exams) {
            return $exams->contains('id', $this->exam1->id) && !$exams->contains('id', $this->exam2->id);
        });
    }

    public function test_step_by_step_dropdown_workflow()
    {
        // Step 1: No subject selected -> displays attempts table, exam & classroom dropdowns disabled with placeholder
        $step1 = $this->actingAs($this->admin)->get(route('admin.grading.index'));
        $step1->assertStatus(200);
        $step1->assertSee('รายการกระดาษคำตอบนักศึกษา');
        $step1->assertSee('นักศึกษา A');
        $step1->assertSee('กรุณาเลือกรายวิชาก่อน');
        $step1->assertSee('กรุณาเลือกข้อสอบก่อน');

        // Step 2: Subject selected -> exam dropdown is unlocked with exams of that subject, classroom still disabled
        $step2 = $this->actingAs($this->admin)->get(route('admin.grading.index', [
            'subject_id' => $this->subject1->id,
        ]));
        $step2->assertStatus(200);
        $step2->assertSee($this->exam1->title);
        $step2->assertDontSee($this->exam2->title);
        $step2->assertSee('กรุณาเลือกข้อสอบก่อน');

        // Step 3: Exam selected -> classroom dropdown unlocked with eligible rooms (classA, classB), attempt list displayed
        $step3 = $this->actingAs($this->admin)->get(route('admin.grading.index', [
            'subject_id' => $this->subject1->id,
            'exam_id' => $this->exam1->id,
        ]));
        $step3->assertStatus(200);
        $step3->assertSee('รายการกระดาษคำตอบนักศึกษา');
        $step3->assertSee('นักศึกษา A');
        $step3->assertSee($this->classA->name);
        $step3->assertSee($this->classB->name);
        $step3->assertDontSee($this->classC->name);
    }
}
