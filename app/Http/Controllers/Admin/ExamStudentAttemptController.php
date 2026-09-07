<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\User;
use App\Models\Classroom;
use App\Models\ExamAttempt;
use App\Models\ExamStudentOverride;
use Illuminate\Http\Request;

class ExamStudentAttemptController extends Controller
{
    private function authorizeExam(Exam $exam): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }

        if ($user->isTeacher()) {
            $isTeacherOfSubject = $exam->subject->teachers()->where('users.id', $user->id)->exists();
            if ($isTeacherOfSubject) {
                return;
            }
        }

        abort(403, 'คุณไม่มีสิทธิ์จัดการข้อสอบนี้');
    }

    public function index(Exam $exam, Request $request)
    {
        $this->authorizeExam($exam);

        $exam->load(['subject.department', 'subject.teachers']);

        // Load classrooms enrolled in this subject
        $classrooms = Classroom::whereHas('users', function ($q) use ($exam) {
            $q->where('role', 'student')->whereHas('enrolledSubjects', function ($q2) use ($exam) {
                $q2->where('subjects.id', $exam->subject_id);
            });
        })->orderBy('name')->get();

        // Get all students enrolled in the subject
        $studentsQuery = $exam->subject->students()->with('classroom');

        if ($request->filled('classroom_id')) {
            $studentsQuery->where('users.classroom_id', $request->get('classroom_id'));
        }

        if ($request->filled('q')) {
            $q = $request->get('q');
            $studentsQuery->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('student_code', 'like', "%{$q}%");
            });
        }

        $allStudents = $studentsQuery->get();

        // Get all attempts for this exam
        $attempts = ExamAttempt::where('exam_id', $exam->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('user_id');

        // Get all overrides for this exam
        $overrides = ExamStudentOverride::where('exam_id', $exam->id)
            ->with('granter')
            ->get()
            ->keyBy('user_id');

        // Decorate student data
        $students = $allStudents->map(function ($student) use ($exam, $attempts, $overrides) {
            $studentAttempts = $attempts->get($student->id, collect());
            $completedAttempts = $studentAttempts->where('status', 'completed');
            $inProgressAttempt = $studentAttempts->firstWhere('status', 'in_progress');
            
            $override = $overrides->get($student->id);
            $extraAttempts = $override ? (int) $override->extra_attempts : 0;
            $allowedAttempts = (int) ($exam->max_attempts ?? 1) + $extraAttempts;
            $completedCount = $completedAttempts->count();
            $remainingAttempts = max(0, $allowedAttempts - $completedCount);

            $hasPassed = $completedAttempts->where('is_passed', true)->isNotEmpty();
            $latestAttempt = $completedAttempts->first(); // sorted by created_at desc
            $bestScore = $completedAttempts->max('score');

            $student->student_attempts_count = $completedCount;
            $student->in_progress_attempt = $inProgressAttempt;
            $student->latest_attempt = $latestAttempt;
            $student->best_score = $bestScore;
            $student->has_passed = $hasPassed;
            $student->override = $override;
            $student->extra_attempts = $extraAttempts;
            $student->allowed_attempts = $allowedAttempts;
            $student->remaining_attempts = $remainingAttempts;

            if ($completedCount === 0) {
                $student->status_group = 'not_attempted';
            } elseif ($hasPassed) {
                $student->status_group = 'passed';
            } else {
                $student->status_group = 'failed';
            }

            return $student;
        });

        // Calculate statistics before filtering by status
        $stats = [
            'total' => $students->count(),
            'passed' => $students->where('has_passed', true)->count(),
            'failed' => $students->where('status_group', 'failed')->count(),
            'not_attempted' => $students->where('status_group', 'not_attempted')->count(),
            'has_extra' => $students->where('extra_attempts', '>', 0)->count(),
        ];

        // Filter by status tab if specified
        $statusFilter = $request->get('status', 'all');
        if ($statusFilter === 'failed') {
            $students = $students->filter(fn($s) => $s->status_group === 'failed');
        } elseif ($statusFilter === 'passed') {
            $students = $students->filter(fn($s) => $s->has_passed);
        } elseif ($statusFilter === 'not_attempted') {
            $students = $students->filter(fn($s) => $s->status_group === 'not_attempted');
        } elseif ($statusFilter === 'has_extra') {
            $students = $students->filter(fn($s) => $s->extra_attempts > 0);
        }

        return view('admin.exams.student_attempts', compact('exam', 'students', 'classrooms', 'stats', 'statusFilter'));
    }

    public function quickAdd(Exam $exam, User $student)
    {
        $this->authorizeExam($exam);

        $override = ExamStudentOverride::firstOrNew([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        $override->extra_attempts = ($override->extra_attempts ?? 0) + 1;
        if (empty($override->reason)) {
            $override->reason = 'เปิดให้สอบเพิ่ม/สอบซ่อม';
        }
        $override->granted_by = auth()->id();
        $override->save();

        $totalAllowed = (int) ($exam->max_attempts ?? 1) + $override->extra_attempts;

        return redirect()->back()->with('success', "เปิดให้ [{$student->student_code}] {$student->name} สอบเพิ่ม 1 ครั้งเรียบร้อยแล้ว (สิทธิ์สอบรวม: {$totalAllowed} ครั้ง)");
    }

    public function update(Request $request, Exam $exam, User $student)
    {
        $this->authorizeExam($exam);

        $request->validate([
            'extra_attempts' => ['required', 'integer', 'min:0', 'max:50'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $extraAttempts = (int) $request->get('extra_attempts');

        if ($extraAttempts === 0 && empty($request->reason)) {
            ExamStudentOverride::where('exam_id', $exam->id)
                ->where('user_id', $student->id)
                ->delete();

            return redirect()->back()->with('success', "รีเซ็ตสิทธิ์สอบเพิ่มสำหรับ [{$student->student_code}] {$student->name} เรียบร้อยแล้ว");
        }

        ExamStudentOverride::updateOrCreate(
            ['exam_id' => $exam->id, 'user_id' => $student->id],
            [
                'extra_attempts' => $extraAttempts,
                'reason' => $request->reason,
                'granted_by' => auth()->id(),
            ]
        );

        $totalAllowed = (int) ($exam->max_attempts ?? 1) + $extraAttempts;

        return redirect()->back()->with('success', "บันทึกสิทธิ์การสอบสำหรับ [{$student->student_code}] {$student->name} เรียบร้อยแล้ว (สิทธิ์สอบรวม: {$totalAllowed} ครั้ง)");
    }

    public function reset(Exam $exam, User $student)
    {
        $this->authorizeExam($exam);

        ExamStudentOverride::where('exam_id', $exam->id)
            ->where('user_id', $student->id)
            ->delete();

        return redirect()->back()->with('success', "รีเซ็ตสิทธิ์สอบเพิ่มสำหรับ [{$student->student_code}] {$student->name} กลับเป็นค่าเริ่มต้นเรียบร้อยแล้ว");
    }

    public function bulkUpdate(Request $request, Exam $exam)
    {
        $this->authorizeExam($exam);

        $request->validate([
            'student_ids' => ['required', 'array'],
            'student_ids.*' => ['integer', 'exists:users,id'],
            'action' => ['required', 'in:add_one,reset'],
            'bulk_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $studentIds = $request->student_ids;
        $action = $request->action;

        if ($action === 'add_one') {
            $reason = $request->bulk_reason ?: 'เปิดให้สอบเพิ่ม/สอบซ่อม (แบบกลุ่ม)';
            foreach ($studentIds as $studentId) {
                $override = ExamStudentOverride::firstOrNew([
                    'exam_id' => $exam->id,
                    'user_id' => $studentId,
                ]);
                $override->extra_attempts = ($override->extra_attempts ?? 0) + 1;
                $override->reason = $reason;
                $override->granted_by = auth()->id();
                $override->save();
            }

            $count = count($studentIds);
            return redirect()->back()->with('success', "เปิดให้สอบเพิ่ม (+1 ครั้ง) สำหรับนักศึกษา {$count} คนเรียบร้อยแล้ว");
        } elseif ($action === 'reset') {
            ExamStudentOverride::where('exam_id', $exam->id)
                ->whereIn('user_id', $studentIds)
                ->delete();

            $count = count($studentIds);
            return redirect()->back()->with('success', "รีเซ็ตสิทธิ์สอบเพิ่มสำหรับนักศึกษา {$count} คนเรียบร้อยแล้ว");
        }

        return redirect()->back();
    }

    public function destroyAttempt(ExamAttempt $attempt)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถลบผลการสอบได้');
        }

        $userId = $attempt->user_id;
        $examId = $attempt->exam_id;
        $round = $attempt->attempt_number;
        $studentName = $attempt->user ? "[{$attempt->user->student_code}] {$attempt->user->name}" : 'นักศึกษา';

        // Delete answers and attempt
        $attempt->studentAnswers()->delete();
        $attempt->delete();

        // Re-index remaining attempts for this student & exam in chronological order
        $remainingAttempts = ExamAttempt::where('user_id', $userId)
            ->where('exam_id', $examId)
            ->orderBy('started_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $newRound = 1;
        foreach ($remainingAttempts as $rem) {
            if ($rem->attempt_number !== $newRound) {
                $rem->attempt_number = $newRound;
                $rem->save();
            }
            $newRound++;
        }

        return redirect()->back()->with('success', "ลบผลการสอบรอบที่ {$round} ของ {$studentName} เรียบร้อยแล้ว");
    }

    public function destroyStudentAttempts(Exam $exam, User $student)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถลบผลการสอบได้');
        }

        $attempts = ExamAttempt::where('exam_id', $exam->id)
            ->where('user_id', $student->id)
            ->get();

        $count = $attempts->count();

        foreach ($attempts as $attempt) {
            $attempt->studentAnswers()->delete();
            $attempt->delete();
        }

        return redirect()->back()->with('success', "ลบผลการสอบทั้งหมด ({$count} ครั้ง) ของ [{$student->student_code}] {$student->name} เรียบร้อยแล้ว");
    }
}
