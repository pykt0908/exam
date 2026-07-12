<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\User;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'subjects' => Subject::count(),
            'exams' => Exam::count(),
            'users' => User::count(),
            'attempts' => ExamAttempt::where('status', 'completed')->count(),
        ];

        $recentAttempts = ExamAttempt::with(['user', 'exam'])
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentAttempts'));
    }
}
