<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\User;
use App\Models\Classroom;
use App\Models\Department;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // User metrics
        $studentCount = User::where('role', 'student')->count();
        $teacherCount = User::where('role', 'teacher')->count();
        $adminCount = User::where('role', 'admin')->count();
        $classroomCount = Classroom::count();
        $departmentCount = Department::count();

        // Subject metrics
        $subjectCount = Subject::count();

        // Exam metrics
        $examTotal = Exam::count();
        $activeExamsCount = Exam::where('is_active', true)->where('approval_status', 'approved')->count();
        $approvedExamsCount = Exam::where('approval_status', 'approved')->count();
        $pendingApprovalsCount = Exam::whereIn('approval_status', ['pending_dept', 'pending_eval', 'pending_academic'])->count();
        $draftExamsCount = Exam::where('approval_status', 'draft')->count();

        // Attempt metrics
        $completedAttemptsQuery = ExamAttempt::where('status', 'completed');
        $totalAttempts = (clone $completedAttemptsQuery)->count();
        $passedAttempts = (clone $completedAttemptsQuery)->where('is_passed', true)->count();
        $passRate = $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 1) : 0;
        $todayAttemptsCount = (clone $completedAttemptsQuery)->whereDate('completed_at', today())->count();

        $stats = [
            'subjects' => $subjectCount,
            'exams' => $examTotal,
            'users' => User::count(),
            'students' => $studentCount,
            'teachers' => $teacherCount,
            'admins' => $adminCount,
            'classrooms' => $classroomCount,
            'departments' => $departmentCount,
            'active_exams' => $activeExamsCount,
            'approved_exams' => $approvedExamsCount,
            'pending_approvals' => $pendingApprovalsCount,
            'draft_exams' => $draftExamsCount,
            'attempts' => $totalAttempts,
            'today_attempts' => $todayAttemptsCount,
            'pass_rate' => $passRate,
        ];

        // Active exams currently open for students
        $activeExams = Exam::with(['subject'])
            ->withCount([
                'questions',
                'examAttempts as completed_attempts_count' => function ($q) {
                    $q->where('status', 'completed');
                }
            ])
            ->where('is_active', true)
            ->where('approval_status', 'approved')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        // Pending approval exams (if any)
        $pendingExams = Exam::with(['subject'])
            ->whereIn('approval_status', ['pending_dept', 'pending_eval', 'pending_academic'])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        // Recent completed attempts with student, classroom, and exam
        $recentAttempts = ExamAttempt::with(['user.classroom', 'exam.subject'])
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentAttempts', 'activeExams', 'pendingExams'));
    }
}

