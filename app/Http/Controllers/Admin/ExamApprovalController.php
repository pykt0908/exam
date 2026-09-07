<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamApprovalLog;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamApprovalController extends Controller
{
    /**
     * Display listing of exams for approval.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $this->authorizeStaffAccess($user);

        $tab = $request->get('tab', 'my_pending'); // my_pending, all_pending, approved, rejected

        $query = Exam::with(['subject.department', 'subject.teachers', 'deptApprover', 'evalApprover', 'academicApprover', 'rejecter'])
            ->withCount('questions');

        // Filter based on tab
        if ($tab === 'my_pending') {
            if ($user->isAdmin()) {
                $query->whereIn('approval_status', ['pending_dept', 'pending_eval', 'pending_academic']);
            } else {
                $query->where(function ($q) use ($user) {
                    $hasCondition = false;

                    // Department Head: pending_dept for their department
                    if ($user->isDepartmentHead()) {
                        $q->orWhere(function ($deptQuery) use ($user) {
                            $deptQuery->where('approval_status', 'pending_dept')
                                ->whereHas('subject', function ($sQuery) use ($user) {
                                    if ($user->department_id) {
                                        $sQuery->where(function ($subQ) use ($user) {
                                            $subQ->where('department_id', $user->department_id)
                                                 ->orWhereNull('department_id');
                                        });
                                    }
                                });
                        });
                        $hasCondition = true;
                    }

                    // Evaluation Head: pending_eval
                    if ($user->isEvaluationHead()) {
                        $q->orWhere('approval_status', 'pending_eval');
                        $hasCondition = true;
                    }

                    // Academic Deputy: pending_academic
                    if ($user->isAcademicDeputy()) {
                        $q->orWhere('approval_status', 'pending_academic');
                        $hasCondition = true;
                    }

                    if (!$hasCondition) {
                        // User has no approval roles, show empty
                        $q->whereRaw('1 = 0');
                    }
                });
            }
        } elseif ($tab === 'all_pending') {
            $query->whereIn('approval_status', ['pending_dept', 'pending_eval', 'pending_academic']);
        } elseif ($tab === 'approved') {
            if ($user->isAdmin()) {
                $query->where(function ($q) {
                    $q->where('approval_status', 'approved')
                      ->orWhereNotNull('dept_approved_by')
                      ->orWhereNotNull('eval_approved_by')
                      ->orWhereNotNull('academic_approved_by');
                });
            } else {
                $query->where(function ($q) use ($user) {
                    $hasCondition = false;

                    // If user was the dept approver
                    if ($user->isDepartmentHead()) {
                        $q->orWhere('dept_approved_by', $user->id);
                        $hasCondition = true;
                    }

                    // If user was the eval approver
                    if ($user->isEvaluationHead()) {
                        $q->orWhere('eval_approved_by', $user->id);
                        $hasCondition = true;
                    }

                    // If user was the academic approver
                    if ($user->isAcademicDeputy()) {
                        $q->orWhere('academic_approved_by', $user->id);
                        $hasCondition = true;
                    }

                    // Or fully approved exams that match user's department
                    if ($user->department_id) {
                        $q->orWhere(function ($subQ) use ($user) {
                            $subQ->where('approval_status', 'approved')
                                 ->whereHas('subject', function ($sQ) use ($user) {
                                     $sQ->where('department_id', $user->department_id);
                                 });
                        });
                        $hasCondition = true;
                    }

                    if (!$hasCondition) {
                        $q->where('approval_status', 'approved');
                    }
                });
            }
        } elseif ($tab === 'rejected') {
            $query->where('approval_status', 'rejected');
        }

        // Optional search filter
        if ($request->filled('q')) {
            $searchTerm = $request->get('q');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhereHas('subject', function ($sQ) use ($searchTerm) {
                      $sQ->where('code', 'like', "%{$searchTerm}%")
                         ->orWhere('name', 'like', "%{$searchTerm}%");
                  });
            });
        }

        $exams = $query->orderBy('submitted_at', 'desc')->orderBy('updated_at', 'desc')->get();

        // Calculate counts for badges
        $myPendingCount = $this->getMyPendingQuery($user)->count();
        $allPendingCount = Exam::whereIn('approval_status', ['pending_dept', 'pending_eval', 'pending_academic'])->count();

        return view('admin.approvals.index', compact('exams', 'tab', 'myPendingCount', 'allPendingCount'));
    }

    /**
     * Preview exam before approving or rejecting.
     */
    public function preview(Exam $exam)
    {
        $user = Auth::user();
        $this->authorizeStaffAccess($user);

        $exam->load([
            'subject.department',
            'subject.teachers',
            'sections.questions.choices',
            'deptApprover',
            'evalApprover',
            'academicApprover',
            'rejecter',
            'approvalLogs.user'
        ]);

        $canApprove = $this->canUserApproveStage($user, $exam);

        return view('admin.approvals.preview', compact('exam', 'canApprove'));
    }

    /**
     * Teacher submits exam for approval.
     */
    public function submit(Exam $exam)
    {
        $user = Auth::user();

        // Teacher or Admin who has rights to this exam
        if (!$user->isAdmin()) {
            $teaches = $user->enrolledSubjects()->where('subject_id', $exam->subject_id)->exists();
            if (!$teaches) {
                abort(403, 'คุณไม่มีสิทธิ์จัดการข้อสอบนี้');
            }
        }

        if (!$exam->canBeEdited()) {
            return back()->with('error', 'ข้อสอบนี้ได้ถูกส่งขออนุมัติไปแล้ว');
        }

        if ($exam->questions()->count() === 0) {
            return back()->with('error', 'ไม่สามารถส่งขออนุมัติได้เนื่องจากยังไม่มีคำถามในข้อสอบ');
        }

        // Determine target status: if it was rejected previously at a specific stage, return to that stage
        $targetStatus = 'pending_dept';
        $targetStageName = 'หัวหน้าหมวดวิชา';

        if ($exam->rejected_stage === 'pending_eval') {
            $targetStatus = 'pending_eval';
            $targetStageName = 'หัวหน้างานวัดและประเมินผล';
        } elseif ($exam->rejected_stage === 'pending_academic') {
            $targetStatus = 'pending_academic';
            $targetStageName = 'รองผู้อำนวยการฝ่ายวิชาการ';
        }

        // Update exam status
        $exam->update([
            'approval_status' => $targetStatus,
            'submitted_at' => now(),
            'rejection_note' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejected_stage' => null,
        ]);

        // Log action
        ExamApprovalLog::create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'stage' => 'submit',
            'action' => 'submitted',
            'comment' => 'ส่งข้อสอบเพื่อขออนุมัติ (ส่งต่อไปยัง' . $targetStageName . ')',
        ]);

        return back()->with('success', 'ส่งข้อสอบเพื่อขออนุมัติไปยัง' . $targetStageName . 'เรียบร้อยแล้ว');
    }

    /**
     * Teacher recalls exam back to draft before it is approved.
     */
    public function recall(Exam $exam)
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            $teaches = $user->enrolledSubjects()->where('subject_id', $exam->subject_id)->exists();
            if (!$teaches) {
                abort(403, 'คุณไม่มีสิทธิ์จัดการข้อสอบนี้');
            }
        }

        if (!$exam->isPending()) {
            return back()->with('error', 'ข้อสอบไม่ได้อยู่ในสถานะรออนุมัติ');
        }

        $exam->update([
            'approval_status' => 'draft',
        ]);

        // Log action
        ExamApprovalLog::create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'stage' => 'recall',
            'action' => 'recalled',
            'comment' => 'ดึงข้อสอบกลับมาแก้ไข',
        ]);

        return back()->with('success', 'ดึงข้อสอบกลับมาเป็นฉบับร่างเรียบร้อยแล้ว สามารถแก้ไขข้อสอบได้');
    }

    /**
     * Approve exam at the current stage.
     */
    public function approve(Request $request, Exam $exam)
    {
        $user = Auth::user();

        if (!$this->canUserApproveStage($user, $exam)) {
            abort(403, 'คุณไม่มีสิทธิ์อนุมัติข้อสอบในขั้นตอนนี้');
        }

        $feedback = $request->input('feedback');

        if ($exam->approval_status === 'pending_dept') {
            $exam->update([
                'approval_status' => 'pending_eval',
                'dept_approved_by' => $user->id,
                'dept_approved_at' => now(),
                'dept_feedback' => $feedback,
            ]);

            ExamApprovalLog::create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'stage' => 'dept_head',
                'action' => 'approved',
                'comment' => $feedback ?: 'หัวหน้าหมวดวิชาอนุมัติเรียบร้อย',
            ]);

            return redirect()->route('admin.approvals.index')
                ->with('success', 'อนุมัติข้อสอบในระดับหัวหน้าหมวดเรียบร้อยแล้ว ส่งต่อให้งานวัดผลตรวจสอบ');
        } elseif ($exam->approval_status === 'pending_eval') {
            $exam->update([
                'approval_status' => 'pending_academic',
                'eval_approved_by' => $user->id,
                'eval_approved_at' => now(),
                'eval_feedback' => $feedback,
            ]);

            ExamApprovalLog::create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'stage' => 'evaluation_head',
                'action' => 'approved',
                'comment' => $feedback ?: 'หัวหน้างานวัดและประเมินผลอนุมัติเรียบร้อย',
            ]);

            return redirect()->route('admin.approvals.index')
                ->with('success', 'อนุมัติข้อสอบในระดับงานวัดและประเมินผลเรียบร้อยแล้ว ส่งต่อให้รองฝ่ายวิชาการพิจารณา');
        } elseif ($exam->approval_status === 'pending_academic') {
            $exam->update([
                'approval_status' => 'approved',
                'academic_approved_by' => $user->id,
                'academic_approved_at' => now(),
                'academic_feedback' => $feedback,
                'is_active' => true, // Automatically activate once fully approved
            ]);

            ExamApprovalLog::create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'stage' => 'academic_deputy',
                'action' => 'approved',
                'comment' => $feedback ?: 'รองผู้อำนวยการฝ่ายวิชาการอนุมัติขั้นสุดท้ายเรียบร้อย',
            ]);

            return redirect()->route('admin.approvals.index')
                ->with('success', 'อนุมัติข้อสอบขั้นสุดท้ายเรียบร้อยแล้ว! ข้อสอบพร้อมเปิดให้นักเรียนเข้าทำตามกำหนดเวลา');
        }

        return back()->with('error', 'สถานะของข้อสอบไม่ถูกต้องสำหรับการอนุมัติ');
    }

    /**
     * Reject exam and send back for revision.
     */
    public function reject(Request $request, Exam $exam)
    {
        $user = Auth::user();

        if (!$this->canUserApproveStage($user, $exam)) {
            abort(403, 'คุณไม่มีสิทธิ์ส่งกลับแก้ไขข้อสอบในขั้นตอนนี้');
        }

        $reason = $request->input('reason') ?: $request->input('rejection_reason');

        if (empty($reason) || mb_strlen(trim($reason)) < 3) {
            return back()->withErrors(['reason' => 'กรุณาระบุเหตุผลหรือข้อแก้ไขที่ต้องการให้ครูผู้สอนปรับปรุง (อย่างน้อย 3 ตัวอักษร)']);
        }

        $stage = match ($exam->approval_status) {
            'pending_dept' => 'dept_head',
            'pending_eval' => 'evaluation_head',
            'pending_academic' => 'academic_deputy',
            default => 'unknown',
        };

        $currentStatus = $exam->approval_status;

        $exam->update([
            'approval_status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_note' => $reason,
            'rejected_stage' => $currentStatus,
            'is_active' => false,
        ]);

        ExamApprovalLog::create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'stage' => $stage,
            'action' => 'rejected',
            'comment' => $reason,
        ]);

        return redirect()->route('admin.approvals.index')
            ->with('success', 'ส่งกลับข้อสอบเพื่อให้ครูผู้สอนแก้ไขเรียบร้อยแล้ว');
    }

    /**
     * Determine if current user can approve/reject this exam at its current stage.
     */
    public function canUserApproveStage($user, Exam $exam): bool
    {
        if ($user->isAdmin()) {
            return $exam->isPending();
        }

        if ($exam->approval_status === 'pending_dept') {
            if (!$user->isDepartmentHead()) {
                return false;
            }
            // Check department match if subject and user have department_id set
            if ($user->department_id && $exam->subject && $exam->subject->department_id) {
                return $user->department_id === $exam->subject->department_id;
            }
            return true;
        }

        if ($exam->approval_status === 'pending_eval') {
            return $user->isEvaluationHead();
        }

        if ($exam->approval_status === 'pending_academic') {
            return $user->isAcademicDeputy();
        }

        return false;
    }

    private function authorizeStaffAccess($user): void
    {
        if (!$user->isStaff()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        }

        if (!$user->isAdmin() && empty($user->academic_roles)) {
            abort(403, 'เฉพาะผู้มีหน้าที่ด้านวิชาการ (หัวหน้าหมวด, งานวัดผล, รองวิชาการ) เท่านั้นที่สามารถเข้าใช้งานได้');
        }
    }

    private function getMyPendingQuery($user)
    {
        $query = Exam::query();

        if ($user->isAdmin()) {
            return $query->whereIn('approval_status', ['pending_dept', 'pending_eval', 'pending_academic']);
        }

        return $query->where(function ($q) use ($user) {
            $hasCondition = false;

            if ($user->isDepartmentHead()) {
                $q->orWhere(function ($deptQuery) use ($user) {
                    $deptQuery->where('approval_status', 'pending_dept')
                        ->whereHas('subject', function ($sQuery) use ($user) {
                            if ($user->department_id) {
                                $sQuery->where(function ($subQ) use ($user) {
                                    $subQ->where('department_id', $user->department_id)
                                         ->orWhereNull('department_id');
                                });
                            }
                        });
                });
                $hasCondition = true;
            }

            if ($user->isEvaluationHead()) {
                $q->orWhere('approval_status', 'pending_eval');
                $hasCondition = true;
            }

            if ($user->isAcademicDeputy()) {
                $q->orWhere('approval_status', 'pending_academic');
                $hasCondition = true;
            }

            if (!$hasCondition) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
