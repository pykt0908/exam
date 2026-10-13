<?php

namespace App\Services;

use App\Mail\ExamApprovalStatusMail;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ExamNotificationService
{
    /**
     * ส่งแจ้งเตือนเมื่อครูกดส่งขออนุมัติข้อสอบ
     */
    public static function notifyExamSubmitted(Exam $exam, User $submittedBy): void
    {
        try {
            $exam->loadMissing(['subject.department', 'subject.teachers']);
            $status = $exam->approval_status;

            $approvers = self::getApproversForStage($exam, $status);
            $stageName = self::getStageTitle($status);
            $previewUrl = route('admin.approvals.preview', $exam->id);

            foreach ($approvers as $approver) {
                // ข้ามผู้ส่งเอง (กรณีที่เป็นคนเดียวกัน)
                if ($approver->id === $submittedBy->id) {
                    continue;
                }

                if ($approver->wantsApprovalNotifications()) {
                    $mailable = new ExamApprovalStatusMail(
                        $exam,
                        'submitted',
                        $approver,
                        null,
                        $submittedBy->name,
                        $stageName,
                        $previewUrl
                    );

                    self::sendMailSafely($approver, $mailable);
                }
            }
        } catch (Throwable $e) {
            Log::error('ExamNotificationService::notifyExamSubmitted failed: ' . $e->getMessage());
        }
    }

    /**
     * ส่งแจ้งเตือนเมื่อข้อสอบได้รับการอนุมัติ (ทั้งผ่านระดับย่อย และอนุมัติขั้นสุดท้าย)
     */
    public static function notifyExamApproved(Exam $exam, User $approvedBy, ?string $feedback, string $previousStatus): void
    {
        try {
            $exam->loadMissing(['subject.department', 'subject.teachers', 'approvalLogs.user']);

            $stageName = self::getStageTitle($previousStatus);
            $teachers = self::getExamTeachers($exam);

            if ($exam->approval_status === 'approved') {
                // --- กรณีอนุมัติขั้นสุดท้ายสมบูรณ์ ---
                $viewUrl = route('admin.exams.questions.index', $exam->id);

                foreach ($teachers as $teacher) {
                    if ($teacher->wantsApprovalNotifications()) {
                        $mailable = new ExamApprovalStatusMail(
                            $exam,
                            'approved_final',
                            $teacher,
                            $feedback,
                            $approvedBy->name,
                            'อนุมัติขั้นสุดท้าย',
                            $viewUrl
                        );

                        self::sendMailSafely($teacher, $mailable);
                    }
                }
            } else {
                // --- กรณีอนุมัติผ่านระดับย่อย (ส่งต่อไปยังระดับถัดไป) ---
                $viewUrl = route('admin.exams.preview', $exam->id);

                // 1. แจ้งเตือนครูผู้สอนว่าผ่านขั้นนี้แล้ว
                foreach ($teachers as $teacher) {
                    if ($teacher->wantsApprovalNotifications()) {
                        $mailable = new ExamApprovalStatusMail(
                            $exam,
                            'approved_step',
                            $teacher,
                            $feedback,
                            $approvedBy->name,
                            $stageName,
                            $viewUrl
                        );

                        self::sendMailSafely($teacher, $mailable);
                    }
                }

                // 2. แจ้งเตือนผู้อนุมัติในสเตจถัดไป
                $nextApprovers = self::getApproversForStage($exam, $exam->approval_status);
                $nextStageName = self::getStageTitle($exam->approval_status);
                $previewUrl = route('admin.approvals.preview', $exam->id);

                foreach ($nextApprovers as $nextApprover) {
                    if ($nextApprover->id === $approvedBy->id) {
                        continue;
                    }

                    if ($nextApprover->wantsApprovalNotifications()) {
                        $mailable = new ExamApprovalStatusMail(
                            $exam,
                            'submitted',
                            $nextApprover,
                            $feedback ? "ผ่านการเห็นชอบจาก {$approvedBy->name} ({$stageName})" : null,
                            $approvedBy->name,
                            $nextStageName,
                            $previewUrl
                        );

                        self::sendMailSafely($nextApprover, $mailable);
                    }
                }
            }
        } catch (Throwable $e) {
            Log::error('ExamNotificationService::notifyExamApproved failed: ' . $e->getMessage());
        }
    }

    /**
     * ส่งแจ้งเตือนเมื่อข้อสอบถูกส่งกลับแก้ไข
     */
    public static function notifyExamRejected(Exam $exam, User $rejectedBy, string $reason): void
    {
        try {
            $exam->loadMissing(['subject.department', 'subject.teachers', 'approvalLogs.user']);

            $teachers = self::getExamTeachers($exam);
            $editUrl = route('admin.exams.questions.index', $exam->id);

            foreach ($teachers as $teacher) {
                if ($teacher->wantsApprovalNotifications()) {
                    $mailable = new ExamApprovalStatusMail(
                        $exam,
                        'rejected',
                        $teacher,
                        $reason,
                        $rejectedBy->name,
                        'ส่งกลับแก้ไข',
                        $editUrl
                    );

                    self::sendMailSafely($teacher, $mailable);
                }
            }
        } catch (Throwable $e) {
            Log::error('ExamNotificationService::notifyExamRejected failed: ' . $e->getMessage());
        }
    }

    /**
     * ดึงรายชื่อผู้อนุมัติในสเตจที่ระบุ
     *
     * @return Collection<int, User>
     */
    public static function getApproversForStage(Exam $exam, string $stage): Collection
    {
        $users = collect();

        if ($stage === 'pending_dept') {
            $deptId = $exam->subject?->department_id;
            $deptQuery = User::where('role', 'teacher')
                ->where('academic_role', 'LIKE', '%department_head%');

            if ($deptId) {
                $deptQuery->where('department_id', $deptId);
            }

            $users = $deptQuery->get();

            // หากไม่มีหัวหน้าแผนก ให้แจ้งเตือน Admin
            if ($users->isEmpty()) {
                $users = User::where('role', 'admin')->get();
            }
        } elseif ($stage === 'pending_eval') {
            $users = User::where('academic_role', 'LIKE', '%evaluation_head%')->get();

            if ($users->isEmpty()) {
                $users = User::where('role', 'admin')->get();
            }
        } elseif ($stage === 'pending_academic') {
            $users = User::where('academic_role', 'LIKE', '%academic_deputy%')->get();

            if ($users->isEmpty()) {
                $users = User::where('role', 'admin')->get();
            }
        }

        return $users->unique('id');
    }

    /**
     * ดึงครูผู้สอนที่เกี่ยวข้องกับข้อสอบนี้
     *
     * @return Collection<int, User>
     */
    public static function getExamTeachers(Exam $exam): Collection
    {
        $teachers = collect();

        // 1. จากครูที่กดส่งข้อสอบ (submit log)
        $submitLog = $exam->approvalLogs->firstWhere('action', 'submitted');
        if ($submitLog && $submitLog->user) {
            $teachers->push($submitLog->user);
        }

        // 2. จากครูประจำวิชา
        if ($exam->subject && $exam->subject->teachers->isNotEmpty()) {
            foreach ($exam->subject->teachers as $t) {
                $teachers->push($t);
            }
        }

        return $teachers->unique('id');
    }

    /**
     * ดึงชื่อขั้นตอนภาษาไทย
     */
    public static function getStageTitle(string $stage): string
    {
        return match ($stage) {
            'pending_dept' => 'หัวหน้าหมวดวิชา / หัวหน้าแผนก',
            'pending_eval' => 'หัวหน้างานวัดและประเมินผล',
            'pending_academic' => 'รองผู้อำนวยการฝ่ายวิชาการ',
            'approved' => 'อนุมัติเสร็จสมบูรณ์',
            'rejected' => 'ส่งกลับแก้ไข',
            default => 'พิจารณาข้อสอบ',
        };
    }

    /**
     * ส่งอีเมลอย่างปลอดภัยโดยไม่ทำให้ Request หลักพังหากส่งเมลล้มเหลว
     */
    public static function sendMailSafely(User $recipient, ExamApprovalStatusMail $mailable): bool
    {
        $email = $recipient->getEffectiveNotificationEmail();
        if (!$email) {
            return false;
        }

        try {
            Mail::to($email)->send($mailable);
            Log::info("ExamNotificationService: Sent {$mailable->type} email to {$email} (User ID: {$recipient->id})");
            return true;
        } catch (Throwable $e) {
            Log::warning("ExamNotificationService: Failed to send email to {$email}: " . $e->getMessage());
            return false;
        }
    }
}
