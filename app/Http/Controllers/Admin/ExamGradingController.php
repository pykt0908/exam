<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamGradingController extends Controller
{
    /**
     * Display a listing of exam attempts for grading.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Base query for exams accessible to the user
        $examsQuery = Exam::orderBy('title');
        if ($user->isTeacher()) {
            $examsQuery->whereHas('subject.teachers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }
        $exams = $examsQuery->get();

        // Base query for completed attempts
        $baseQuery = ExamAttempt::with(['user.classroom', 'exam.subject'])
            ->where('status', 'completed');

        if ($user->isTeacher()) {
            $baseQuery->whereHas('exam.subject.teachers', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // Stats
        $pendingCount = (clone $baseQuery)->where('grading_status', 'pending_grading')->count();
        $gradedCount = (clone $baseQuery)->where('grading_status', 'graded')->count();
        $totalCount = (clone $baseQuery)->count();

        // Filters
        $attemptsQuery = clone $baseQuery;

        if ($request->filled('exam_id')) {
            $attemptsQuery->where('exam_id', $request->exam_id);
        }

        if ($request->filled('grading_status')) {
            if ($request->grading_status !== 'all') {
                $attemptsQuery->where('grading_status', $request->grading_status);
            }
        } else {
            // Default filter: if there are pending attempts, show pending first
            if ($pendingCount > 0) {
                $attemptsQuery->where('grading_status', 'pending_grading');
            }
        }

        if ($request->filled('classroom_id')) {
            $classroomId = $request->classroom_id;
            $attemptsQuery->whereHas('user', function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId);
            });
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $attemptsQuery->whereHas('user', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('student_code', 'like', "%{$q}%");
            });
        }

        $attempts = $attemptsQuery->orderBy('completed_at', 'desc')->paginate(25);
        $classrooms = Classroom::orderBy('name')->get();

        return view('admin.grading.index', compact(
            'attempts',
            'exams',
            'classrooms',
            'pendingCount',
            'gradedCount',
            'totalCount'
        ));
    }

    /**
     * Show the grading interface for a specific exam attempt.
     */
    public function show(ExamAttempt $attempt)
    {
        $user = auth()->user();

        // Authorization check for teachers
        if ($user->isTeacher()) {
            $teaches = $attempt->exam->subject->teachers()->where('users.id', $user->id)->exists();
            if (!$teaches) {
                abort(403, 'คุณไม่มีสิทธิ์เข้าถึงการตรวจข้อสอบชุดนี้');
            }
        }

        // Load attempt relationships
        $attempt->load([
            'user.classroom',
            'exam.subject',
            'exam.questions' => function ($q) {
                $q->leftJoin('exam_sections', 'questions.exam_section_id', '=', 'exam_sections.id')
                    ->select('questions.*')
                    ->orderByRaw('COALESCE(exam_sections.sort_order, 99999) ASC')
                    ->orderByRaw('COALESCE(exam_sections.id, 99999) ASC')
                    ->orderBy('questions.id', 'ASC');
            },
            'exam.questions.examSection',
            'exam.questions.choices',
            'studentAnswers.choice',
        ]);

        $studentAnswers = $attempt->studentAnswers->keyBy('question_id');

        // Find next attempt pending grading for the same exam
        $nextAttempt = ExamAttempt::where('exam_id', $attempt->exam_id)
            ->where('status', 'completed')
            ->where('grading_status', 'pending_grading')
            ->where('id', '!=', $attempt->id)
            ->first();

        // Sibling attempts list for quick switcher dropdown
        $allAttempts = ExamAttempt::with('user')
            ->where('exam_id', $attempt->exam_id)
            ->where('status', 'completed')
            ->orderBy('completed_at', 'asc')
            ->get();

        $currentIndex = $allAttempts->search(fn($a) => $a->id === $attempt->id);
        $prevAttempt = ($currentIndex !== false && $currentIndex > 0) ? $allAttempts->get($currentIndex - 1) : null;
        $nextInListAttempt = ($currentIndex !== false && $currentIndex < $allAttempts->count() - 1) ? $allAttempts->get($currentIndex + 1) : null;

        return view('admin.grading.show', compact(
            'attempt',
            'studentAnswers',
            'nextAttempt',
            'allAttempts',
            'currentIndex',
            'prevAttempt',
            'nextInListAttempt'
        ));
    }

    /**
     * Update scores and feedback for an exam attempt.
     */
    public function update(Request $request, ExamAttempt $attempt)
    {
        $user = auth()->user();

        // Authorization check for teachers
        if ($user->isTeacher()) {
            $teaches = $attempt->exam->subject->teachers()->where('users.id', $user->id)->exists();
            if (!$teaches) {
                abort(403, 'คุณไม่มีสิทธิ์เข้าถึงการตรวจข้อสอบชุดนี้');
            }
        }

        $request->validate([
            'scores' => 'nullable|array',
            'scores.*' => 'nullable|numeric|min:0',
            'comments' => 'nullable|array',
            'comments.*' => 'nullable|string|max:1000',
            'action' => 'nullable|string|in:save,save_and_next',
        ]);

        DB::transaction(function () use ($request, $attempt) {
            $scores = $request->input('scores', []);
            $comments = $request->input('comments', []);
            $attempt->load('exam.questions');
            $questions = $attempt->exam->questions;

            $rawScore = 0;

            foreach ($questions as $question) {
                $answer = StudentAnswer::firstOrNew([
                    'exam_attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                ]);

                if ($question->type === 'essay') {
                    // Score logic for essay
                    $maxScore = (float)$question->score;
                    if (isset($scores[$question->id])) {
                        $awarded = min((float)$scores[$question->id], $maxScore);
                        $awarded = max(0, $awarded);
                    } else {
                        $awarded = $answer->score_awarded ?? 0.0;
                    }

                    $answer->score_awarded = $awarded;
                    $answer->is_correct = ($maxScore > 0 && $awarded >= $maxScore);

                    if (array_key_exists($question->id, $comments)) {
                        $answer->teacher_feedback = $comments[$question->id];
                    }

                    $answer->save();
                } else {
                    // Choice questions
                    if ($answer->score_awarded === null) {
                        $answer->score_awarded = $answer->is_correct ? (float)$question->score : 0.0;
                        $answer->save();
                    }
                }

                $rawScore += ($answer->score_awarded ?? 0.0);
            }

            $totalRawScore = (float)$questions->sum('score');
            $examTargetScore = (float)($attempt->exam->total_score ?? $totalRawScore);

            $finalScore = ($totalRawScore > 0 && $examTargetScore > 0)
                ? round(($rawScore / $totalRawScore) * $examTargetScore, 2)
                : round($rawScore, 2);

            $passingPercentage = $attempt->exam->passing_percentage;
            $percentageObtained = $examTargetScore > 0 ? ($finalScore / $examTargetScore) * 100 : 0;
            $isPassed = $percentageObtained >= $passingPercentage;

            $attempt->update([
                'score' => $finalScore,
                'raw_score' => $rawScore,
                'total_raw_score' => $totalRawScore,
                'is_passed' => $isPassed,
                'grading_status' => 'graded',
            ]);
        });

        if ($request->input('action') === 'save_and_next') {
            $nextAttempt = ExamAttempt::where('exam_id', $attempt->exam_id)
                ->where('status', 'completed')
                ->where('grading_status', 'pending_grading')
                ->where('id', '!=', $attempt->id)
                ->first();

            if ($nextAttempt) {
                return redirect()->route('admin.grading.show', $nextAttempt->id)
                    ->with('success', 'บันทึกคะแนนเรียบร้อยแล้ว และเปิดชุดถัดไปที่รอตรวจ');
            }
        }

        return redirect()->route('admin.grading.show', $attempt->id)
            ->with('success', 'บันทึกผลการตรวจและคำนวณคะแนนรวมเรียบร้อยแล้ว');
    }
}
