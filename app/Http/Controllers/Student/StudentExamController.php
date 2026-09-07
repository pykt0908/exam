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
        $student->load('enrolledSubjects');
        
        // List active exams that the student can take (only for enrolled subjects)
        $enrolledSubjectIds = $student->enrolledSubjects->pluck('id');

        $exams = Exam::where('is_active', true)
            ->where('approval_status', 'approved')
            ->whereIn('subject_id', $enrolledSubjectIds)
            ->with('subject')
            ->withCount('questions')
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 1 ELSE 0 END, starts_at ASC, id ASC')
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
        if (!$exam->is_active || !$exam->isApproved()) {
            abort(404, 'ข้อสอบนี้ยังไม่เปิดให้เข้าทำหรือไม่ผ่านการอนุมัติ');
        }

        $student = Auth::user();
        $student->load('enrolledSubjects');
        $eligibility = $student->getExamEligibility($exam);
        if (!$eligibility['eligible']) {
            return redirect()->route('student.dashboard')->with('error', 'คุณไม่มีสิทธิ์เข้าสอบวิชานี้ (' . ($eligibility['reason'] ?: 'ถูกระงับสิทธิ์') . ') กรุณาติดต่ออาจารย์ผู้สอนหรือฝ่ายการเงิน/ทะเบียน');
        }

        if ($exam->isUpcoming()) {
            return redirect()->route('student.dashboard')->with('error', 'ยังไม่ถึงกำหนดเวลาสอบ ข้อสอบจะเปิดให้เข้าทำในวันที่ ' . $exam->starts_at->format('d/m/Y H:i น.'));
        }

        if ($exam->isExpired()) {
            return redirect()->route('student.dashboard')->with('error', 'หมดเวลาการทำข้อสอบชุดนี้แล้ว (ปิดระบบเมื่อ ' . $exam->ends_at->format('d/m/Y H:i น.') . ')');
        }

        $completedAttemptsCount = ExamAttempt::where('user_id', Auth::id())
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->count();

        $allowedAttempts = $exam->getAllowedAttemptsForUser(Auth::id());
        $extraAttempts = $exam->getExtraAttemptsForUser(Auth::id());

        $hasPassed = ExamAttempt::where('user_id', Auth::id())
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->where('is_passed', true)
            ->exists();

        if ($completedAttemptsCount >= $allowedAttempts) {
            if ($hasPassed) {
                return redirect()->route('student.dashboard')->with('error', 'คุณผ่านเกณฑ์การสอบวิชานี้เรียบร้อยแล้ว');
            }
            return redirect()->route('student.dashboard')->with('error', 'คุณทำข้อสอบนี้ครบกำหนดจำนวนครั้งแล้ว (' . $allowedAttempts . ' ครั้ง) หากต้องการสอบแก้ตัว กรุณาติดต่ออาจารย์ผู้สอน');
        }

        if ($hasPassed && $extraAttempts === 0) {
            return redirect()->route('student.dashboard')->with('error', 'คุณผ่านเกณฑ์การสอบวิชานี้เรียบร้อยแล้ว ไม่จำเป็นต้องสอบซ่อม');
        }

        $exam->load('subject')->loadCount('questions');
        return view('student.exam.lobby', compact('exam', 'completedAttemptsCount', 'allowedAttempts'));
    }

    public function start(Request $request, Exam $exam)
    {
        if (!$exam->is_active || !$exam->isApproved()) {
            abort(404, 'ข้อสอบนี้ยังไม่เปิดให้เข้าทำหรือไม่ผ่านการอนุมัติ');
        }

        $student = Auth::user();
        $student->load('enrolledSubjects');
        $eligibility = $student->getExamEligibility($exam);
        if (!$eligibility['eligible']) {
            return redirect()->route('student.dashboard')->with('error', 'คุณไม่มีสิทธิ์เข้าสอบวิชานี้ (' . ($eligibility['reason'] ?: 'ถูกระงับสิทธิ์') . ') กรุณาติดต่ออาจารย์ผู้สอนหรือฝ่ายการเงิน/ทะเบียน');
        }

        if ($exam->isUpcoming()) {
            return redirect()->route('student.dashboard')->with('error', 'ยังไม่ถึงกำหนดเวลาสอบ ข้อสอบจะเปิดให้เข้าทำในวันที่ ' . $exam->starts_at->format('d/m/Y H:i น.'));
        }

        if ($exam->isExpired()) {
            return redirect()->route('student.dashboard')->with('error', 'หมดเวลาการทำข้อสอบชุดนี้แล้ว (ปิดระบบเมื่อ ' . $exam->ends_at->format('d/m/Y H:i น.') . ')');
        }

        $completedAttemptsCount = ExamAttempt::where('user_id', $student->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->count();

        $allowedAttempts = $exam->getAllowedAttemptsForUser($student->id);
        $extraAttempts = $exam->getExtraAttemptsForUser($student->id);

        $hasPassed = ExamAttempt::where('user_id', $student->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'completed')
            ->where('is_passed', true)
            ->exists();

        if ($completedAttemptsCount >= $allowedAttempts) {
            if ($hasPassed) {
                return redirect()->route('student.dashboard')->with('error', 'คุณผ่านเกณฑ์การสอบวิชานี้เรียบร้อยแล้ว');
            }
            return redirect()->route('student.dashboard')->with('error', 'คุณทำข้อสอบนี้ครบกำหนดจำนวนครั้งแล้ว');
        }

        if ($hasPassed && $extraAttempts === 0) {
            return redirect()->route('student.dashboard')->with('error', 'คุณผ่านเกณฑ์การสอบวิชานี้เรียบร้อยแล้ว ไม่จำเป็นต้องสอบซ่อม');
        }

        // If there's an existing in-progress attempt, redirect to it instead of creating a new one
        $existingAttempt = ExamAttempt::where('user_id', $student->id)
            ->where('exam_id', $exam->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingAttempt) {
            session()->put('exam_just_started_' . $existingAttempt->id, true);
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

        // Determine attempt number for this student and exam
        $attemptNumber = ExamAttempt::where('user_id', $student->id)
            ->where('exam_id', $exam->id)
            ->count() + 1;

        // Create new attempt
        $attempt = ExamAttempt::create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
            'total_questions' => $exam->questions()->count(),
            'status' => 'in_progress',
        ]);

        session()->put('exam_just_started_' . $attempt->id, true);

        return redirect()->route('student.exam.take', [$exam->id, $attempt->id]);
    }

    public function take(Exam $exam, ExamAttempt $attempt)
    {
        // Safety checks
        if ($attempt->user_id !== Auth::id() || $attempt->exam_id !== $exam->id) {
            abort(403, 'เข้าถึงส่วนนี้ไม่ได้');
        }

        $student = Auth::user();
        $student->load('enrolledSubjects');
        $eligibility = $student->getExamEligibility($exam);
        if (!$eligibility['eligible']) {
            return redirect()->route('student.dashboard')->with('error', 'คุณไม่มีสิทธิ์เข้าสอบวิชานี้ (' . ($eligibility['reason'] ?: 'ถูกระงับสิทธิ์') . ') กรุณาติดต่ออาจารย์ผู้สอนหรือฝ่ายการเงิน/ทะเบียน');
        }

        if ($attempt->status === 'completed') {
            return redirect()->route('student.exam.result', $attempt->id)
                ->with('error', 'ข้อสอบนี้ถูกส่งไปเรียบร้อยแล้ว');
        }

        // Calculate time remaining
        $started = $attempt->started_at;
        $duration = $exam->duration_minutes;
        $expiresAt = $started->copy()->addMinutes($duration);
        if ($exam->ends_at && $exam->ends_at->lt($expiresAt)) {
            $expiresAt = $exam->ends_at;
        }
        $timeRemainingSeconds = $expiresAt->timestamp - now()->timestamp;

        // If time is up, auto-submit
        if ($timeRemainingSeconds <= 0) {
            return $this->submit($attempt);
        }

        // Check if this is initial entry from lobby or a page refresh/re-entry
        $isInitialEntry = session()->pull('exam_just_started_' . $attempt->id, false);
        $wasRefreshed = false;

        if (!$isInitialEntry && $exam->max_focus_escapes > 0) {
            $lastAjaxEscape = session('last_ajax_focus_escape_at_' . $attempt->id, 0);
            // If an AJAX focus escape was already recorded within the last 3 seconds (e.g. from pagehide/visibilitychange right before reload), avoid double-counting
            if (now()->timestamp - $lastAjaxEscape >= 3) {
                $attempt->increment('focus_escape_count');
                $attempt->refresh();
            }
            $wasRefreshed = true;
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

        return view('student.exam.take', compact('exam', 'attempt', 'questions', 'savedAnswers', 'savedTextAnswers', 'timeRemainingSeconds', 'wasRefreshed'));
    }

    public function saveAnswer(Request $request, ExamAttempt $attempt)
    {
        if ($attempt->user_id !== Auth::id() || $attempt->status !== 'in_progress') {
            return response()->json(['error' => 'Unauthorized or exam already completed'], 403);
        }

        // Check if student exam eligibility is revoked in real-time
        $attempt->loadMissing('exam');
        $student = Auth::user()->fresh();
        $student->load('enrolledSubjects');
        $eligibility = $student->getExamEligibility($attempt->exam);
        if (!$eligibility['eligible']) {
            return response()->json([
                'ineligible' => true,
                'error' => 'สิทธิ์การสอบของคุณถูกระงับ (' . ($eligibility['reason'] ?: 'ติดต่อฝ่ายการเงิน/ทะเบียน') . ')',
                'reason' => $eligibility['reason'] ?: 'ถูกระงับสิทธิ์สอบ (ติดต่อฝ่ายการเงิน/ทะเบียน)'
            ], 403);
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

    public function checkEligibility(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'eligible' => false,
                'completed' => true,
                'reason' => 'การสอบชุดนี้เสร็จสิ้นหรือถูกส่งไปแล้ว',
                'message' => 'การสอบชุดนี้เสร็จสิ้นหรือถูกส่งไปแล้ว'
            ]);
        }

        $attempt->loadMissing('exam');
        $student = Auth::user()->fresh();
        $student->load('enrolledSubjects');
        $eligibility = $student->getExamEligibility($attempt->exam);

        if (!$eligibility['eligible']) {
            return response()->json([
                'eligible' => false,
                'reason' => $eligibility['reason'] ?: 'ถูกระงับสิทธิ์สอบ (ติดต่อฝ่ายการเงิน/ทะเบียน)',
                'message' => 'คุณถูกระงับสิทธิ์การสอบ: ' . ($eligibility['reason'] ?: 'ถูกระงับสิทธิ์สอบ (ติดต่อฝ่ายการเงิน/ทะเบียน)')
            ]);
        }

        return response()->json([
            'eligible' => true,
            'reason' => null
        ]);
    }

    public function recordFocusEscape(ExamAttempt $attempt)
    {
        if ($attempt->user_id !== Auth::id() || $attempt->status !== 'in_progress') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $attempt->loadMissing('exam');
        $student = Auth::user()->fresh();
        $student->load('enrolledSubjects');
        $eligibility = $student->getExamEligibility($attempt->exam);
        if (!$eligibility['eligible']) {
            return response()->json([
                'ineligible' => true,
                'error' => 'สิทธิ์การสอบของคุณถูกระงับ',
                'reason' => $eligibility['reason'] ?: 'ถูกระงับสิทธิ์สอบ'
            ], 403);
        }

        $lastAjax = session('last_ajax_focus_escape_at_' . $attempt->id, 0);
        if (now()->timestamp - $lastAjax < 2) {
            return response()->json([
                'success' => true,
                'focus_escape_count' => $attempt->focus_escape_count,
            ]);
        }

        session(['last_ajax_focus_escape_at_' . $attempt->id => now()->timestamp]);
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

        $attempt->loadMissing('exam');
        $student = Auth::user()->fresh();
        $student->load('enrolledSubjects');
        $eligibility = $student->getExamEligibility($attempt->exam);
        if (!$eligibility['eligible']) {
            return response()->json([
                'ineligible' => true,
                'error' => 'สิทธิ์การสอบของคุณถูกระงับ',
                'reason' => $eligibility['reason'] ?: 'ถูกระงับสิทธิ์สอบ'
            ], 403);
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

        $attempt->loadMissing('exam');
        $student = Auth::user()->fresh();
        $student->load('enrolledSubjects');
        $eligibility = $student->getExamEligibility($attempt->exam);
        if (!$eligibility['eligible']) {
            return redirect()->route('student.dashboard')->with('error', 'คุณไม่มีสิทธิ์ส่งข้อสอบวิชานี้เนื่องจากถูกระงับสิทธิ์ (' . ($eligibility['reason'] ?: 'ถูกระงับสิทธิ์') . ')');
        }

        $attempt->load('exam.questions');
        $exam = $attempt->exam;

        $hasEssay = $exam->questions->where('type', 'essay')->count() > 0;
        $gradingStatus = $hasEssay ? 'pending_grading' : 'graded';

        // Calculate score and populate score_awarded for each answer
        $studentAnswers = StudentAnswer::where('exam_attempt_id', $attempt->id)->get();
        $rawScore = 0.0;
        
        foreach ($studentAnswers as $ans) {
            $question = $exam->questions->firstWhere('id', $ans->question_id);
            if ($question) {
                $awarded = $ans->is_correct ? (float)$question->score : 0.0;
                $ans->update(['score_awarded' => $awarded]);
                $rawScore += $awarded;
            }
        }

        // Total possible raw score & target exam total score
        $totalRawScore = (float)$exam->questions->sum('score');
        $examTargetScore = (float)($exam->total_score ?? $totalRawScore);

        // Proportional Scaling: Final Score = (rawScore / totalRawScore) * examTargetScore
        if ($totalRawScore > 0 && $examTargetScore > 0) {
            $finalScore = round(($rawScore / $totalRawScore) * $examTargetScore, 2);
        } else {
            $finalScore = round($rawScore, 2);
        }

        // Check if passed (only if no pending essay questions)
        if ($hasEssay) {
            $isPassed = null; // Do not determine pass/fail yet until teacher grades
        } else {
            $passingPercentage = $exam->passing_percentage;
            $percentageObtained = $examTargetScore > 0 ? ($finalScore / $examTargetScore) * 100 : 0;
            $isPassed = $percentageObtained >= $passingPercentage;
        }

        // Update attempt
        $attempt->update([
            'completed_at' => now(),
            'score' => $finalScore,
            'raw_score' => $rawScore,
            'total_raw_score' => $totalRawScore,
            'is_passed' => $isPassed,
            'status' => 'completed',
            'grading_status' => $gradingStatus,
        ]);

        session()->flash('just_submitted', true);

        $successMsg = $hasEssay 
            ? 'ส่งข้อสอบเรียบร้อยแล้ว (มีข้อสอบข้อเขียนที่อยู่ระหว่างรอผู้สอนตรวจให้คะแนน)' 
            : 'ส่งข้อสอบและบันทึกคะแนนเรียบร้อยแล้ว';

        return redirect()->route('student.exam.result', $attempt->id)
            ->with('success', $successMsg);
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

        $awardedScores = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->pluck('score_awarded', 'question_id')
            ->toArray();

        $teacherFeedbacks = StudentAnswer::where('exam_attempt_id', $attempt->id)
            ->whereNotNull('teacher_feedback')
            ->pluck('teacher_feedback', 'question_id')
            ->toArray();

        return view('student.exam.result', compact(
            'attempt',
            'savedAnswers',
            'savedTextAnswers',
            'correctness',
            'awardedScores',
            'teacherFeedbacks'
        ));
    }
}
