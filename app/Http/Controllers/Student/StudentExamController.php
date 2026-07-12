<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Choice;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentExamController extends Controller
{
    public function index()
    {
        $student = Auth::user();
        
        // List active exams that the student can take (only for enrolled subjects)
        $enrolledSubjectIds = $student->enrolledSubjects()->pluck('subjects.id');

        $exams = Exam::where('is_active', true)
            ->whereIn('subject_id', $enrolledSubjectIds)
            ->with('subject')
            ->withCount('questions')
            ->orderBy('created_at', 'desc')
            ->get();

        // Previous attempts
        $attempts = ExamAttempt::where('user_id', $student->id)
            ->with('exam.subject')
            ->orderBy('completed_at', 'desc')
            ->get();

        return view('student.dashboard', compact('exams', 'attempts'));
    }

    public function lobby(Exam $exam)
    {
        if (!$exam->is_active) {
            abort(404, 'ข้อสอบนี้ไม่เปิดให้เข้าทำ');
        }

        $hasPassed = ExamAttempt::where('user_id', Auth::id())
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->where('is_passed', true)
            ->exists();

        if ($hasPassed) {
            return redirect()->route('student.dashboard')->with('error', 'คุณผ่านเกณฑ์การสอบวิชานี้เรียบร้อยแล้ว ไม่จำเป็นต้องสอบซ่อม');
        }

        $completedAttemptsCount = ExamAttempt::where('user_id', Auth::id())
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->count();

        if ($completedAttemptsCount >= $exam->max_attempts) {
            return redirect()->route('student.dashboard')->with('error', 'คุณทำข้อสอบนี้ครบกำหนดจำนวนครั้งแล้ว (' . $exam->max_attempts . ' ครั้ง)');
        }

        $exam->load('subject')->loadCount('questions');
        return view('student.exam.lobby', compact('exam'));
    }

    public function start(Request $request, Exam $exam)
    {
        if (!$exam->is_active) {
            abort(404, 'ข้อสอบนี้ไม่เปิดให้เข้าทำ');
        }

        $student = Auth::user();

        $hasPassed = ExamAttempt::where('user_id', $student->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->where('is_passed', true)
            ->exists();

        if ($hasPassed) {
            return redirect()->route('student.dashboard')->with('error', 'คุณผ่านเกณฑ์การสอบวิชานี้เรียบร้อยแล้ว ไม่จำเป็นต้องสอบซ่อม');
        }

        $completedAttemptsCount = ExamAttempt::where('user_id', $student->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->count();

        if ($completedAttemptsCount >= $exam->max_attempts) {
            return redirect()->route('student.dashboard')->with('error', 'คุณทำข้อสอบนี้ครบกำหนดจำนวนครั้งแล้ว');
        }

        // If there's an existing in-progress attempt, redirect to it instead of creating a new one
        $existingAttempt = ExamAttempt::where('user_id', $student->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingAttempt) {
            return redirect()->route('student.exam.take', [$exam->id, $existingAttempt->id]);
        }

        // Validate passcode if set
        if ($exam->passcode) {
            $request->validate([
                'passcode' => ['required', 'string'],
            ], [
                'passcode.required' => 'กรุณากรอกรหัสผ่านเข้าห้องสอบ',
            ]);

            if ($request->passcode !== $exam->passcode) {
                return redirect()->back()->withErrors(['passcode' => 'รหัสผ่านเข้าห้องสอบไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง หรือติดต่ออาจารย์ผู้สอน']);
            }
        }

        // Create new attempt
        $attempt = ExamAttempt::create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'started_at' => now(),
            'total_questions' => $exam->questions()->count(),
            'status' => 'in_progress',
        ]);

        return redirect()->route('student.exam.take', [$exam->id, $attempt->id]);
    }

    public function take(Exam $exam, ExamAttempt $attempt)
    {
        // Safety checks
        if ($attempt->user_id !== Auth::id() || $attempt->exam_id !== $exam->id) {
            abort(403, 'เข้าถึงส่วนนี้ไม่ได้');
        }

        if ($attempt->status === 'completed') {
            return redirect()->route('student.exam.result', $attempt->id)
                ->with('error', 'ข้อสอบนี้ถูกส่งไปเรียบร้อยแล้ว');
        }

        // Calculate time remaining
        $started = $attempt->started_at;
        $duration = $exam->duration_minutes;
        $expiresAt = $started->copy()->addMinutes($duration);
        $timeRemainingSeconds = $expiresAt->timestamp - now()->timestamp;

        // If time is up, auto-submit
        if ($timeRemainingSeconds <= 0) {
            return $this->submit($attempt);
        }

        // If focus escape limit exceeded, auto-submit
        if ($exam->max_focus_escapes > 0 && $attempt->focus_escape_count >= $exam->max_focus_escapes) {
            return $this->submit($attempt);
        }

        // Load questions and choices sorted by section sort order, section ID, and question ID
        $questions = Question::where('questions.exam_id', $exam->id)
            ->leftJoin('exam_sections', 'questions.exam_section_id', '=', 'exam_sections.id')
            ->select('questions.*')
            ->orderByRaw('COALESCE(exam_sections.sort_order, 99999) ASC')
            ->orderByRaw('COALESCE(exam_sections.id, 99999) ASC')
            ->orderBy('questions.id', 'ASC')
            ->with(['examSection', 'choices' => function($query) {
                // Select only id, question_id, choice_text, choice_image to avoid leaking is_correct via frontend
                $query->select('id', 'question_id', 'choice_text', 'choice_image');
            }])
            ->get();

        // Stable shuffle within each section using attempt ID + section ID as seed so refreshes do not change the shuffled order
        if ($exam->shuffle_questions) {
            $grouped = $questions->groupBy('exam_section_id');
            $shuffled = collect();
            foreach ($grouped as $sectId => $sectQuestions) {
                $shuffled = $shuffled->concat($sectQuestions->shuffle($attempt->id + ($sectId ?? 0)));
            }
            $questions = $shuffled;
        }

        if ($exam->shuffle_choices) {
            $questions->each(function($question) use ($attempt) {
                // Seed with attempt ID + question ID to keep it stable per question
                $question->setRelation('choices', $question->choices->shuffle($attempt->id + $question->id));
            });
        }

        // Get student's current saved answers
        $savedAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereNotNull('choice_id')
            ->pluck('choice_id', 'question_id')
            ->toArray();

        $savedTextAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereNotNull('answer_text')
            ->pluck('answer_text', 'question_id')
            ->toArray();

        return view('student.exam.take', compact('exam', 'attempt', 'questions', 'savedAnswers', 'savedTextAnswers', 'timeRemainingSeconds'));
    }

    public function saveAnswer(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->user_id !== Auth::id() || $attempt->status !== 'in_progress') {
            return response()->json(['error' => 'Unauthorized or exam already completed'], 403);
        }

        $request->validate([
            'question_id' => ['required', 'exists:questions,id'],
            'choice_id' => ['nullable', 'exists:choices,id'],
            'answer_text' => ['nullable', 'string'],
        ]);

        $questionId = $request->question_id;
        $question = Question::findOrFail($questionId);

        if ($question->type === 'essay') {
            if ($request->filled('answer_text')) {
                $isCorrect = false;
                if ($question->essay_answer !== null) {
                    $studentAnswerText = trim($request->answer_text);
                    $expectedAnswerText = trim($question->essay_answer);
                    // Case-insensitive comparison
                    $isCorrect = (mb_strtolower($studentAnswerText) === mb_strtolower($expectedAnswerText));
                }

                StudentAnswer::updateOrCreate(
                    ['exam_attempt_id' => $attempt->id, 'question_id' => $questionId],
                    [
                        'choice_id' => null,
                        'answer_text' => $request->answer_text,
                        'is_correct' => $isCorrect
                    ]
                );
            } else {
                StudentAnswer::where('exam_attempt_id', $attempt->id)
                    ->where('question_id', $questionId)
                    ->delete();
            }
            return response()->json(['success' => true]);
        }

        // Standard choice processing
        $choiceId = $request->choice_id;

        // If choice_id is null, delete answer
        if (empty($choiceId)) {
            StudentAnswer::where('exam_attempt_id', $attempt->id)
                ->where('question_id', $questionId)
                ->delete();
            return response()->json(['success' => true, 'cleared' => true]);
        }

        // Validate choice belongs to the question
        $choice = Choice::where('id', $choiceId)
            ->where('question_id', $questionId)
            ->first();

        if (!$choice) {
            return response()->json(['error' => 'Invalid choice for this question'], 422);
        }

        // Save or update answer
        $studentAnswer = StudentAnswer::updateOrCreate(
            ['exam_attempt_id' => $attempt->id, 'question_id' => $questionId],
            [
                'choice_id' => $choice->id,
                'answer_text' => null,
                'is_correct' => $choice->is_correct
            ]
        );

        return response()->json(['success' => true]);
    }

    public function recordFocusEscape(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== Auth::id() || $attempt->status !== 'in_progress') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $attempt->increment('focus_escape_count');
        $attempt->refresh();

        return response()->json([
            'success' => true,
            'focus_escape_count' => $attempt->focus_escape_count,
        ]);
    }

    public function sectionScore(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->user_id !== Auth::id() || $attempt->status !== 'in_progress') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate(['section_id' => ['required']]);
        $sectionId = $request->section_id === 'null' ? null : $request->section_id;

        // Get all questions in this section
        $questions = Question::where('exam_id', $attempt->exam_id)
            ->where('exam_section_id', $sectionId)
            ->get();

        $questionIds   = $questions->pluck('id');
        $questionCount = $questions->count();
        $totalScore    = $questions->sum('score');

        // Get student answers for this section
        $studentAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereIn('question_id', $questionIds)
            ->get();

        $answeredCount = $studentAnswers->count();
        $earnedScore   = 0;
        foreach ($studentAnswers as $ans) {
            if ($ans->is_correct) {
                $q = $questions->firstWhere('id', $ans->question_id);
                if ($q) $earnedScore += $q->score;
            }
        }

        return response()->json([
            'success'        => true,
            'earned'         => $earnedScore,
            'total'          => $totalScore,
            'answered'       => $answeredCount,
            'question_count' => $questionCount,
        ]);
    }

    public function submitAttempt(ExamAttempt $attempt)
    {
        return $this->submit($attempt);
    }

    private function submit(ExamAttempt $attempt)
    {
        if ($attempt->status === 'completed') {
            return redirect()->route('student.exam.result', $attempt->id);
        }

        $attempt->load('exam.questions');
        $exam = $attempt->exam;

        // Calculate score
        $studentAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)->get();
        $score = 0;
        
        foreach ($studentAnswers as $ans) {
            if ($ans->is_correct) {
                // Find question score
                $question = $exam->questions->firstWhere('id', $ans->question_id);
                if ($question) {
                    $score += $question->score;
                }
            }
        }

        // Total possible score
        $totalScore = $exam->questions->sum('score');

        // Check if passed
        $passingPercentage = $exam->passing_percentage;
        $percentageObtained = $totalScore > 0 ? ($score / $totalScore) * 100 : 0;
        $isPassed = $percentageObtained >= $passingPercentage;

        // Update attempt
        $attempt->update([
            'completed_at' => now(),
            'score' => $score,
            'is_passed' => $isPassed,
            'status' => 'completed',
        ]);

        session()->flash('just_submitted', true);

        return redirect()->route('student.exam.result', $attempt->id)
            ->with('success', 'ส่งข้อสอบและบันทึกคะแนนเรียบร้อยแล้ว');
    }

    public function result(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $attempt->load(['exam.subject', 'exam.questions' => function($q) {
            $q->leftJoin('exam_sections', 'questions.exam_section_id', '=', 'exam_sections.id')
              ->select('questions.*')
              ->orderByRaw('COALESCE(exam_sections.sort_order, 99999) ASC')
              ->orderByRaw('COALESCE(exam_sections.id, 99999) ASC')
              ->orderBy('questions.id', 'ASC');
        }, 'exam.questions.examSection', 'exam.questions.choices']);
        $exam = $attempt->exam;

        // If allow_review is disabled and the student didn't just submit the exam, block review
        if (!$exam->allow_review && !session('just_submitted')) {
            return redirect()->route('student.dashboard')->with('error', 'ผู้สอนไม่อนุญาตให้ดูผลการสอบและเฉลยย้อนหลัง');
        }
        
        $savedAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereNotNull('choice_id')
            ->pluck('choice_id', 'question_id')
            ->toArray();

        $savedTextAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereNotNull('answer_text')
            ->pluck('answer_text', 'question_id')
            ->toArray();

        $correctness = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->pluck('is_correct', 'question_id')
            ->toArray();

        return view('student.exam.result', compact('attempt', 'savedAnswers', 'savedTextAnswers', 'correctness'));
    }
}
