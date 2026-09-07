<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamStudentOverride;
use App\Models\Question;
use App\Models\StudentAnswer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamStudentAttemptTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private User $otherTeacher;
    private User $student;
    private Subject $subject;
    private Exam $exam;

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
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $this->otherTeacher = User::create([
            'name' => 'Other Teacher',
            'email' => 'other_teacher@test.com',
            'password' => bcrypt('password'),
            'role' => 'teacher',
        ]);

        $this->student = User::create([
            'name' => 'Student Somchai',
            'email' => 'student@test.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'student_code' => 'STD001',
            'is_exam_eligible' => true,
        ]);

        $this->subject = Subject::create([
            'code' => 'ACC101',
            'name' => 'Accounting 1',
        ]);

        // Assign teacher to subject
        $this->subject->teachers()->attach($this->teacher->id);

        // Enroll student to subject
        $this->subject->students()->attach($this->student->id);

        $this->exam = Exam::create([
            'subject_id' => $this->subject->id,
            'title' => 'Midterm Exam',
            'duration_minutes' => 60,
            'max_attempts' => 1,
            'passing_percentage' => 60,
            'total_score' => 100,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
    }

    public function test_admin_and_subject_teacher_can_access_student_attempts_page()
    {
        // Admin access
        $resAdmin = $this->actingAs($this->admin)
            ->get(route('admin.exams.student-attempts.index', $this->exam->id));
        $resAdmin->assertStatus(200);

        // Assigned teacher access
        $resTeacher = $this->actingAs($this->teacher)
            ->get(route('admin.exams.student-attempts.index', $this->exam->id));
        $resTeacher->assertStatus(200);

        // Other teacher access should be forbidden
        $resOther = $this->actingAs($this->otherTeacher)
            ->get(route('admin.exams.student-attempts.index', $this->exam->id));
        $resOther->assertStatus(403);
    }

    public function test_quick_add_grants_extra_attempt()
    {
        $this->actingAs($this->teacher)
            ->post(route('admin.exams.student-attempts.quick-add', [$this->exam->id, $this->student->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('exam_student_overrides', [
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
            'extra_attempts' => 1,
            'granted_by' => $this->teacher->id,
        ]);

        $this->assertEquals(2, $this->exam->getAllowedAttemptsForUser($this->student->id));
    }

    public function test_update_and_reset_override()
    {
        // Update with custom attempts and reason
        $this->actingAs($this->admin)
            ->put(route('admin.exams.student-attempts.update', [$this->exam->id, $this->student->id]), [
                'extra_attempts' => 2,
                'reason' => 'สอบซ่อมรอบพิเศษ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('exam_student_overrides', [
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
            'extra_attempts' => 2,
            'reason' => 'สอบซ่อมรอบพิเศษ',
        ]);

        $this->assertEquals(3, $this->exam->getAllowedAttemptsForUser($this->student->id));

        // Reset
        $this->actingAs($this->admin)
            ->delete(route('admin.exams.student-attempts.reset', [$this->exam->id, $this->student->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('exam_student_overrides', [
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
        ]);

        $this->assertEquals(1, $this->exam->getAllowedAttemptsForUser($this->student->id));
    }

    public function test_bulk_add_extra_attempts()
    {
        $student2 = User::create([
            'name' => 'Student Somsak',
            'email' => 'student2@test.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'student_code' => 'STD002',
            'is_exam_eligible' => true,
        ]);
        $this->subject->students()->attach($student2->id);

        $this->actingAs($this->teacher)
            ->post(route('admin.exams.student-attempts.bulk', $this->exam->id), [
                'student_ids' => [$this->student->id, $student2->id],
                'action' => 'add_one',
                'bulk_reason' => 'เปิดให้สอบซ่อมพร้อมกัน',
            ])
            ->assertRedirect();

        $this->assertEquals(2, $this->exam->getAllowedAttemptsForUser($this->student->id));
        $this->assertEquals(2, $this->exam->getAllowedAttemptsForUser($student2->id));
    }

    public function test_student_attempt_limit_and_retake_unlocked()
    {
        // 1. Simulate 1 completed attempt
        ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
            'status' => 'completed',
            'score' => 40,
            'is_passed' => false,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        // Student tries to enter lobby -> blocked because 1/1 attempt used
        $responseBlocked = $this->actingAs($this->student)
            ->get(route('student.exam.lobby', $this->exam->id));
        $responseBlocked->assertRedirect(route('student.dashboard'));
        $responseBlocked->assertSessionHas('error');

        // Teacher grants +1 attempt
        $this->actingAs($this->teacher)
            ->post(route('admin.exams.student-attempts.quick-add', [$this->exam->id, $this->student->id]));

        // Now student has allowedAttempts = 2, completed = 1 -> can enter lobby!
        $responseAllowed = $this->actingAs($this->student)
            ->get(route('student.exam.lobby', $this->exam->id));
        $responseAllowed->assertStatus(200);
    }

    public function test_check_eligibility_during_exam_when_exam_id_differs_from_subject_id()
    {
        // Ensure student is enrolled in the subject
        $this->subject->students()->syncWithoutDetaching([$this->student->id]);

        // Create an attempt where exam_id might differ from subject_id
        $attempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->postJson(route('student.exam.checkEligibility', $attempt->id));

        $response->assertStatus(200);
        $response->assertJson([
            'eligible' => true,
            'reason' => null,
        ]);
    }

    public function test_admin_can_delete_single_attempt_and_reindexes_remaining_attempts()
    {
        $question = Question::create([
            'exam_id' => $this->exam->id,
            'question_text' => 'Sample Question 1',
            'type' => 'multiple_choice',
            'score' => 1,
        ]);

        $attempt1 = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => 'completed',
            'score' => 50,
            'started_at' => now()->subHours(3),
            'completed_at' => now()->subHours(2),
        ]);

        $attempt2 = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
            'attempt_number' => 2,
            'status' => 'completed',
            'score' => 70,
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ]);

        $attempt3 = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
            'attempt_number' => 3,
            'status' => 'completed',
            'score' => 90,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $answer2 = StudentAnswer::create([
            'exam_attempt_id' => $attempt2->id,
            'question_id' => $question->id,
            'answer_text' => 'B',
            'is_correct' => true,
            'score_awarded' => 1,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.exam-attempts.destroy', $attempt2->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        // Verify attempt2 and answer2 are deleted
        $this->assertDatabaseMissing('exam_attempts', ['id' => $attempt2->id]);
        $this->assertDatabaseMissing('student_answers', ['id' => $answer2->id]);

        // Verify remaining attempts are reindexed
        $attempt1->refresh();
        $attempt3->refresh();

        $this->assertEquals(1, $attempt1->attempt_number);
        $this->assertEquals(2, $attempt3->attempt_number);
    }

    public function test_admin_can_delete_all_student_attempts()
    {
        $question = Question::create([
            'exam_id' => $this->exam->id,
            'question_text' => 'Sample Question',
            'type' => 'multiple_choice',
            'score' => 1,
        ]);

        $attempt1 = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => 'completed',
            'score' => 60,
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ]);

        StudentAnswer::create([
            'exam_attempt_id' => $attempt1->id,
            'question_id' => $question->id,
            'answer_text' => 'A',
            'is_correct' => true,
            'score_awarded' => 1,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.exams.student-attempts.destroy-all', [$this->exam->id, $this->student->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('exam_attempts', [
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
        ]);
        $this->assertDatabaseMissing('student_answers', [
            'exam_attempt_id' => $attempt1->id,
        ]);
    }

    public function test_teacher_cannot_delete_attempts()
    {
        $attempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'user_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => 'completed',
            'score' => 70,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        // Teacher trying to delete single attempt -> 403
        $this->actingAs($this->teacher)
            ->delete(route('admin.exam-attempts.destroy', $attempt->id))
            ->assertStatus(403);

        // Teacher trying to delete all attempts -> 403
        $this->actingAs($this->teacher)
            ->delete(route('admin.exams.student-attempts.destroy-all', [$this->exam->id, $this->student->id]))
            ->assertStatus(403);

        // Attempt still exists
        $this->assertDatabaseHas('exam_attempts', ['id' => $attempt->id]);
    }

    public function test_refreshing_exam_counts_as_focus_escape_and_shows_warning()
    {
        $this->exam->update(['max_focus_escapes' => 2]);

        // 1. Student starts exam from lobby
        $this->actingAs($this->student)
            ->post(route('student.exam.start', $this->exam->id));

        $attempt = ExamAttempt::where('user_id', $this->student->id)->where('exam_id', $this->exam->id)->first();
        $this->assertNotNull($attempt);
        $this->assertEquals(0, $attempt->focus_escape_count);

        // 2. Initial visit to take page (with session flag) -> does NOT increment count
        $initialResponse = $this->actingAs($this->student)
            ->get(route('student.exam.take', [$this->exam->id, $attempt->id]));
        $initialResponse->assertStatus(200);
        $initialResponse->assertViewHas('wasRefreshed', false);
        $this->assertEquals(0, $attempt->fresh()->focus_escape_count);

        // 3. First refresh -> increments count to 1 and shows warning
        $refresh1 = $this->actingAs($this->student)
            ->get(route('student.exam.take', [$this->exam->id, $attempt->id]));
        $refresh1->assertStatus(200);
        $refresh1->assertViewHas('wasRefreshed', true);
        $refresh1->assertSee('คุณได้ทำการรีเฟรชหน้าจอข้อสอบ');
        $this->assertEquals(1, $attempt->fresh()->focus_escape_count);

        // 4. Second refresh -> reaches max_focus_escapes (2) -> auto-submits
        $refresh2 = $this->actingAs($this->student)
            ->get(route('student.exam.take', [$this->exam->id, $attempt->id]));
        $refresh2->assertRedirect(route('student.exam.result', $attempt->id));
        $this->assertEquals('completed', $attempt->fresh()->status);
        $this->assertEquals(2, $attempt->fresh()->focus_escape_count);
    }

    public function test_refresh_does_not_double_count_with_ajax_escape()
    {
        $this->exam->update(['max_focus_escapes' => 3]);

        // 1. Student starts exam from lobby
        $this->actingAs($this->student)
            ->post(route('student.exam.start', $this->exam->id));

        $attempt = ExamAttempt::where('user_id', $this->student->id)->where('exam_id', $this->exam->id)->first();

        // 2. Initial visit
        $this->actingAs($this->student)
            ->get(route('student.exam.take', [$this->exam->id, $attempt->id]));
        $this->assertEquals(0, $attempt->fresh()->focus_escape_count);

        // 3. First reload: browser triggers AJAX focusEscape right before page reload
        $this->actingAs($this->student)
            ->post(route('student.exam.focusEscape', $attempt->id));
        $this->assertEquals(1, $attempt->fresh()->focus_escape_count);

        // Then browser loads the refreshed page
        $refresh1 = $this->actingAs($this->student)
            ->get(route('student.exam.take', [$this->exam->id, $attempt->id]));
        $refresh1->assertStatus(200);
        $refresh1->assertViewHas('wasRefreshed', true);
        // It should still be 1 (NOT 2!)
        $this->assertEquals(1, $attempt->fresh()->focus_escape_count);
    }
}


