<?php

namespace Tests\Feature;

use App\Mail\ExamApprovalStatusMail;
use App\Models\Department;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ExamApprovalNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_profile_and_update_notification_email(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'email' => 'T12345@exam.local',
            'notification_email' => null,
            'notify_email_approval' => true,
        ]);

        $this->assertFalse($teacher->hasConfiguredEmail());

        $response = $this->actingAs($teacher)->get('/profile');
        $response->assertStatus(200);
        $response->assertSee('ข้อมูลส่วนตัวและการแจ้งเตือน');

        // Update notification email
        $updateResponse = $this->actingAs($teacher)->put('/profile', [
            'name' => 'อาจารย์สมศรี มีสุข',
            'notification_email' => 'somsri@gmail.com',
            'phone' => '0891234567',
            'notify_email_approval' => '1',
        ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('success');

        $teacher->refresh();
        $this->assertEquals('somsri@gmail.com', $teacher->notification_email);
        $this->assertEquals('somsri@gmail.com', $teacher->getEffectiveNotificationEmail());
        $this->assertTrue($teacher->hasConfiguredEmail());
        $this->assertTrue($teacher->wantsApprovalNotifications());
    }

    public function test_user_can_send_test_email(): void
    {
        Mail::fake();

        $teacher = User::factory()->create([
            'role' => 'teacher',
            'notification_email' => 'test_teacher@example.com',
        ]);

        $response = $this->actingAs($teacher)->post('/profile/test-email', [
            'test_email' => 'test_teacher@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Mail::assertSent(ExamApprovalStatusMail::class, function ($mail) {
            return $mail->type === 'test' && $mail->hasTo('test_teacher@example.com');
        });
    }

    public function test_submitting_exam_sends_notification_to_department_head(): void
    {
        Mail::fake();

        $dept = Department::create(['name' => 'หมวดคอมพิวเตอร์']);

        $deptHead = User::factory()->create([
            'role' => 'teacher',
            'academic_role' => json_encode(['department_head']),
            'department_id' => $dept->id,
            'notification_email' => 'head_com@example.com',
            'notify_email_approval' => true,
        ]);

        $teacher = User::factory()->create([
            'role' => 'teacher',
            'department_id' => $dept->id,
            'notification_email' => 'teacher_com@example.com',
        ]);

        $subject = Subject::create([
            'code' => '30204-2001',
            'name' => 'การพัฒนาเว็บ',
            'department_id' => $dept->id,
        ]);
        $subject->teachers()->attach($teacher->id);

        $exam = Exam::create([
            'subject_id' => $subject->id,
            'title' => 'สอบกลางภาค',
            'duration_minutes' => 60,
            'passing_percentage' => 50,
            'approval_status' => 'draft',
        ]);

        Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'HTML ย่อมาจากอะไร?',
            'score' => 1,
            'type' => 'multiple_choice',
        ]);

        // Submit exam
        $response = $this->actingAs($teacher)->post(route('admin.exams.submit-approval', $exam->id));
        $response->assertRedirect();

        $exam->refresh();
        $this->assertEquals('pending_dept', $exam->approval_status);

        Mail::assertSent(ExamApprovalStatusMail::class, function ($mail) use ($deptHead) {
            return $mail->type === 'submitted' && $mail->hasTo($deptHead->notification_email);
        });
    }

    public function test_approving_exam_at_dept_stage_sends_notifications(): void
    {
        Mail::fake();

        $dept = Department::create(['name' => 'หมวดคอมพิวเตอร์']);

        $deptHead = User::factory()->create([
            'role' => 'teacher',
            'academic_role' => json_encode(['department_head']),
            'department_id' => $dept->id,
            'notification_email' => 'head_dept@example.com',
        ]);

        $evalHead = User::factory()->create([
            'role' => 'teacher',
            'academic_role' => json_encode(['evaluation_head']),
            'notification_email' => 'head_eval@example.com',
            'notify_email_approval' => true,
        ]);

        $teacher = User::factory()->create([
            'role' => 'teacher',
            'department_id' => $dept->id,
            'notification_email' => 'teacher_submitter@example.com',
            'notify_email_approval' => true,
        ]);

        $subject = Subject::create([
            'code' => '30204-2002',
            'name' => 'การโปรแกรมระบบ',
            'department_id' => $dept->id,
        ]);
        $subject->teachers()->attach($teacher->id);

        $exam = Exam::create([
            'subject_id' => $subject->id,
            'title' => 'สอบปลายภาค',
            'duration_minutes' => 60,
            'passing_percentage' => 50,
            'approval_status' => 'pending_dept',
        ]);

        Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'คำถามที่ 1',
            'score' => 1,
            'type' => 'multiple_choice',
        ]);

        // Dept head approves
        $response = $this->actingAs($deptHead)->post(route('admin.approvals.approve', $exam->id), [
            'feedback' => 'ข้อสอบได้มาตรฐานตามหลักสูตร',
        ]);

        $response->assertRedirect(route('admin.approvals.index'));

        $exam->refresh();
        $this->assertEquals('pending_eval', $exam->approval_status);

        // 1. Sent approved_step to teacher
        Mail::assertSent(ExamApprovalStatusMail::class, function ($mail) use ($teacher) {
            return $mail->type === 'approved_step' && $mail->hasTo($teacher->notification_email);
        });

        // 2. Sent submitted to next approver (eval head)
        Mail::assertSent(ExamApprovalStatusMail::class, function ($mail) use ($evalHead) {
            return $mail->type === 'submitted' && $mail->hasTo($evalHead->notification_email);
        });
    }

    public function test_rejecting_exam_sends_notification_with_reason_to_teacher(): void
    {
        Mail::fake();

        $dept = Department::create(['name' => 'หมวดช่างยนต์']);

        $deptHead = User::factory()->create([
            'role' => 'teacher',
            'academic_role' => json_encode(['department_head']),
            'department_id' => $dept->id,
            'notification_email' => 'head_mechanic@example.com',
        ]);

        $teacher = User::factory()->create([
            'role' => 'teacher',
            'department_id' => $dept->id,
            'notification_email' => 'mechanic_teacher@example.com',
            'notify_email_approval' => true,
        ]);

        $subject = Subject::create([
            'code' => '30101-2001',
            'name' => 'งานเครื่องยนต์แก๊สโซลีน',
            'department_id' => $dept->id,
        ]);
        $subject->teachers()->attach($teacher->id);

        $exam = Exam::create([
            'subject_id' => $subject->id,
            'title' => 'สอบกลางภาคช่างยนต์',
            'duration_minutes' => 60,
            'passing_percentage' => 50,
            'approval_status' => 'pending_dept',
        ]);

        Question::create([
            'exam_id' => $exam->id,
            'question_text' => 'เครื่องยนต์ 4 จังหวะมีกี่จังหวะ?',
            'score' => 1,
            'type' => 'multiple_choice',
        ]);

        // Dept head rejects
        $response = $this->actingAs($deptHead)->post(route('admin.approvals.reject', $exam->id), [
            'reason' => 'กรุณาเพิ่มข้อสอบปรนัยอีก 10 ข้อ และตรวจสอบเฉลยข้อ 5',
        ]);

        $response->assertRedirect(route('admin.approvals.index'));

        $exam->refresh();
        $this->assertEquals('rejected', $exam->approval_status);
        $this->assertEquals('กรุณาเพิ่มข้อสอบปรนัยอีก 10 ข้อ และตรวจสอบเฉลยข้อ 5', $exam->rejection_note);

        Mail::assertSent(ExamApprovalStatusMail::class, function ($mail) use ($teacher) {
            return $mail->type === 'rejected' 
                && $mail->hasTo($teacher->notification_email)
                && str_contains($mail->comment, 'กรุณาเพิ่มข้อสอบปรนัย');
        });
    }
}
