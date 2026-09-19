<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use App\Models\Subject;
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

        // 1. Subjects query (accessible to current user)
        $subjectsQuery = Subject::with('department')->withCount(['exams'])->orderBy('code');
        if ($user->isTeacher()) {
            $subjectsQuery->whereHas('teachers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }
        $subjects = $subjectsQuery->get();

        // Pending attempts count per subject for Step 1 badges
        $pendingCountsBySubject = ExamAttempt::where('status', 'completed')
            ->where('grading_status', 'pending_grading')
            ->join('exams', 'exam_attempts.exam_id', '=', 'exams.id')
            ->groupBy('exams.subject_id')
            ->selectRaw('exams.subject_id, count(*) as count')
            ->pluck('count', 'subject_id');

        // 2. Read filter inputs
        $subjectId = $request->filled('subject_id') ? (int) $request->subject_id : null;
        $examId = $request->filled('exam_id') ? (int) $request->exam_id : null;
        $classroomId = $request->filled('classroom_id') ? (int) $request->classroom_id : null;

        // If teacher, make sure selected subject belongs to teacher
        if ($subjectId && $user->isTeacher()) {
            if (!$subjects->contains('id', $subjectId)) {
                $subjectId = null;
            }
        }

        // If exam_id is specified, validate and sync with subject
        if ($examId) {
            $examCheck = Exam::find($examId);
            if ($examCheck) {
                if ($user->isTeacher()) {
                    $teaches = $examCheck->subject && $examCheck->subject->teachers()->where('users.id', $user->id)->exists();
                    if (!$teaches) {
                        $examId = null;
                        $examCheck = null;
                    }
                }
                if ($examCheck) {
                    if (!$subjectId) {
                        $subjectId = $examCheck->subject_id;
                    } elseif ($subjectId !== $examCheck->subject_id) {
                        // User selected a subject that doesn't match this exam, reset examId
                        $examId = null;
                    }
                }
            } else {
                $examId = null;
            }
        }

        // Determine Current Step (1: เลือกรายวิชา, 2: เลือกชุดข้อสอบ, 3: ตรวจข้อสอบตามห้องเรียน)
        $currentStep = 1;
        if ($subjectId && !$examId) {
            $currentStep = 2;
        } elseif ($examId) {
            $currentStep = 3;
        }

        // 3. Exams query (filtered by subject_id if chosen)
        $examsQuery = Exam::orderBy('title')
            ->withCount([
                'examAttempts as pending_attempts_count' => function ($q) {
                    $q->where('status', 'completed')->where('grading_status', 'pending_grading');
                },
                'examAttempts as completed_attempts_count' => function ($q) {
                    $q->where('status', 'completed');
                },
                'questions'
            ]);

        if ($user->isTeacher()) {
            $examsQuery->whereHas('subject.teachers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }
        if ($subjectId) {
            $examsQuery->where('subject_id', $subjectId);
        }
        $exams = $examsQuery->get();

        // 4. Classrooms query: "กรองตามห้องที่มีสิทธิ์สอบข้อสอบนั้นได้"
        if ($examId) {
            $targetSubjectId = $subjectId;
            $classrooms = Classroom::where(function ($query) use ($targetSubjectId, $examId) {
                $query->whereHas('users', function ($q) use ($targetSubjectId) {
                    $q->where('role', 'student')->whereHas('enrolledSubjects', function ($q2) use ($targetSubjectId) {
                        $q2->where('subjects.id', $targetSubjectId);
                    });
                })->orWhereHas('users.examAttempts', function ($a) use ($examId) {
                    $a->where('exam_id', $examId)->where('status', 'completed');
                });
            })->orderBy('name')->get();
        } elseif ($subjectId) {
            $classrooms = Classroom::whereHas('users', function ($q) use ($subjectId) {
                $q->where('role', 'student')->whereHas('enrolledSubjects', function ($q2) use ($subjectId) {
                    $q2->where('subjects.id', $subjectId);
                });
            })->orderBy('name')->get();
        } else {
            if ($user->isTeacher()) {
                $classrooms = Classroom::whereHas('users', function ($q) use ($user) {
                    $q->where('role', 'student')->whereHas('enrolledSubjects.teachers', function ($q2) use ($user) {
                        $q2->where('users.id', $user->id);
                    });
                })->orderBy('name')->get();
            } else {
                $classrooms = Classroom::orderBy('name')->get();
            }
        }

        // Reset classroomId if not among eligible classrooms
        if ($classroomId && !$classrooms->contains('id', $classroomId)) {
            $classroomId = null;
        }

        // 5. Base query for completed attempts
        $baseQuery = ExamAttempt::with(['user.classroom', 'exam.subject'])
            ->where('status', 'completed');

        if ($user->isTeacher()) {
            $baseQuery->whereHas('exam.subject.teachers', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        // 6. Build filter query (for stats and list)
        $filterQuery = clone $baseQuery;

        if ($examId) {
            $filterQuery->where('exam_id', $examId);
        } elseif ($subjectId) {
            $filterQuery->whereHas('exam', function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId);
            });
        }

        if ($classroomId) {
            $filterQuery->whereHas('user', function ($q) use ($classroomId) {
                $q->where('classroom_id', $classroomId);
            });
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $filterQuery->whereHas('user', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('student_code', 'like', "%{$q}%");
            });
        }

        // 7. Stats
        $pendingCount = (clone $filterQuery)->where('grading_status', 'pending_grading')->count();
        $gradedCount = (clone $filterQuery)->where('grading_status', 'graded')->count();
        $totalCount = (clone $filterQuery)->count();

        // 8. Apply grading status filter
        $attemptsQuery = clone $filterQuery;
        $statusParam = $request->input('grading_status');

        if ($request->filled('grading_status')) {
            if ($statusParam !== 'all') {
                $attemptsQuery->where('grading_status', $statusParam);
            }
        } else {
            // Default filter: if there are pending attempts, show pending first
            if ($pendingCount > 0) {
                $attemptsQuery->where('grading_status', 'pending_grading');
            }
        }

        $attempts = $attemptsQuery->orderBy('completed_at', 'desc')
            ->paginate(25)
            ->appends($request->query());

        return view('admin.grading.index', compact(
            'attempts',
            'subjects',
            'exams',
            'classrooms',
            'subjectId',
            'examId',
            'classroomId',
            'currentStep',
            'pendingCountsBySubject',
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
                    ->orderByRaw('COALESCE(questions.sort_order, questions.id) ASC')
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
            $attempt->load(['exam.questions', 'exam.sections']);
            $questions = $attempt->exam->questions;

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
            }

            $attempt->grading_status = 'graded';
            $calc = $attempt->calculateFinalScore();

            $attempt->update([
                'score' => $calc['score'],
                'raw_score' => $calc['raw_score'],
                'total_raw_score' => $calc['total_raw_score'],
                'is_passed' => $calc['is_passed'],
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
